<?php

namespace App\Console\Commands;

use App\Models\CcdAssociationRun;
use App\Models\CcdCliente;
use App\Models\CcdClienteCuenta;
use App\Services\Ccd\CcdAutoAssociationApplyGuard;
use App\Services\Ccd\CcdBulkProcessLock;
use App\Services\Ccd\CcdVerifiedAssociationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AssociateBulkCcd extends Command
{
    private const APPLY_AUTHORIZATION = 'CONFIRMAR_ASOCIACION_AUTOMATICA';

    protected $signature = 'ccd:associate-bulk
        {--batch-size=100 : CCD leídas por consulta, entre 1 y 500}
        {--max-documents=1000 : Máximo por invocación, entre 1 y 10000}
        {--from-id= : ID inicial inclusivo para una ejecución nueva}
        {--to-id= : ID final inclusivo}
        {--cosecha= : Cosecha exacta opcional}
        {--resume= : UUID de ejecución que se reanudará}
        {--apply : Persiste sólo confirmaciones verificadas}
        {--authorization= : Requerido junto con --apply}
        {--audit-confirmed : Relee PDF ya confirmados para auditar conflictos}
        {--max-seconds=240 : Duración máxima aproximada de esta invocación}
        {--max-memory-mb=192 : Detiene el lote antes de exceder este uso}
        {--max-errors=25 : Detiene el tramo al alcanzar errores técnicos}
        {--output= : Nombre CSV privado de este tramo, sin ruta}';

    protected $description = 'Procesa CCD verificadas por lotes reanudables, en simulación por defecto';

    public function handle(
        CcdVerifiedAssociationService $associations,
        CcdAutoAssociationApplyGuard $applyGuard,
        CcdBulkProcessLock $processLock,
    ): int {
        $options = $this->validatedOptions();

        if ($options === null) {
            return self::FAILURE;
        }

        if ($options['apply'] && ($reason = $applyGuard->rejectionReason(DB::connection())) !== null) {
            $this->error($reason);

            return self::FAILURE;
        }

        $run = null;

        try {
            if (! $processLock->acquire()) {
                $this->error('Ya existe otro proceso ccd:associate-bulk en ejecución. No se procesó ningún documento.');

                return self::FAILURE;
            }

            $run = $this->resolveRun($options);

            if ($run === null) {
                return self::FAILURE;
            }

            if ($run->status === CcdAssociationRun::STATUS_COMPLETED) {
                $this->info("La ejecución {$run->execution_id} ya está completa; no se procesó ningún documento.");
                $this->renderSummary($run, 0, 0, 0);

                return self::SUCCESS;
            }

            return $this->process($run, $options, $associations);
        } catch (Throwable $exception) {
            Log::error('CCD bulk association failed.', ['exception_class' => $exception::class]);

            if ($run instanceof CcdAssociationRun) {
                $run->forceFill([
                    'status' => CcdAssociationRun::STATUS_FAILED,
                    'stop_reason' => 'error_critico',
                    'last_heartbeat_at' => now(),
                    'finished_at' => null,
                ])->save();
            }

            $this->error('La ejecución masiva se detuvo por un error crítico. Revise el log privado y reanude desde el checkpoint.');

            return self::FAILURE;
        } finally {
            $processLock->release();
        }
    }

    /**
     * @param  array{batch_size:int,max_documents:int,from_id:?int,to_id:?int,cosecha:?string,resume:?string,apply:bool,audit_confirmed:bool,max_seconds:int,max_memory_bytes:int,max_errors:int,output:?string}  $options
     */
    private function process(CcdAssociationRun $run, array $options, CcdVerifiedAssociationService $associations): int
    {
        $disk = Storage::disk((string) config('ccd.private_disk'));
        $report = $options['output'] ?? sprintf(
            'ccd-associate-bulk-%s-%s-%s.csv',
            $run->execution_id,
            now()->format('Ymd-His'),
            Str::lower(Str::random(6)),
        );
        $output = 'audits/'.$report;

        if ($disk->exists($output)) {
            $run->forceFill([
                'status' => CcdAssociationRun::STATUS_PAUSED,
                'stop_reason' => 'reporte_ya_existente',
                'last_heartbeat_at' => now(),
            ])->save();
            $this->error('El CSV privado indicado ya existe; use otro nombre para no sobrescribirlo.');

            return self::FAILURE;
        }

        $temporary = 'audits/.tmp-'.Str::uuid().'.csv';
        $path = $disk->path($temporary);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar el directorio privado de auditoría CCD.');
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el CSV privado de la ejecución masiva.');
        }

        fputcsv($handle, [
            'execution_id',
            'ccd_id',
            'cliente_cuenta_id',
            'operacion',
            'fuente',
            'resultado',
            'motivo',
            'evidencia',
            'evidencia_dni',
            'metodo_verificacion',
            'modo',
            'milisegundos',
        ]);

        $started = hrtime(true);
        $checkpointAt = $started;
        $processedThisInvocation = 0;
        $counts = $this->emptyCounts();
        $stopReason = null;
        $failed = false;
        $queryCount = 0;
        $peakMemory = memory_get_usage(true);

        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $run->forceFill([
            'status' => CcdAssociationRun::STATUS_RUNNING,
            'stop_reason' => null,
            'finished_at' => null,
            'last_heartbeat_at' => now(),
            'batch_size' => $options['batch_size'],
        ])->save();

        try {
            while ($processedThisInvocation < $options['max_documents']) {
                if ($this->elapsedSeconds($started) >= $options['max_seconds']) {
                    $stopReason = 'limite_tiempo';
                    break;
                }

                if (memory_get_usage(true) >= $options['max_memory_bytes']) {
                    $stopReason = 'limite_memoria';
                    break;
                }

                $limit = min($options['batch_size'], $options['max_documents'] - $processedThisInvocation);
                $rows = $this->baseQuery($run)
                    ->where('ccd_clientes.id', '>', $run->last_ccd_id)
                    ->limit($limit)
                    ->get();

                if ($rows->isEmpty()) {
                    $stopReason = 'rango_completado';
                    break;
                }

                $confirmed = $options['audit_confirmed']
                    ? collect()
                    : CcdClienteCuenta::query()
                        ->whereIn('ccd_cliente_id', $rows->pluck('id'))
                        ->where('estado', CcdClienteCuenta::ESTADO_CONFIRMADA)
                        ->orderBy('id')
                        ->get(['ccd_cliente_id', 'cliente_cuenta_id'])
                        ->keyBy('ccd_cliente_id');

                foreach ($rows as $ccd) {
                    $documentStarted = hrtime(true);

                    if ($confirmedLink = $confirmed->get($ccd->id)) {
                        $result = [
                            'status' => 'ya_confirmada',
                            'source' => 'omitida',
                            'account_id' => (int) $confirmedLink->cliente_cuenta_id,
                            'operacion' => null,
                            'evidence' => 'confirmacion_preexistente',
                            'dni_evidence' => 'no_releida',
                            'method' => 'preclasificacion_lote',
                            'reason' => 'omitida_confirmacion_preexistente_sin_relectura',
                        ];
                    } else {
                        try {
                            $result = $options['apply']
                                ? $associations->apply($ccd)
                                : $associations->analyze($ccd);
                        } catch (Throwable $exception) {
                            Log::error('CCD bulk document failed.', [
                                'exception_class' => $exception::class,
                                'ccd_id' => (int) $ccd->id,
                                'execution_id' => $run->execution_id,
                            ]);
                            $result = [
                                'status' => 'error',
                                'source' => 'no_disponible',
                                'account_id' => null,
                                'operacion' => null,
                                'evidence' => 'procesamiento_tecnico',
                                'dni_evidence' => 'no_evaluada',
                                'method' => 'operacion_pdf_automatica',
                                'reason' => 'procesamiento_tecnico',
                            ];
                        }
                    }

                    $this->incrementCount($counts, (string) $result['status']);
                    $processedThisInvocation++;
                    $run->last_ccd_id = (int) $ccd->id;
                    $peakMemory = max($peakMemory, memory_get_usage(true), memory_get_peak_usage(true));

                    fputcsv($handle, array_map([$this, 'safeCsv'], [
                        $run->execution_id,
                        $ccd->id,
                        $result['account_id'],
                        $result['operacion'],
                        $result['source'],
                        $result['status'],
                        $result['reason'],
                        $result['evidence'],
                        $result['dni_evidence'],
                        $result['method'],
                        $options['apply'] ? 'aplicacion' : 'simulacion',
                        round((hrtime(true) - $documentStarted) / 1_000_000, 3),
                    ]));

                    if ($counts['errors'] >= $options['max_errors']) {
                        $stopReason = 'limite_errores';
                        $failed = true;
                        break 2;
                    }

                    if ($this->elapsedSeconds($started) >= $options['max_seconds']) {
                        $stopReason = 'limite_tiempo';
                        break 2;
                    }

                    if (memory_get_usage(true) >= $options['max_memory_bytes']) {
                        $stopReason = 'limite_memoria';
                        break 2;
                    }
                }

                $this->checkpoint($run, $counts, $checkpointAt, $queryCount, $peakMemory, null);
                gc_collect_cycles();
            }

            if ($stopReason === null) {
                $stopReason = $this->hasRemaining($run) ? 'limite_documentos' : 'rango_completado';
            } elseif ($stopReason === 'limite_tiempo' || $stopReason === 'limite_memoria') {
                $failed = false;
            }

            if ($stopReason !== 'rango_completado' && ! $failed && ! $this->hasRemaining($run)) {
                $stopReason = 'rango_completado';
            }
        } finally {
            fclose($handle);

            if (! $disk->move($temporary, $output)) {
                $disk->delete($temporary);
                throw new \RuntimeException('No se pudo publicar el CSV privado de la ejecución masiva.');
            }
        }

        $completed = $stopReason === 'rango_completado';
        $run->last_report_path = $output;
        $this->checkpoint(
            $run,
            $counts,
            $checkpointAt,
            $queryCount,
            $peakMemory,
            $failed ? CcdAssociationRun::STATUS_FAILED : ($completed ? CcdAssociationRun::STATUS_COMPLETED : CcdAssociationRun::STATUS_PAUSED),
            $stopReason,
        );

        $this->info("Ejecución: {$run->execution_id}");
        $this->info("CSV privado: {$output}");
        $this->renderSummary($run, $processedThisInvocation, $queryCount, $peakMemory);
        $this->comment($completed
            ? 'Rango completado. No quedan CCD para los filtros de esta ejecución.'
            : "Ejecución acotada detenida por {$stopReason}. Reanude con --resume={$run->execution_id}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string,int> $counts */
    private function checkpoint(
        CcdAssociationRun $run,
        array &$counts,
        int &$checkpointAt,
        int $queryCount,
        int $peakMemory,
        ?string $status,
        ?string $stopReason = null,
    ): void {
        $now = hrtime(true);
        $elapsed = ($now - $checkpointAt) / 1_000_000_000;
        $checkpointAt = $now;

        foreach ($counts as $column => $amount) {
            if ($amount > 0) {
                $run->{$column} = (int) $run->{$column} + $amount;
                $counts[$column] = 0;
            }
        }

        $run->elapsed_seconds = (float) $run->elapsed_seconds + $elapsed;
        $run->documents_per_second = $run->elapsed_seconds > 0
            ? round($run->processed / $run->elapsed_seconds, 4)
            : 0;
        $run->last_query_count = $queryCount;
        $run->peak_memory_bytes = max((int) $run->peak_memory_bytes, $peakMemory);
        $run->last_heartbeat_at = now();
        $run->stop_reason = $stopReason;

        if ($status !== null) {
            $run->status = $status;
            $run->finished_at = $status === CcdAssociationRun::STATUS_COMPLETED ? now() : null;
        }

        $run->save();
    }

    private function hasRemaining(CcdAssociationRun $run): bool
    {
        return $this->baseQuery($run)
            ->where('ccd_clientes.id', '>', $run->last_ccd_id)
            ->exists();
    }

    private function baseQuery(CcdAssociationRun $run): Builder
    {
        $filters = (array) $run->filters;
        $query = CcdCliente::query()
            ->with('archivo:id,disk,path,sha256')
            ->select(['id', 'numdoc', 'pdf', 'cosecha', 'ccd_archivo_id'])
            ->orderBy('ccd_clientes.id');

        if (($filters['to_id'] ?? null) !== null) {
            $query->where('ccd_clientes.id', '<=', (int) $filters['to_id']);
        }

        if (($filters['cosecha'] ?? null) !== null) {
            $query->where('cosecha', (string) $filters['cosecha']);
        }

        return $query;
    }

    /**
     * @param  array{batch_size:int,max_documents:int,from_id:?int,to_id:?int,cosecha:?string,resume:?string,apply:bool,audit_confirmed:bool,max_seconds:int,max_memory_bytes:int,max_errors:int,output:?string}  $options
     */
    private function resolveRun(array &$options): ?CcdAssociationRun
    {
        if ($options['resume'] !== null) {
            $run = CcdAssociationRun::query()->where('execution_id', $options['resume'])->first();

            if ($run === null) {
                $this->error('No existe una ejecución con el UUID indicado en --resume.');

                return null;
            }

            if (($run->mode === 'aplicacion') !== $options['apply']) {
                $this->error('El modo de --resume debe coincidir con el modo original de la ejecución.');

                return null;
            }

            $stored = (array) $run->filters;
            $options['from_id'] = isset($stored['from_id']) ? (int) $stored['from_id'] : null;
            $options['to_id'] = isset($stored['to_id']) ? (int) $stored['to_id'] : null;
            $options['cosecha'] = $stored['cosecha'] ?? null;
            $options['audit_confirmed'] = (bool) ($stored['audit_confirmed'] ?? false);

            return $run;
        }

        $executionId = (string) Str::uuid();
        $initialCursor = max(0, ($options['from_id'] ?? 1) - 1);

        return CcdAssociationRun::query()->create([
            'execution_id' => $executionId,
            'mode' => $options['apply'] ? 'aplicacion' : 'simulacion',
            'status' => CcdAssociationRun::STATUS_RUNNING,
            'filters' => [
                'from_id' => $options['from_id'],
                'to_id' => $options['to_id'],
                'cosecha' => $options['cosecha'],
                'audit_confirmed' => $options['audit_confirmed'],
            ],
            'batch_size' => $options['batch_size'],
            'last_ccd_id' => $initialCursor,
            'started_at' => now(),
            'last_heartbeat_at' => now(),
        ]);
    }

    /** @return array{batch_size:int,max_documents:int,from_id:?int,to_id:?int,cosecha:?string,resume:?string,apply:bool,audit_confirmed:bool,max_seconds:int,max_memory_bytes:int,max_errors:int,output:?string}|null */
    private function validatedOptions(): ?array
    {
        $batchSize = $this->integerOption('batch-size', 1, 500);
        $maxDocuments = $this->integerOption('max-documents', 1, 10_000);
        $fromId = $this->nullableIntegerOption('from-id', 1);
        $toId = $this->nullableIntegerOption('to-id', 1);
        $maxSeconds = $this->integerOption('max-seconds', 10, 3_600);
        $maxMemoryMb = $this->integerOption('max-memory-mb', 64, 1_024);
        $maxErrors = $this->integerOption('max-errors', 1, 1_000);

        if ($batchSize === null || $maxDocuments === null || $maxSeconds === null || $maxMemoryMb === null || $maxErrors === null) {
            $this->error('Los límites numéricos del proceso masivo son inválidos.');

            return null;
        }

        if (($this->option('from-id') !== null && $fromId === null)
            || ($this->option('to-id') !== null && $toId === null)
            || ($fromId !== null && $toId !== null && $fromId > $toId)) {
            $this->error('--from-id y --to-id deben ser enteros positivos y formar un rango válido.');

            return null;
        }

        $apply = (bool) $this->option('apply');

        if ($apply && ! hash_equals(self::APPLY_AUTHORIZATION, trim((string) $this->option('authorization')))) {
            $this->error('--apply exige --authorization='.self::APPLY_AUTHORIZATION.'.');

            return null;
        }

        if (! $apply && $this->option('authorization') !== null) {
            $this->error('--authorization sólo puede utilizarse junto con --apply.');

            return null;
        }

        $resume = trim((string) $this->option('resume')) ?: null;

        if ($resume !== null && ! Str::isUuid($resume)) {
            $this->error('--resume debe contener un UUID válido.');

            return null;
        }

        if ($resume !== null && ($this->option('from-id') !== null || $this->option('to-id') !== null || $this->option('cosecha') !== null || $this->option('audit-confirmed'))) {
            $this->error('Al reanudar, los filtros originales se recuperan del checkpoint y no deben repetirse.');

            return null;
        }

        $cosecha = strtoupper(trim((string) $this->option('cosecha'))) ?: null;

        if ($cosecha !== null && ! preg_match('/^[A-Z0-9_-]{1,100}$/D', $cosecha)) {
            $this->error('--cosecha contiene un valor inválido.');

            return null;
        }

        $output = trim((string) $this->option('output')) ?: null;

        if ($output !== null && ($output !== basename($output) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/D', $output))) {
            $this->error('--output debe ser un nombre CSV simple, sin directorios.');

            return null;
        }

        return [
            'batch_size' => $batchSize,
            'max_documents' => $maxDocuments,
            'from_id' => $fromId,
            'to_id' => $toId,
            'cosecha' => $cosecha,
            'resume' => $resume,
            'apply' => $apply,
            'audit_confirmed' => (bool) $this->option('audit-confirmed'),
            'max_seconds' => $maxSeconds,
            'max_memory_bytes' => $maxMemoryMb * 1024 * 1024,
            'max_errors' => $maxErrors,
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

    /** @return array{processed:int,eligible:int,confirmed:int,already_confirmed:int,not_eligible:int,reviews:int,conflicts:int,errors:int} */
    private function emptyCounts(): array
    {
        return [
            'processed' => 0,
            'eligible' => 0,
            'confirmed' => 0,
            'already_confirmed' => 0,
            'not_eligible' => 0,
            'reviews' => 0,
            'conflicts' => 0,
            'errors' => 0,
        ];
    }

    /** @param array<string,int> $counts */
    private function incrementCount(array &$counts, string $status): void
    {
        $counts['processed']++;
        $column = match ($status) {
            'elegible' => 'eligible',
            'confirmada' => 'confirmed',
            'ya_confirmada' => 'already_confirmed',
            'no_elegible' => 'not_eligible',
            'revision' => 'reviews',
            'conflicto' => 'conflicts',
            default => 'errors',
        };
        $counts[$column]++;
    }

    private function renderSummary(CcdAssociationRun $run, int $invocationProcessed, int $queryCount, int $peakMemory): void
    {
        $this->table(['Métrica', 'Valor'], [
            ['Estado', $run->status],
            ['Último CCD ID', $run->last_ccd_id],
            ['Procesados en este tramo', $invocationProcessed],
            ['Procesados acumulados', $run->processed],
            ['Elegibles', $run->eligible],
            ['Confirmadas', $run->confirmed],
            ['Ya confirmadas', $run->already_confirmed],
            ['No elegibles', $run->not_eligible],
            ['Revisiones', $run->reviews],
            ['Conflictos', $run->conflicts],
            ['Errores', $run->errors],
            ['Documentos/segundo', number_format((float) $run->documents_per_second, 4, '.', '')],
            ['Consultas SQL del tramo', $queryCount],
            ['Memoria pico del proceso', $this->formatBytes($peakMemory)],
            ['Motivo de parada', $run->stop_reason ?? '-'],
        ]);
    }

    private function elapsedSeconds(int $started): float
    {
        return (hrtime(true) - $started) / 1_000_000_000;
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }

    private function formatBytes(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 2, '.', '').' MB';
    }
}
