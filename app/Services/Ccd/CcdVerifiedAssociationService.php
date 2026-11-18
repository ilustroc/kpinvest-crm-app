<?php

namespace App\Services\Ccd;

use App\Models\CcdCliente;
use App\Models\CcdClienteCuenta;
use App\Models\ClienteCuenta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prepara y, con autorización explícita, persiste sólo confirmaciones que
 * siguen siendo inequívocas al releer el PDF. No consume CSV previos.
 */
class CcdVerifiedAssociationService
{
    public function __construct(
        private readonly CcdPdfSourceResolver $sources,
        private readonly CcdPdfIdentifierExtractor $extractor,
        private readonly CcdExtractionMatcher $matcher,
        private readonly CcdCuentaAssociationService $accounts,
        private readonly CcdDocumentDniRecognizer $dniRecognizer,
    ) {}

    /**
     * @return array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string}
     */
    public function analyze(CcdCliente $ccd): array
    {
        return $this->analyzeDocument($ccd, false);
    }

    /**
     * Simulación de sólo lectura para medir evidencias documentales candidatas
     * que todavía no están autorizadas para una confirmación automática.
     *
     * @return array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string}
     */
    public function analyzeForDniValidation(CcdCliente $ccd): array
    {
        return $this->analyzeDocument($ccd, true);
    }

