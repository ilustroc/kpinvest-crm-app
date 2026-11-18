<?php

namespace App\Console\Commands;

use App\Models\CcdCliente;
use App\Services\Ccd\CcdAqpAssociationApplyGuard;
use App\Services\Ccd\CcdAqpControlledAssociationService;
use App\Services\Ccd\CcdAqpHumanReviewReader;
use App\Services\Ccd\CcdAqpV3AuditReader;
use App\Services\Ccd\CcdBulkProcessLock;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AssociateAuditedAqpCcd extends Command
{
    private const EXPECTED_TOTAL = 10_224;

    private const EXPECTED_INPUTS = 12;

    private const EXPECTED_AQP4 = 7_024;

    private const EXPECTED_AQP5 = 3_200;

    private const APPLY_AUTHORIZATION = 'CONFIRMAR_ASOCIACION_AQP_AUDITADA';

    private const APPLY_CONFIRMATION = 'REVALIDAR_PDF_Y_CONFIRMAR_AQP';

    protected $signature = 'ccd:associate-aqp-audited
        {--input=* : CSV privados producidos por ccd:recheck-dni V3 dentro de audits/}
        {--human-review=ccd-aqp-revision-humana-20261118-211134-aprobada.csv : CSV privado de revisión humana aprobado}
        {--human-review-sha256=2ebbfa12df1e0dad537c8fdb57d60075c5b4cb995b97d71d56b4e67c9cb80942 : SHA-256 aprobado de la revisión humana}
        {--expected-human-total=40}
        {--expected-human-aqp4=20}
        {--expected-human-aqp5=20}
        {--expected-human-per-stratum=5}
        {--ccd-id= : Procesa exactamente una CCD incluida en la revisión humana}
        {--cosecha= : AQP4 o AQP5; vacío procesa ambas}
        {--after-id=0 : Procesa sólo CCD con ID mayor al indicado}
        {--limit=25 : Máximo por invocación, entre 1 y 100}
        {--expected-total=10224 : Total único esperado en la auditoría completa}
        {--expected-inputs=12 : Cantidad esperada de CSV V3}
        {--expected-aqp4=7024 : Total AQP4 esperado}
        {--expected-aqp5=3200 : Total AQP5 esperado}
        {--apply : Persiste únicamente asociaciones AQP revalidadas}
        {--authorization= : Primera autorización requerida con --apply}
        {--confirmation= : Segunda confirmación requerida con --apply}
        {--output= : Nombre del CSV privado, sin ruta}';

    protected $description = 'Diagnostica o asocia CCD AQP4/AQP5 previamente auditadas en V3';

    public function handle(
        CcdAqpV3AuditReader $reader,
        CcdAqpHumanReviewReader $humanReviewReader,
        CcdAqpControlledAssociationService $associations,
        CcdAqpAssociationApplyGuard $applyGuard,
        CcdBulkProcessLock $processLock,
    ): int {
        $options = $this->validatedOptions();

        if ($options === null) {
            return self::FAILURE;
        }

        try {
            $audit = $reader->read($options['inputs']);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->auditTotalsMatch($audit, $options)) {
            return self::FAILURE;
        }

        try {
            $humanReview = $humanReviewReader->read(
                $options['human_review'],
                $options['human_review_sha256'],
                $audit,
                $options['expected_human_total'],
                $options['expected_human_aqp4'],
                $options['expected_human_aqp5'],
                $options['expected_human_per_stratum'],
            );
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($options['apply']) {
            if ($options['expected_total'] !== self::EXPECTED_TOTAL
                || $options['expected_inputs'] !== self::EXPECTED_INPUTS
                || $options['expected_aqp4'] !== self::EXPECTED_AQP4
                || $options['expected_aqp5'] !== self::EXPECTED_AQP5) {
                $this->error('El modo --apply exige los totales oficiales completos de la auditoría AQP V3.');

                return self::FAILURE;
            }

            if ($options['human_review'] !== CcdAqpHumanReviewReader::APPROVED_FILENAME
                || ! hash_equals(CcdAqpHumanReviewReader::APPROVED_SHA256, $options['human_review_sha256'])
                || $options['expected_human_total'] !== CcdAqpHumanReviewReader::APPROVED_TOTAL
                || $options['expected_human_aqp4'] !== CcdAqpHumanReviewReader::APPROVED_AQP4
                || $options['expected_human_aqp5'] !== CcdAqpHumanReviewReader::APPROVED_AQP5
                || $options['expected_human_per_stratum'] !== CcdAqpHumanReviewReader::APPROVED_PER_STRATUM) {
                $this->error('El modo --apply exige el archivo oficial completo de revisión humana AQP V4.1.');

                return self::FAILURE;
            }

            if ($options['ccd_id'] === null) {
                $this->error('Durante el piloto, --apply exige seleccionar exactamente una CCD mediante --ccd-id.');

                return self::FAILURE;
            }
        }

        if ($options['ccd_id'] !== null && ! isset($humanReview['approved'][$options['ccd_id']])) {
            $this->error('La CCD indicada no pertenece a la revisión humana aprobada.');

            return self::FAILURE;
        }

        if ($options['apply'] && ($reason = $applyGuard->rejectionReason(DB::connection())) !== null) {
            $this->error($reason);

            return self::FAILURE;
        }

        $candidates = array_values(array_filter(
            $audit['candidates'],
            fn (array $candidate): bool => isset($humanReview['approved'][$candidate['ccd_id']])
                && ($options['ccd_id'] === null || $candidate['ccd_id'] === $options['ccd_id'])
                && ($options['ccd_id'] !== null || $candidate['ccd_id'] > $options['after_id'])
                && ($options['cosecha'] === null || $candidate['cosecha'] === $options['cosecha']),
        ));
        $candidates = array_map(function (array $candidate) use ($humanReview): array {
            $approved = $humanReview['approved'][$candidate['ccd_id']];
            $candidate['human_review_sha256'] = $approved['human_review_sha256'];
            $candidate['human_stratum'] = $approved['stratum'];

            return $candidate;
        }, array_slice($candidates, 0, $options['ccd_id'] === null ? $options['limit'] : 1));

        if ($candidates === []) {
            if ($options['ccd_id'] !== null) {
                $this->error('La CCD aprobada no coincide con los filtros o ya no pertenece a la auditoría V3.');

                return self::FAILURE;
            }

            $this->warn('No quedan CCD auditadas para los filtros indicados.');

            return self::SUCCESS;
        }

        try {
            if (! $processLock->acquire()) {
                $this->error('Ya existe otro proceso de asociación CCD en ejecución.');

                return self::FAILURE;
            }

            return $this->process($candidates, $audit, $humanReview, $options, $associations);
        } finally {
            $processLock->release();
        }
    }

    /**
     * @param  array<int,array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string,human_review_sha256:string,human_stratum:string}>  $candidates
     * @param  array{total:int,counts:array{AQP4:int,AQP5:int},reports:array<int,array{name:string,sha256:string,rows:int}>}  $audit
     * @param  array{total:int,counts:array{AQP4:int,AQP5:int},sha256:string}  $humanReview
     * @param  array{inputs:array<int,string>,human_review:string,human_review_sha256:string,expected_human_total:int,expected_human_aqp4:int,expected_human_aqp5:int,expected_human_per_stratum:int,ccd_id:?int,cosecha:?string,after_id:int,limit:int,expected_total:int,expected_inputs:int,expected_aqp4:int,expected_aqp5:int,apply:bool,output:string}  $options
     */
    private function process(array $candidates, array $audit, array $humanReview, array $options, CcdAqpControlledAssociationService $associations): int
    {
        $disk = Storage::disk((string) config('ccd.private_disk'));
        $output = 'audits/'.$options['output'];

        if ($disk->exists($output)) {
            $this->error('El CSV privado de salida ya existe; no se sobrescribió.');

            return self::FAILURE;
        }

        $temporary = 'audits/.tmp-'.Str::uuid().'.csv';
        $path = $disk->path($temporary);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('No se pudo preparar el directorio privado de auditoría AQP.');

            return self::FAILURE;
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            $this->error('No se pudo crear el CSV privado AQP V4.');

            return self::FAILURE;
        }

        fputcsv($handle, [
            'ccd_id',
            'cosecha',
            'auditoria_v3_sha256',
            'revision_humana_sha256',
            'documento_sha256',
            'resultado',
            'motivo',
            'evidencia_operacion',
            'evidencia_dni',
            'fuente',
            'metodo_verificacion',
            'modo',
        ]);

        $counts = ['processed' => 0, 'eligible' => 0, 'confirmed' => 0, 'already_confirmed' => 0, 'integrity_failures' => 0];
        $failed = false;
        $lastId = 0;

        foreach ($candidates as $candidate) {
            $lastId = $candidate['ccd_id'];
            $ccd = CcdCliente::query()
                ->with('archivo:id,disk,path,sha256')
                ->find($candidate['ccd_id']);

            try {
                $result = $ccd === null
                    ? $this->technicalResult('ccd_no_encontrada')
                    : ($options['apply']
                        ? $associations->apply($ccd, $candidate)
                        : $associations->analyze($ccd, $candidate));
            } catch (Throwable $exception) {
                Log::error('CCD AQP controlled association failed.', [
                    'exception_class' => $exception::class,
                    'ccd_id' => $candidate['ccd_id'],
                ]);
                $result = $this->technicalResult('error_transaccion_aqp');
            }

            $counts['processed']++;
            $counter = match ($result['status']) {
                'elegible' => 'eligible',
                'confirmada' => 'confirmed',
                'ya_confirmada' => 'already_confirmed',
                default => 'integrity_failures',
            };
            $counts[$counter]++;

            fputcsv($handle, array_map([$this, 'safeCsv'], [
                $candidate['ccd_id'],
                $candidate['cosecha'],
                $candidate['source_sha256'],
                $candidate['human_review_sha256'],
                $result['document_sha256'],
                $result['status'],
                $result['reason'],
                $result['evidence'],
                $result['dni_evidence'],
                $result['source'],
                $result['method'],
                $options['apply'] ? 'aplicacion' : 'dry-run',
            ]));

            if ($counter === 'integrity_failures') {
                $failed = true;
                break;
            }

            gc_collect_cycles();
        }

        fclose($handle);

        if (! $disk->move($temporary, $output)) {
            $disk->delete($temporary);
            $this->error('No se pudo publicar el CSV privado AQP V4.');

            return self::FAILURE;
        }

        $this->table(['Control', 'Valor'], [
            ['CSV V3 verificados', count($audit['reports'])],
            ['CCD únicas auditadas', $audit['total']],
            ['AQP4 auditadas', $audit['counts']['AQP4']],
            ['AQP5 auditadas', $audit['counts']['AQP5']],
            ['Revisión humana SHA-256', $humanReview['sha256']],
            ['CCD con aprobación humana', $humanReview['total']],
            ['AQP4 con aprobación humana', $humanReview['counts']['AQP4']],
            ['AQP5 con aprobación humana', $humanReview['counts']['AQP5']],
            ['Procesadas en este lote', $counts['processed']],
            ['Elegibles', $counts['eligible']],
            ['Confirmadas', $counts['confirmed']],
            ['Ya confirmadas', $counts['already_confirmed']],
            ['Fallos de integridad', $counts['integrity_failures']],
            ['Último CCD ID', $lastId],
        ]);
        $this->info("CSV privado generado: {$output}");
        $this->comment($options['apply']
            ? 'Aplicación AQP controlada completada con revalidación documental por registro.'
            : 'Dry-run completado: no se insertó, actualizó ni eliminó ninguna asociación.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{total:int,counts:array{AQP4:int,AQP5:int},reports:array<int,array{name:string,sha256:string,rows:int}>}  $audit
     * @param  array{expected_total:int,expected_inputs:int,expected_aqp4:int,expected_aqp5:int}  $options
     */
    private function auditTotalsMatch(array $audit, array $options): bool
    {
        $checks = [
            'CCD únicas' => [$audit['total'], $options['expected_total']],
            'CSV V3' => [count($audit['reports']), $options['expected_inputs']],
            'AQP4' => [$audit['counts']['AQP4'], $options['expected_aqp4']],
            'AQP5' => [$audit['counts']['AQP5'], $options['expected_aqp5']],
        ];

        foreach ($checks as $label => [$actual, $expected]) {
            if ($actual !== $expected) {
                $this->error("La auditoría V3 no coincide en {$label}: actual {$actual}, esperado {$expected}.");

                return false;
            }
        }

        return true;
    }

    /** @return array{inputs:array<int,string>,human_review:string,human_review_sha256:string,expected_human_total:int,expected_human_aqp4:int,expected_human_aqp5:int,expected_human_per_stratum:int,ccd_id:?int,cosecha:?string,after_id:int,limit:int,expected_total:int,expected_inputs:int,expected_aqp4:int,expected_aqp5:int,apply:bool,output:string}|null */
    private function validatedOptions(): ?array
    {
        $inputs = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) $this->option('input'),
        ))));
        $cosecha = strtoupper(trim((string) $this->option('cosecha'))) ?: null;
        $humanReview = trim((string) $this->option('human-review'));
        $humanReviewSha256 = strtolower(trim((string) $this->option('human-review-sha256')));
        $expectedHumanTotal = $this->integerOption('expected-human-total', 1, 1_000);
        $expectedHumanAqp4 = $this->integerOption('expected-human-aqp4', 0, 1_000);
        $expectedHumanAqp5 = $this->integerOption('expected-human-aqp5', 0, 1_000);
        $expectedHumanPerStratum = $this->integerOption('expected-human-per-stratum', 1, 100);
        $ccdId = $this->nullableIntegerOption('ccd-id', 1);
        $afterId = $this->integerOption('after-id', 0, PHP_INT_MAX);
        $limit = $this->integerOption('limit', 1, 100);
        $expectedTotal = $this->integerOption('expected-total', 1, 100_000);
        $expectedInputs = $this->integerOption('expected-inputs', 1, 100);
        $expectedAqp4 = $this->integerOption('expected-aqp4', 0, 100_000);
        $expectedAqp5 = $this->integerOption('expected-aqp5', 0, 100_000);
        $apply = (bool) $this->option('apply');

        if ($inputs === [] || $afterId === null || $limit === null || $expectedTotal === null
            || $expectedInputs === null || $expectedAqp4 === null || $expectedAqp5 === null
            || $expectedHumanTotal === null || $expectedHumanAqp4 === null || $expectedHumanAqp5 === null
            || $expectedHumanPerStratum === null
            || ($cosecha !== null && ! in_array($cosecha, ['AQP4', 'AQP5'], true))) {
            $this->error('Los CSV, cosecha o límites del proceso AQP son inválidos.');

            return null;
        }

        if (($this->option('ccd-id') !== null && $ccdId === null)
            || $humanReview !== basename($humanReview)
            || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/D', $humanReview)
            || ! preg_match('/^[a-f0-9]{64}$/D', $humanReviewSha256)) {
            $this->error('El archivo, huella o CCD de revisión humana es inválido.');

            return null;
        }

        if ($expectedAqp4 + $expectedAqp5 !== $expectedTotal) {
            $this->error('--expected-aqp4 más --expected-aqp5 debe ser igual a --expected-total.');

            return null;
        }

        if ($expectedHumanAqp4 + $expectedHumanAqp5 !== $expectedHumanTotal
            || $expectedHumanPerStratum * 4 !== $expectedHumanAqp4
            || $expectedHumanPerStratum * 4 !== $expectedHumanAqp5) {
            $this->error('La distribución humana esperada debe coincidir con ambas cosechas y sus cuatro cuartiles.');

            return null;
        }

        if ($apply
            && (! hash_equals(self::APPLY_AUTHORIZATION, trim((string) $this->option('authorization')))
                || ! hash_equals(self::APPLY_CONFIRMATION, trim((string) $this->option('confirmation'))))) {
            $this->error('--apply exige ambas autorizaciones AQP exactas.');

            return null;
        }

        if (! $apply && ($this->option('authorization') !== null || $this->option('confirmation') !== null)) {
            $this->error('--authorization y --confirmation sólo pueden utilizarse con --apply.');

            return null;
        }

        $requestedOutput = trim((string) $this->option('output'));
        $output = $requestedOutput !== ''
            ? $requestedOutput
            : 'ccd-associate-aqp-audited-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.csv';

        if ($output !== basename($output) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/D', $output)) {
            $this->error('--output debe ser un nombre CSV simple, sin directorios.');

            return null;
        }

        return [
            'inputs' => $inputs,
            'human_review' => $humanReview,
            'human_review_sha256' => $humanReviewSha256,
            'expected_human_total' => $expectedHumanTotal,
            'expected_human_aqp4' => $expectedHumanAqp4,
            'expected_human_aqp5' => $expectedHumanAqp5,
            'expected_human_per_stratum' => $expectedHumanPerStratum,
            'ccd_id' => $ccdId,
            'cosecha' => $cosecha,
            'after_id' => $afterId,
            'limit' => $limit,
            'expected_total' => $expectedTotal,
            'expected_inputs' => $expectedInputs,
            'expected_aqp4' => $expectedAqp4,
            'expected_aqp5' => $expectedAqp5,
            'apply' => $apply,
            'output' => $output,
        ];
    }

    private function integerOption(string $name, int $minimum, int $maximum): ?int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT);

        return $value !== false && $value >= $minimum && $value <= $maximum ? $value : null;
    }

    private function nullableIntegerOption(string $name, int $minimum): ?int
    {
        $raw = $this->option($name);

        if ($raw === null || $raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);

        return $value !== false && $value >= $minimum ? $value : null;
    }

    /** @return array{status:string,source:string,account_id:null,operacion:null,evidence:string,method:string,reason:string,dni_evidence:string,document_sha256:null} */
    private function technicalResult(string $reason): array
    {
        return [
            'status' => 'error',
            'source' => 'no_disponible',
            'account_id' => null,
            'operacion' => null,
            'evidence' => 'control_aqp_v4',
            'method' => 'operacion_pdf_aqp_auditada_v3',
            'reason' => $reason,
            'dni_evidence' => 'no_evaluada',
            'document_sha256' => null,
        ];
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
