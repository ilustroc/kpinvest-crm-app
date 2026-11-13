<?php

namespace App\Services\Ccd;

use App\Models\CcdCliente;
use App\Models\CcdClienteCuenta;
use App\Models\ClienteCuenta;
use App\Services\Cliente\ClienteAccountService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CcdCuentaAssociationService
{
    public function __construct(
        private readonly ClienteAccountService $accounts,
    ) {}

    /**
     * Clasifica un CCD por DNI y cosecha. Una coincidencia de cosecha nunca se
     * considera confirmación por sí sola: sólo crea candidatas revisables.
     *
     * @return array{ccd_id:int,candidates:Collection<int, ClienteCuenta>,candidate_count:int,status:string,confirmed_cliente_cuenta_id:?int}
     */
    public function audit(
        CcdCliente $ccd,
        bool $persist = false,
        ?string $confirmedOperation = null,
        ?int $confirmedByUserId = null,
        ?string $evidence = null,
        string $verificationMethod = 'revision_visual_pdf',
    ): array {
        $candidates = $this->candidatesFor($ccd);
        $confirmedAccount = $this->confirmationCandidate($candidates, $confirmedOperation);

        if ($confirmedOperation !== null && trim($evidence ?? '') === '') {
            throw new DomainException('La confirmación requiere evidencia de la revisión del documento.');
        }

        if ($persist && $confirmedAccount !== null && $confirmedByUserId === null) {
            throw new DomainException('La confirmación requiere el usuario que verificó el documento.');
        }

        if ($persist) {
            DB::transaction(function () use (
                $ccd,
                $candidates,
                $confirmedAccount,
                $confirmedByUserId,
                $evidence,
                $verificationMethod
            ): void {
                foreach ($candidates as $candidate) {
                    $this->storeCandidate($ccd, $candidate);
                }

                if ($confirmedAccount !== null && $confirmedByUserId !== null) {
                    $this->confirm(
                        $ccd,
                        $confirmedAccount,
                        $confirmedByUserId,
                        (string) $evidence,
                        $verificationMethod,
                    );
                }
            });
        }

        return [
            'ccd_id' => (int) $ccd->id,
            'candidates' => $candidates,
            'candidate_count' => $candidates->count(),
            'status' => $confirmedAccount !== null
                ? 'confirmada'
                : ($candidates->isEmpty() ? 'sin_candidata' : ($candidates->count() === 1 ? 'candidata_unica' : 'ambigua')),
            'confirmed_cliente_cuenta_id' => $confirmedAccount?->id,
        ];
    }

    /** @return Collection<int, ClienteCuenta> */
    public function candidatesFor(CcdCliente $ccd): Collection
    {
        $numdoc = trim((string) $ccd->numdoc);
        $ccdCosecha = $this->normalizeCosecha((string) $ccd->cosecha, false);

        if ($numdoc === '' || $ccdCosecha === '') {
            return collect();
        }

        return ClienteCuenta::query()
            ->where('numdoc', $numdoc)
            ->orderBy('id')
            ->get(['id', 'numdoc', 'cuenta', 'operacion', 'entidad', 'producto', 'cosecha'])
            ->filter(fn (ClienteCuenta $account): bool => $this->normalizeCosecha((string) $account->cosecha) === $ccdCosecha)
            ->values();
    }

    public function normalizeCosecha(?string $cosecha, bool $fromClienteCuenta = true): string
    {
        $value = trim((string) $cosecha);

        if ($fromClienteCuenta) {
            $value = (string) ($this->accounts->mapCosechaClientesToCcd($value) ?? '');
        }

        return preg_replace('/[^A-Z0-9]+/', '', strtoupper($value)) ?: '';
    }

    private function confirmationCandidate(Collection $candidates, ?string $operation): ?ClienteCuenta
    {
        if ($operation === null || trim($operation) === '') {
            return null;
        }

        $matches = $candidates
            ->filter(fn (ClienteCuenta $account): bool => trim((string) $account->operacion) === trim($operation))
            ->values();

        if ($matches->count() !== 1) {
            throw new DomainException('La operación confirmada no identifica de forma única una cuenta candidata.');
        }

        return $matches->first();
    }

    private function storeCandidate(CcdCliente $ccd, ClienteCuenta $account): void
    {
        $link = CcdClienteCuenta::query()->firstOrNew([
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $account->id,
        ]);

        if ($link->exists) {
            return;
        }

        $link->fill([
            'estado' => CcdClienteCuenta::ESTADO_CANDIDATA,
            'metodo_verificacion' => 'dni_cosecha_normalizada',
            'evidencia' => 'Coincidencia de DNI y cosecha normalizada; requiere verificación de operación.',
        ])->save();
    }

    private function confirm(
        CcdCliente $ccd,
        ClienteCuenta $account,
        int $confirmedByUserId,
        string $evidence,
        string $verificationMethod,
    ): void {
        $link = CcdClienteCuenta::query()->firstOrNew([
            'ccd_cliente_id' => $ccd->id,
            'cliente_cuenta_id' => $account->id,
        ]);

        $link->fill([
            'estado' => CcdClienteCuenta::ESTADO_CONFIRMADA,
            'metodo_verificacion' => $verificationMethod,
            'evidencia' => $evidence,
            'confirmado_por_user_id' => $confirmedByUserId,
            'confirmado_at' => now(),
        ])->save();
    }
}