    /**
     * @return array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string}
     */
    private function analyzeDocument(CcdCliente $ccd, bool $allowAqpCandidateEvidence): array
    {
        $source = $this->sources->resolve($ccd);

        if ($source['status'] !== 'ok' || $source['path'] === null) {
            return $this->result('error', $source['source'] ?? 'no_disponible', null, null, $source['evidence'], 'documento_no_disponible');
        }

        try {
            $extraction = $this->extractor->extract($source['path']);
            $match = $this->matcher->classify($ccd, $extraction);

            if ($match['clasificacion'] !== 'coincidencia_exacta' || $match['operacion_candidata'] === null) {
                if (in_array($match['evidence'], ['dni_documental_no_coincide', 'dni_documental_contradictorio'], true)
                    && ($confirmed = $this->confirmedAssociationForCcd((int) $ccd->id)) !== null) {
                    return $this->result(
                        'conflicto',
                        (string) $source['source'],
                        (int) $confirmed->cliente_cuenta_id,
                        $confirmed->clienteCuenta?->operacion,
                        $match['evidence'],
                        'dni_documental_contradice_confirmacion_existente',
                        $match['evidence'],
                    );
                }

                $dniEvidence = match ($match['evidence']) {
                    'dni_documental_no_coincide', 'dni_documental_contradictorio' => $match['evidence'],
                    'sin_texto' => 'dni_no_evaluable_sin_texto',
                    'pdf_ilegible' => 'dni_no_evaluable_error_extraccion',
                    default => 'no_evaluada',
                };

                $isAqpIdentityConflict = in_array(strtoupper(trim((string) $ccd->cosecha)), ['AQP4', 'AQP5'], true)
                    && in_array($match['evidence'], ['dni_documental_no_coincide', 'dni_documental_contradictorio'], true);
                $status = match (true) {
                    $isAqpIdentityConflict => 'revision',
                    $match['clasificacion'] === 'ambigua' => 'revision',
                    $match['clasificacion'] === 'error' => 'error',
                    default => 'no_elegible',
                };

                return $this->result(
                    $status,
                    (string) $source['source'],
                    null,
                    null,
                    $match['evidence'],
                    $match['clasificacion'],
                    $dniEvidence,
                );
            }

            $accounts = ($match['cuenta_candidata'] ?? null) instanceof ClienteCuenta
                ? collect([$match['cuenta_candidata']])
                : $this->candidateAccountsFor($ccd)
                    ->filter(fn (ClienteCuenta $account): bool => $this->normalize($account->operacion) === $this->normalize((string) $match['operacion_candidata']))
                    ->values();

            if ($accounts->count() !== 1) {
                return $this->result(
                    'revision',
                    (string) $source['source'],
                    null,
                    (string) $match['operacion_candidata'],
                    $match['evidence'],
                    $accounts->isEmpty() ? 'operacion_no_corresponde_a_cuenta_candidata' : 'operacion_no_identifica_cuenta_unica',
                );
            }

            /** @var ClienteCuenta $account */
            $account = $accounts->first();

            if ($this->normalize($account->numdoc) !== $this->normalize($ccd->numdoc)) {
                return $this->result('revision', (string) $source['source'], null, (string) $account->operacion, $match['evidence'], 'dni_cuenta_no_coincide');
            }

            $aqpEvidence = $this->dniRecognizer->aqpCustomerCodeEvidence(
                (string) $extraction['text'],
                (string) $ccd->numdoc,
                (string) $ccd->cosecha,
            );

            if (in_array($aqpEvidence, [
                CcdDocumentDniRecognizer::AQP_CODE_MISMATCH,
                CcdDocumentDniRecognizer::AQP_CODE_CONTRADICTORY,
            ], true)) {
                if (($confirmed = $this->confirmedAssociationForCcd((int) $ccd->id)) !== null) {
                    return $this->result(
                        'conflicto',
                        (string) $source['source'],
                        (int) $confirmed->cliente_cuenta_id,
                        $confirmed->clienteCuenta?->operacion,
                        $match['evidence'],
                        'codigo_cliente_aqp_contradice_confirmacion_existente',
                        (string) $aqpEvidence,
                    );
                }

                return $this->result(
                    'revision',
                    (string) $source['source'],
                    null,
                    (string) $match['operacion_candidata'],
                    $match['evidence'],
                    'dni_documental_no_corrobable',
                    (string) $aqpEvidence,
                );
            }

            $existing = $this->existingState((int) $ccd->id, (int) $account->id);

            if ($existing !== null) {
                return $this->result($existing['status'], (string) $source['source'], (int) $account->id, (string) $account->operacion, $match['evidence'], $existing['reason']);
            }

            $dniEvidence = $this->dniRecognizer->evidence((string) $extraction['text'], (string) $ccd->numdoc);

            if ($dniEvidence !== null) {
                return $this->result(
                    'elegible',
                    (string) $source['source'],
                    (int) $account->id,
                    (string) $account->operacion,
                    $match['evidence'],
                    'operacion_y_dni_documentales_verificados',
                    $dniEvidence,
                );
            }

            if ($aqpEvidence === CcdDocumentDniRecognizer::AQP_CODE_VERIFIED) {
                return $this->result(
                    $allowAqpCandidateEvidence ? 'elegible' : 'revision',
                    (string) $source['source'],
                    $allowAqpCandidateEvidence ? (int) $account->id : null,
                    (string) $account->operacion,
                    $match['evidence'],
                    $allowAqpCandidateEvidence
                        ? 'operacion_y_codigo_cliente_aqp_documentales_verificados'
                        : 'dni_codigo_cliente_aqp_pendiente_validacion_ampliada',
                    $aqpEvidence,
                );
            }

            if ($aqpEvidence === CcdDocumentDniRecognizer::AQP_CODE_INSUFFICIENT_CONTEXT) {
                return $this->result(
                    'revision',
                    (string) $source['source'],
                    null,
                    (string) $match['operacion_candidata'],
                    $match['evidence'],
                    'dni_documental_no_corrobable',
                    $aqpEvidence,
                );
            }

            return $this->result(
                'revision',
                (string) $source['source'],
                null,
                (string) $account->operacion,
                $match['evidence'],
                'dni_documental_no_corrobable',
                $this->dniRecognizer->missingEvidenceDiagnostic((string) $extraction['text'], (string) $ccd->numdoc),
            );
        } finally {
            $this->sources->release($source);
        }
    }

