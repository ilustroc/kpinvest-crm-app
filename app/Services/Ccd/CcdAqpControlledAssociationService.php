<?php

namespace App\Services\Ccd;

use App\Models\CcdArchivo;
use App\Models\CcdCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CcdAqpControlledAssociationService
{
    public function __construct(
        private readonly CcdVerifiedAssociationService $associations,
    ) {}

    /**
     * @param  array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string}  $audit
     * @return array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string,document_sha256:?string}
     */
    public function analyze(CcdCliente $ccd, array $audit): array
    {
        if (($invalid = $this->auditMismatch($ccd, $audit)) !== null) {
            return $this->result('revision', $invalid);
        }

        $before = $this->documentFingerprint($ccd);

        if ($before['status'] !== 'ok') {
            return $this->result('error', $before['evidence']);
        }

        $analysis = $this->associations->analyzeForDniValidation($ccd);
        $after = $this->documentFingerprint($ccd);

        if ($after['status'] !== 'ok' || ! hash_equals((string) $before['sha256'], (string) $after['sha256'])) {
            return $this->result('error', 'documento_cambio_durante_revalidacion');
        }

        return $this->controlledResult($analysis, (string) $before['sha256']);
    }

    /**
     * @param  array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string}  $audit
     * @return array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string,document_sha256:?string}
     */
    public function apply(CcdCliente $ccd, array $audit): array
    {
        return DB::transaction(function () use ($ccd, $audit): array {
            $locked = CcdCliente::query()
                ->with('archivo:id,disk,path,sha256')
                ->lockForUpdate()
                ->findOrFail($ccd->id);

            if (($invalid = $this->auditMismatch($locked, $audit)) !== null) {
                return $this->result('revision', $invalid);
            }

            $before = $this->documentFingerprint($locked);

            if ($before['status'] !== 'ok') {
                return $this->result('error', $before['evidence']);
            }

            $analysis = $this->associations->applyAqpAudited($locked, $audit['source_sha256']);
            $after = $this->documentFingerprint($locked);

            if ($after['status'] !== 'ok' || ! hash_equals((string) $before['sha256'], (string) $after['sha256'])) {
                throw new RuntimeException('El documento cambió durante la asociación AQP controlada.');
            }

            return $this->controlledResult($analysis, (string) $before['sha256']);
        }, 3);
    }

    /**
     * @param  array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string}  $audit
     */
    private function auditMismatch(CcdCliente $ccd, array $audit): ?string
    {
        if ((int) $ccd->id !== $audit['ccd_id']) {
            return 'ccd_no_corresponde_a_auditoria_v3';
        }

        $harvest = strtoupper(trim((string) $ccd->cosecha));

        if ($harvest !== $audit['cosecha'] || ! in_array($harvest, ['AQP4', 'AQP5'], true)) {
            return 'cosecha_cambio_desde_auditoria_v3';
        }

        if (! preg_match('/^[a-f0-9]{64}$/D', $audit['source_sha256'])) {
            return 'huella_auditoria_v3_invalida';
        }

        return null;
    }

    /** @return array{status:string,sha256:?string,evidence:string} */
    private function documentFingerprint(CcdCliente $ccd): array
    {
        $archivoId = $ccd->getAttribute('ccd_archivo_id');
        $archivo = $ccd->relationLoaded('archivo')
            ? $ccd->archivo
            : ($archivoId !== null ? CcdArchivo::query()->find((int) $archivoId) : null);

        if ($archivo === null) {
            return ['status' => 'error', 'sha256' => null, 'evidence' => 'archivo_privado_no_catalogado'];
        }

        $diskName = (string) config('ccd.private_disk');
        $expectedPath = CcdArchivo::pathForHash((string) $archivo->sha256);

        if ($archivo->disk !== $diskName || $archivo->path !== $expectedPath) {
            return ['status' => 'error', 'sha256' => null, 'evidence' => 'archivo_privado_no_valido'];
        }

        try {
            $disk = Storage::disk($diskName);
            $path = $disk->path($archivo->path);
            $hash = $disk->exists($archivo->path) && is_file($path) && is_readable($path)
                ? hash_file('sha256', $path)
                : false;
        } catch (Throwable) {
            $hash = false;
        }

        if (! is_string($hash)) {
            return ['status' => 'error', 'sha256' => null, 'evidence' => 'archivo_privado_no_disponible'];
        }

        if (! hash_equals(strtolower((string) $archivo->sha256), strtolower($hash))) {
            return ['status' => 'error', 'sha256' => $hash, 'evidence' => 'archivo_privado_sha256_no_coincide'];
        }

        return ['status' => 'ok', 'sha256' => strtolower($hash), 'evidence' => 'archivo_privado_sha256_verificado'];
    }

    /**
     * @param  array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string}  $analysis
     * @return array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string,document_sha256:?string}
     */
    private function controlledResult(array $analysis, string $documentSha256): array
    {
        if (in_array($analysis['status'], ['error', 'conflicto'], true)) {
            return array_merge($analysis, ['document_sha256' => $documentSha256]);
        }

        if ($analysis['source'] !== 'privado'
            || $analysis['dni_evidence'] !== CcdDocumentDniRecognizer::AQP_CODE_VERIFIED) {
            return $this->result('revision', 'evidencia_aqp_v3_no_revalidada', $documentSha256, $analysis);
        }

        return array_merge($analysis, ['document_sha256' => $documentSha256]);
    }

    /**
     * @param  array{source:string,account_id:?int,operacion:?string,evidence:string,method:string,dni_evidence:string}|null  $analysis
     * @return array{status:string,source:string,account_id:?int,operacion:?string,evidence:string,method:string,reason:string,dni_evidence:string,document_sha256:?string}
     */
    private function result(string $status, string $reason, ?string $documentSha256 = null, ?array $analysis = null): array
    {
        return [
            'status' => $status,
            'source' => $analysis['source'] ?? 'no_disponible',
            'account_id' => $analysis['account_id'] ?? null,
            'operacion' => $analysis['operacion'] ?? null,
            'evidence' => $analysis['evidence'] ?? 'control_aqp_v4',
            'method' => $analysis['method'] ?? 'operacion_pdf_aqp_auditada_v3',
            'reason' => $reason,
            'dni_evidence' => $analysis['dni_evidence'] ?? 'no_evaluada',
            'document_sha256' => $documentSha256,
        ];
    }
}
