<?php

namespace App\Services\Ccd;

use App\Models\CcdCliente;
use App\Models\ClienteCuenta;

class CcdExtractionMatcher
{
    public function __construct(
        private readonly CcdCuentaAssociationService $associations,
    ) {}

    /**
     * @param array{status:string,text_length:int,dnis:array<int,string>,cuentas:array<int,string>,operaciones:array<int,string>,entidades:array<int,string>,text:string,evidence:string} $extraction
     * @return array{clasificacion:string,operacion_candidata:?string,evidence:string,candidate_count:int}
     */
    public function classify(CcdCliente $ccd, array $extraction): array
    {
        if ($extraction['status'] !== 'ok') {
            return $this->result($extraction['status'], null, $extraction['evidence'], 0);
        }

        $expectedDni = $this->normalize($ccd->numdoc);
        $extractedDnis = array_map(fn (string $dni): string => $this->normalize($dni), $extraction['dnis']);

        if ($extractedDnis !== [] && ! in_array($expectedDni, $extractedDnis, true)) {
            return $this->result('sin_coincidencia', null, 'dni_documental_no_coincide', 0);
        }

        $candidates = $this->associations->candidatesFor($ccd);

        if ($candidates->isEmpty()) {
            return $this->result('sin_coincidencia', null, 'sin_cuenta_compatible_por_dni_y_cosecha', 0);
        }

        $operations = array_map(fn (string $operation): string => $this->normalize($operation), $extraction['operaciones']);
        $operationMatches = $candidates
            ->filter(fn (ClienteCuenta $account): bool => in_array($this->normalize($account->operacion), $operations, true))
            ->values();

        if ($operationMatches->count() === 1) {
            return $this->result('coincidencia_exacta', (string) $operationMatches->first()->operacion, 'operacion_documental_exacta', $candidates->count());
        }

        if ($operationMatches->count() > 1) {
            return $this->result('ambigua', null, 'operacion_documental_corresponde_a_varias_cuentas', $candidates->count());
        }

        $accounts = array_map(fn (string $account): string => $this->normalize($account), $extraction['cuentas']);
        $accountMatches = $candidates
            ->filter(fn (ClienteCuenta $account): bool => in_array($this->normalize($account->cuenta), $accounts, true))
            ->values();

        if ($accountMatches->isNotEmpty()) {
            $operationsForAccounts = $accountMatches->pluck('operacion')->map(fn ($operation) => $this->normalize($operation))->unique();

            return $this->result(
                'coincidencia_cuenta',
                $operationsForAccounts->count() === 1 ? (string) $accountMatches->first()->operacion : null,
                $operationsForAccounts->count() === 1
                    ? 'cuenta_documental_sin_operacion_explicita'
                    : 'cuenta_documental_con_varias_operaciones',
                $candidates->count(),
            );
        }

        if ($operations === [] && $accounts === []) {
            return $this->result('sin_coincidencia', null, 'texto_sin_identificadores_de_cuenta_u_operacion', $candidates->count());
        }

        return $this->result('sin_coincidencia', null, 'identificador_documental_sin_cuenta_compatible', $candidates->count());
    }

    /** @return array{clasificacion:string,operacion_candidata:?string,evidence:string,candidate_count:int} */
    private function result(string $classification, ?string $operation, string $evidence, int $candidateCount): array
    {
        return [
            'clasificacion' => $classification,
            'operacion_candidata' => $operation,
            'evidence' => $evidence,
            'candidate_count' => $candidateCount,
        ];
    }

    private function normalize(?string $value): string
    {
        return preg_replace('/\s+/', '', strtoupper(trim((string) $value))) ?: '';
    }
}