    /**
     * Vuelve a analizar dentro de una transacción y bajo bloqueo de la CCD;
     * así un segundo proceso no puede confirmar otra cuenta al mismo tiempo.
     *
     * @return array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string}
     */
    public function apply(CcdCliente $ccd): array
    {
        return DB::transaction(function () use ($ccd): array {
            $lockedCcd = CcdCliente::query()->lockForUpdate()->findOrFail($ccd->id);
            $analysis = $this->analyze($lockedCcd);

            if ($analysis['status'] !== 'elegible' || $analysis['account_id'] === null) {
                return $analysis;
            }

            $account = ClienteCuenta::query()->lockForUpdate()->find($analysis['account_id']);

            if ($account === null
                || $this->normalize($account->numdoc) !== $this->normalize($lockedCcd->numdoc)
                || $this->normalize($account->operacion) !== $this->normalize((string) $analysis['operacion'])) {
                return $this->result('revision', $analysis['source'], null, $analysis['operacion'], $analysis['evidence'], 'cuenta_cambio_durante_verificacion', $analysis['dni_evidence']);
            }

            $existing = $this->existingState((int) $lockedCcd->id, (int) $account->id, true);

            if ($existing !== null) {
                return $this->result($existing['status'], $analysis['source'], (int) $account->id, (string) $account->operacion, $analysis['evidence'], $existing['reason'], $analysis['dni_evidence']);
            }

            $link = CcdClienteCuenta::query()
                ->where('ccd_cliente_id', $lockedCcd->id)
                ->where('cliente_cuenta_id', $account->id)
                ->lockForUpdate()
                ->first();

            if ($link === null) {
                $link = new CcdClienteCuenta([
                    'ccd_cliente_id' => $lockedCcd->id,
                    'cliente_cuenta_id' => $account->id,
                ]);
            }

            if ($link->exists && $link->estado === CcdClienteCuenta::ESTADO_EN_REVISION) {
                return $this->result('revision', $analysis['source'], (int) $account->id, (string) $account->operacion, $analysis['evidence'], 'asociacion_existente_en_revision', $analysis['dni_evidence']);
            }

            $link->fill([
                'estado' => CcdClienteCuenta::ESTADO_CONFIRMADA,
                'metodo_verificacion' => 'operacion_pdf_automatica',
                'evidencia' => 'Proceso automático ccd:associate-verified; '.$analysis['evidence'].'; '.$analysis['dni_evidence'].'; fuente '.$analysis['source'].'.',
                'confirmado_por_user_id' => null,
                'confirmado_at' => now(),
            ])->save();

            return $this->result('confirmada', $analysis['source'], (int) $account->id, (string) $account->operacion, $analysis['evidence'], 'confirmacion_automatica_aplicada', $analysis['dni_evidence']);
        }, 3);
    }

    /** @return Collection<int, ClienteCuenta> */
    private function candidateAccountsFor(CcdCliente $ccd): Collection
    {
        $specific = $this->accounts->candidatesFor($ccd);

        return $specific->isNotEmpty() ? $specific : $this->accounts->genericConfianzaCandidatesFor($ccd);
    }

    /** @return array{status:string,reason:string}|null */
    private function existingState(int $ccdId, int $accountId, bool $lock = false): ?array
    {
        $query = CcdClienteCuenta::query()->where('ccd_cliente_id', $ccdId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $links = $query->get();
        $confirmed = $links->where('estado', CcdClienteCuenta::ESTADO_CONFIRMADA);

        if ($confirmed->contains(fn (CcdClienteCuenta $link): bool => (int) $link->cliente_cuenta_id !== $accountId)) {
            return ['status' => 'conflicto', 'reason' => 'ccd_ya_confirmada_en_otra_cuenta'];
        }

        if ($confirmed->contains(fn (CcdClienteCuenta $link): bool => (int) $link->cliente_cuenta_id === $accountId)) {
            return ['status' => 'ya_confirmada', 'reason' => 'asociacion_confirmada_preexistente'];
        }

        if ($links->contains(fn (CcdClienteCuenta $link): bool => $link->estado === CcdClienteCuenta::ESTADO_EN_REVISION)) {
            return ['status' => 'revision', 'reason' => 'asociacion_existente_en_revision'];
        }

        return null;
    }

    private function confirmedAssociationForCcd(int $ccdId): ?CcdClienteCuenta
    {
        return CcdClienteCuenta::query()
            ->with('clienteCuenta:id,operacion')
            ->where('ccd_cliente_id', $ccdId)
            ->where('estado', CcdClienteCuenta::ESTADO_CONFIRMADA)
            ->orderBy('id')
            ->first();
    }

    /** @return array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string} */
    private function result(string $status, string $source, ?int $accountId, ?string $operation, string $evidence, string $reason, string $dniEvidence = 'no_evaluada'): array
    {
        return [
            'status' => $status,
            'source' => $source,
            'account_id' => $accountId,
            'operacion' => $operation,
            'evidence' => $evidence,
            'method' => 'operacion_pdf_automatica',
            'reason' => $reason,
            'dni_evidence' => $dniEvidence,
        ];
    }

    private function normalize(?string $value): string
    {
        return preg_replace('/\s+/', '', strtoupper(trim((string) $value))) ?: '';
    }
}
