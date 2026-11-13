<?php

namespace App\Services\Cliente;

use App\Models\CcdCliente;
use App\Models\CcdClienteCuenta;
use App\Models\PromesaPago;
use App\Services\Asignacion\ClienteAssignmentService;
use App\ViewModels\Cliente\ClienteShowViewModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClienteProfileService
{
    public function __construct(
        private readonly ClienteAccountService $accounts,
        private readonly ClientePaymentService $payments,
        private readonly ClienteAssignmentService $assignments,
    ) {
    }

    public function show(string $dni): ClienteShowViewModel
    {
        $cuentas = $this->accounts->accountsForDni($dni);
        abort_if($cuentas->isEmpty(), 404);

        $pagos = $this->payments->paymentsForDni($dni);
        $pagosGrouped = $this->payments->groupedByOperation($pagos);
        $accountByOperation = $this->payments->accountByOperation($pagos);

        $resolvedAssignments = Schema::hasTable('asignar_clientes')
            ? $this->assignments->resolveActiveAssignmentsForAccounts($cuentas)
            : [];

        $advisorByOperation = collect($resolvedAssignments['advisor_name_by_operation'] ?? []);

        $cuentas = $this->accounts->addPaymentAndAdvisorData(
            $cuentas,
            $pagosGrouped,
            $accountByOperation,
            $advisorByOperation,
            $resolvedAssignments,
        );
        $financialTotals = $this->accounts->financialTotals($cuentas);

        [$ccdDocs, $ccdByDni, $ccdByCodigo, $ccdByCosecha, $ccdCosechaKeys] = $this->ccdData($dni, $cuentas);
        [$cnasByCuenta, $cnasByOperacion] = $this->cnaData($dni, $cuentas, $accountByOperation);

        return new ClienteShowViewModel(
            numdoc: $dni,
            titular: $cuentas->first()->nombre ?? '-',
            cuentas: $cuentas,
            pagos: $pagos,
            promesas: $this->promesasForDni($dni),
            ccdDocs: $ccdDocs,
            ccdByDni: $ccdByDni,
            ccdByCodigo: $ccdByCodigo,
            ccdByCosecha: $ccdByCosecha,
            ccdCosechaKeys: $ccdCosechaKeys,
            cnasByCuenta: $cnasByCuenta,
            cnasByOperacion: $cnasByOperacion,
            pagosGrouped: $pagosGrouped,
            nextNroCarta: $this->nextNroCarta(),
            totPagos: $this->payments->total($pagos),
            totCapital: $financialTotals['capital'],
            totDeuda: $financialTotals['deuda'],
            dni: $dni,
        );
    }

    private function promesasForDni(string $dni): Collection
    {
        $col = Schema::hasColumn('promesas_pago', 'numdoc') ? 'numdoc' : 'dni';

        return PromesaPago::query()
            ->where($col, $dni)
            ->with([
                'user:id,name',
                'operaciones',
                'cuotas' => fn ($query) => $query->orderBy('nro'),
            ])
            ->orderByDesc('fecha_promesa')
            ->get();
    }

    private function ccdData(string $dni, Collection $cuentas): array
    {
        $ccdDocs = collect();
        $ccdByDni = collect();
        $ccdByCodigo = collect();
        $ccdByCosecha = collect();
        $ccdCosechaKeys = [];

        if (! Schema::hasTable('ccd_clientes')) {
            return [$ccdDocs, $ccdByDni, $ccdByCodigo, $ccdByCosecha, $ccdCosechaKeys];
        }

        $columns = DB::getSchemaBuilder()->getColumnListing('ccd_clientes');
        $selected = collect(['id', 'numdoc', 'pdf', 'cosecha', 'link', 'codigo'])
            ->filter(fn ($column) => in_array($column, $columns, true))
            ->all();

        $ccdDocs = CcdCliente::query()
            ->where('numdoc', $dni)
            ->orderByDesc('id')
            ->get($selected);

        if (Schema::hasTable('ccd_cliente_cuenta') && $ccdDocs->isNotEmpty()) {
            $confirmedLinks = CcdClienteCuenta::query()
                ->whereIn('ccd_cliente_id', $ccdDocs->pluck('id'))
                ->where('estado', CcdClienteCuenta::ESTADO_CONFIRMADA)
                ->with('ccdCliente:id,numdoc,pdf,cosecha')
                ->get()
                ->filter(fn (CcdClienteCuenta $link) => $link->ccdCliente !== null
                    && hash_equals($dni, (string) $link->ccdCliente->numdoc))
                ->groupBy('cliente_cuenta_id');

            $confirmedIds = $confirmedLinks
                ->flatten()
                ->pluck('ccd_cliente_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            foreach ($cuentas as $account) {
                $account->ccdConfirmados = collect($confirmedLinks->get((int) $account->id, []))
                    ->pluck('ccdCliente')
                    ->filter()
                    ->sortBy('id')
                    ->values();
            }

            $ccdDocs = $ccdDocs
                ->reject(fn (CcdCliente $document): bool => in_array((int) $document->id, $confirmedIds, true))
                ->values();
        }

        $ccdByDni = collect([$dni => $ccdDocs->values()]);

        if (in_array('codigo', $columns, true)) {
            $ccdByCodigo = $ccdDocs->groupBy('codigo');
        }

        $normalize = fn ($value) => preg_replace('/[\s_]+/', '', strtoupper(trim((string) $value)));

        $ccdByCosecha = $ccdDocs->groupBy(fn ($document) => $normalize($document->cosecha));

        foreach ($cuentas as $account) {
            $source = (string) ($account->cosecha ?? '');
            $mapped = $this->accounts->mapCosechaClientesToCcd($source);
            $ccdCosechaKeys[$source] = $normalize($mapped);
        }

        return [$ccdDocs, $ccdByDni, $ccdByCodigo, $ccdByCosecha, $ccdCosechaKeys];
    }

    private function cnaData(string $dni, Collection $cuentas, Collection $accountByOperation): array
    {
        if (! Schema::hasTable('cna_solicitudes')) {
            return [collect(), collect()];
        }

        $columns = DB::getSchemaBuilder()->getColumnListing('cna_solicitudes');
        $docCol = in_array('numdoc', $columns, true) ? 'numdoc' : 'dni';
        $selected = collect([
            'id',
            $docCol,
            'nro_carta',
            'operaciones',
            'workflow_estado',
            'created_at',
            'pdf_path',
            'docx_path',
        ])->filter(fn ($column) => in_array($column, $columns, true))->values()->all();

        $cnas = DB::table('cna_solicitudes')
            ->select($selected)
            ->where($docCol, $dni)
            ->orderByDesc('created_at')
            ->get();

        $byAccount = [];
        $byOperation = [];

        foreach ($cnas as $cna) {
            foreach ($this->decodeOperations($cna->operaciones ?? '[]') as $operation) {
                $summary = (object) [
                    'id' => $cna->id,
                    'nro_carta' => $cna->nro_carta ?? $cna->id,
                    'workflow_estado' => $cna->workflow_estado ?? 'pendiente',
                    'created_at' => $cna->created_at,
                    'pdf_path' => $cna->pdf_path ?? null,
                    'docx_path' => $cna->docx_path ?? null,
                ];

                $byOperation[$operation] = $byOperation[$operation] ?? collect();
                $byOperation[$operation]->push($summary);

                $account = optional($cuentas->firstWhere('operacion', $operation))->cuenta;
                $account = (string) ($account ?: ($accountByOperation[$operation] ?? $operation));

                $byAccount[$account] = $byAccount[$account] ?? collect();
                $byAccount[$account]->push($summary);
            }
        }

        return [collect($byAccount), collect($byOperation)];
    }

    private function decodeOperations(mixed $raw): array
    {
        $operations = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        if (! is_array($operations)) {
            $operations = array_filter(array_map('trim', explode(',', (string) $raw)));
        }

        return array_values(array_filter($operations, fn ($operation) => $operation !== null && $operation !== ''));
    }

    private function nextNroCarta(): ?string
    {
        if (! Schema::hasTable('cna_solicitudes')) {
            return null;
        }

        $columns = DB::getSchemaBuilder()->getColumnListing('cna_solicitudes');

        if (! in_array('correlativo', $columns, true)) {
            return null;
        }

        $max = (int) DB::table('cna_solicitudes')->max('correlativo');

        return str_pad(($max ?: 0) + 1, 6, '0', STR_PAD_LEFT);
    }
}
