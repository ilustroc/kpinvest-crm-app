<?php

namespace App\Services\Cartera;

use App\Services\Panel\PanelDashboardService;
use App\Traits\CsvImportTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataReconciliationService
{
    use CsvImportTrait;

    public const BATCH_SIZE = 500;
    public const IMPORT_LOCK_TIMEOUT = 120; // seconds

    /**
     * Known bank and financial entity aliases to canonical names.
     */
    protected static array $entityAliases = [
        'BCP' => 'BCP',
        'BANCO DE CREDITO' => 'BCP',
        'BANCO DE CREDITO DEL PERU' => 'BCP',
        'CREDITO' => 'BCP',
        'BBVA' => 'BBVA',
        'BANCO BBVA' => 'BBVA',
        'BBVA CONTINENTAL' => 'BBVA',
        'CONTINENTAL' => 'BBVA',
        'FINANCIERA CONFIANZA' => 'FINANCIERA CONFIANZA',
        'CONFIANZA' => 'FINANCIERA CONFIANZA',
        'BANCO PICHINCHA' => 'BANCO PICHINCHA',
        'PICHINCHA' => 'BANCO PICHINCHA',
        'FINANCIERA PICHINCHA' => 'BANCO PICHINCHA',
        'INTERBANK' => 'INTERBANK',
        'BANCO INTERNACIONAL' => 'INTERBANK',
        'SCOTIABANK' => 'SCOTIABANK',
        'SCOTIABANK PERU' => 'SCOTIABANK',
        'WANDOO' => 'WANDOO',
        'WANDOO FINANCE' => 'WANDOO',
    ];

    /**
     * Normalize entity names to canonical financial names.
     */
    public function normalizeEntidad(?string $entidad): string
    {
        if ($entidad === null || trim($entidad) === '') {
            return 'DESCONOCIDO';
        }

        $clean = mb_strtoupper(trim($entidad), 'UTF-8');
        $clean = preg_replace('/\s+/', ' ', $clean);

        return self::$entityAliases[$clean] ?? $clean;
    }

    /**
     * Compute current database fingerprint to detect stale previews before execution.
     */
    public function computeDatabaseFingerprint(): string
    {
        $count = DB::table('clientes_cuentas')->count();
        $maxUpdated = (string) (DB::table('clientes_cuentas')->max('updated_at') ?? '');
        $version = (string) Cache::get(PanelDashboardService::CACHE_VERSION_KEY, 1);
        return md5("{$count}:{$maxUpdated}:{$version}");
    }

    /**
     * Generate diagnostic dry-run preview without writing to database.
     */
    public function preview(string $filepath): array
    {
        return $this->processFile($filepath, isPreview: true);
    }

    /**
     * Execute 3-way reconciliation with transactional safety.
     */
    public function execute(string $filepath, ?string $expectedFingerprint = null): array
    {
        return $this->processFile($filepath, isPreview: false, expectedFingerprint: $expectedFingerprint);
    }

    /**
     * Core processing engine for preview and execution.
     */
    protected function processFile(string $filepath, bool $isPreview, ?string $expectedFingerprint = null): array
    {
        $fh = @fopen($filepath, 'r');
        if (!$fh) {
            return [
                0,
                0,
                ['No se pudo abrir el archivo para lectura.'],
                [],
            ];
        }

        $first = fgets($fh);
        if ($first === false) {
            fclose($fh);
            return [
                0,
                0,
                ['El archivo está vacío.'],
                [],
            ];
        }

        $del = $this->detectDelimiter($first);
        rewind($fh);

        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) {
            fclose($fh);
            return [
                0,
                0,
                ['El archivo carece de encabezados.'],
                [],
            ];
        }

        $map = $this->mapHeaders($headers);

        DB::connection()->disableQueryLog();
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $now = Carbon::now()->toDateTimeString();
        $rowNum = 1;

        $groupedByFileIdentity = [];
        $quarantineDetails = [];
        $financialChanges = [];
        $errors = [];
        $alerts = [];

        $countQuarantine = 0;

        // Stage 1: File reading, string sanitization, financial format validation
        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            $rowNum++;
            $data = $this->mapRowToData($row, $map, $now, $rowNum);

            // Validation: numdoc and operacion must not be empty
            if (empty($data['numdoc']) || empty($data['operacion'])) {
                $countQuarantine++;
                $quarantineDetails[] = [
                    'fila' => $rowNum,
                    'numdoc' => (string) ($data['numdoc'] ?? ''),
                    'operacion' => (string) ($data['operacion'] ?? ''),
                    'motivo' => 'IDENTIFICADOR_FALTANTE (DNI u operación vacíos)',
                ];
                continue;
            }

            // Financial validation error (malformed text, ambiguous format, or decimal overflow)
            if (!empty($data['_monto_error'])) {
                $countQuarantine++;
                $quarantineDetails[] = [
                    'fila' => $rowNum,
                    'numdoc' => $data['numdoc'],
                    'operacion' => $data['operacion'],
                    'motivo' => $data['_monto_error'],
                ];
                continue;
            }

            // Monetary validation: negative debt is strictly prohibited in cartera accounts
            if (($data['deuda_capital'] !== null && $data['deuda_capital'] < 0) ||
                ($data['deuda_total'] !== null && $data['deuda_total'] < 0)) {
                $countQuarantine++;
                $quarantineDetails[] = [
                    'fila' => $rowNum,
                    'numdoc' => $data['numdoc'],
                    'operacion' => $data['operacion'],
                    'motivo' => 'MONTO_INVALIDO (Deuda negativa detectada)',
                ];
                continue;
            }

            $normEntity = $this->normalizeEntidad($data['entidad'] ?? null);
            $data['_norm_entidad'] = $normEntity;
            $fileIdentityKey = "{$normEntity}::{$data['numdoc']}::{$data['operacion']}";

            $groupedByFileIdentity[$fileIdentityKey][] = $data;
        }
        fclose($fh);

        // Stage 1.5: In-CSV Duplicate Resolution (Identical vs Conflicting)
        $validRows = [];
        $countDuplicates = 0;

        foreach ($groupedByFileIdentity as $fileIdentityKey => $group) {
            if (count($group) === 1) {
                $validRows[] = $group[0];
                continue;
            }

            // Multiple rows share the same identity key in this CSV
            $first = $group[0];
            $hasConflict = false;
            $conflicts = [];

            for ($i = 1; $i < count($group); $i++) {
                $other = $group[$i];
                $diffs = $this->detectCsvRowDifferences($first, $other);
                if (!empty($diffs)) {
                    $hasConflict = true;
                    $conflicts[$other['_fila']] = $diffs;
                }
            }

            if ($hasConflict) {
                // Rule 2 & 3: Conflicting duplicates in CSV (different debts or different products)
                // Retain ALL involved rows in quarantine. Do not arbitrarily pick first or last balance.
                $filasInvolucradas = array_column($group, '_fila');
                $productosInvolucrados = array_values(array_unique(array_filter(array_column($group, 'producto'))));
                $deudasInvolucradas = array_values(array_unique(array_filter(array_column($group, 'deuda_capital'))));

                foreach ($group as $item) {
                    $countQuarantine++;
                    $quarantineDetails[] = [
                        'fila' => $item['_fila'],
                        'numdoc' => $item['numdoc'],
                        'operacion' => $item['operacion'],
                        'entidad' => $item['entidad'] ?? null,
                        'motivo' => "DUPLICADO_CONFLICTIVO_EN_CSV (Conflicto entre filas " . implode(', ', $filasInvolucradas) . ")",
                        'valores_en_conflicto' => [
                            'filas' => $filasInvolucradas,
                            'productos' => $productosInvolucrados,
                            'deudas' => $deudasInvolucradas,
                        ],
                    ];
                }
                $alerts[] = "Filas " . implode(', ', $filasInvolucradas) . ": Retenidas en cuarentena por conflicto de valores/productos para la misma operación {$first['operacion']}.";
            } else {
                // Identical duplicates in CSV: count repeated rows, omit repetitions, process representative row
                $validRows[] = $first;
                $countDuplicates += (count($group) - 1);
                $alerts[] = "Fila {$first['_fila']}: Duplicados idénticos en CSV omitidos (" . (count($group) - 1) . " repeticiones idénticas descartadas).";
            }
        }

        // Database Fingerprint Verification (Avoid stale preview execution)
        $currentDbFingerprint = $this->computeDatabaseFingerprint();
        if (!$isPreview && $expectedFingerprint !== null && $expectedFingerprint !== $currentDbFingerprint) {
            $alerts[] = "ADVERTENCIA: La base de datos fue modificada entre la vista previa y la ejecución. Se reevalúa el estado actual.";
        }

        // Concurrency lock for executions
        $fpLock = null;
        if (!$isPreview) {
            $lockFile = storage_path('framework/cache/cartera_import.lock');
            $dir = dirname($lockFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $fpLock = @fopen($lockFile, 'c+');
            if ($fpLock && !flock($fpLock, LOCK_EX | LOCK_NB)) {
                return [
                    0,
                    count($validRows) + $countQuarantine + $countDuplicates,
                    ['Existe otro proceso de importación de cartera en ejecución.'],
                    [],
                ];
            }
        }

        $countInsert = 0;
        $countUpdate = 0;
        $countUnchanged = 0;

        try {
            // Stages 2 & 3: Identity Resolution & 3-Way Reconciliation
            // We group valid rows into batches of BATCH_SIZE (500)
            $chunks = array_chunk($validRows, self::BATCH_SIZE);

            // Whole-File Transaction: Ensures all-or-nothing atomicity across all batches.
            // If any batch fails mid-process, the entire file rolls back cleanly.
            if (!$isPreview) {
                DB::beginTransaction();
            }

            foreach ($chunks as $chunk) {
                $dnis = array_unique(array_column($chunk, 'numdoc'));
                $ops = array_unique(array_column($chunk, 'operacion'));

                // Fetch candidates from DB
                $candidates = DB::table('clientes_cuentas')
                    ->whereIn('numdoc', $dnis)
                    ->whereIn('operacion', $ops)
                    ->get()
                    ->groupBy(function ($row) {
                        return "{$row->numdoc}::{$row->operacion}";
                    });

                $batchInserts = [];
                $batchUpdates = [];

                foreach ($chunk as $row) {
                    $lookupKey = "{$row['numdoc']}::{$row['operacion']}";
                    $matches = $candidates->get($lookupKey, collect());

                    if ($matches->isEmpty()) {
                        // VÍA 1: INSERT (Cuenta nueva inequívoca)
                        $countInsert++;
                        $batchInserts[] = $this->prepareInsertPayload($row);
                    } elseif ($matches->count() === 1) {
                        // VÍA 2: UPDATE o SIN_CAMBIOS (Cuenta existente inequívoca)
                        $target = $matches->first();

                        // Discrepancy check: client name homonymy risk
                        if (!empty($target->nombre) && !empty($row['nombre'])) {
                            if ($this->hasSevereNameDiscrepancy($target->nombre, $row['nombre'])) {
                                $countQuarantine++;
                                $quarantineDetails[] = [
                                    'fila' => $row['_fila'],
                                    'numdoc' => $row['numdoc'],
                                    'operacion' => $row['operacion'],
                                    'motivo' => "DISCREPANCIA_TITULAR (BD: {$target->nombre} vs CSV: {$row['nombre']})",
                                ];
                                continue;
                            }
                        }

                        // Check if fields actually changed
                        $diffs = $this->detectFieldDifferences($target, $row);
                        if (empty($diffs)) {
                            // SIN_CAMBIOS: Identical reimport. Do NOT execute SQL UPDATE, do NOT touch updated_at!
                            $countUnchanged++;
                        } else {
                            // UPDATE: Real financial or data modification
                            $countUpdate++;
                            foreach ($diffs as $field => $change) {
                                $financialChanges[] = [
                                    'fila' => $row['_fila'],
                                    'numdoc' => $row['numdoc'],
                                    'operacion' => $row['operacion'],
                                    'campo' => $field,
                                    'anterior' => $change['old'],
                                    'propuesto' => $change['new'],
                                ];
                            }
                            $batchUpdates[] = [
                                'id' => $target->id,
                                'payload' => $this->prepareUpdatePayload($row),
                            ];
                        }
                    } else {
                        // Multiple DB matches exist for same (numdoc, operacion)
                        // Rule 3: Do not merge distinct legitimate obligations. If cannot be resolved with certainty -> quarantine.
                        $countQuarantine++;
                        $quarantineDetails[] = [
                            'fila' => $row['_fila'],
                            'numdoc' => $row['numdoc'],
                            'operacion' => $row['operacion'],
                            'motivo' => "AMBIGUEDAD_BD_MULTIPLE_OBLIGACION ({$matches->count()} cuentas ambiguas u obligaciones legítimas en BD para la misma operación)",
                        ];
                    }
                }

                // If not in preview mode, execute batch writes within the file transaction
                if (!$isPreview) {
                    if (!empty($batchInserts)) {
                        DB::table('clientes_cuentas')->insert($batchInserts);
                    }

                    if (!empty($batchUpdates)) {
                        foreach ($batchUpdates as $up) {
                            DB::table('clientes_cuentas')
                                ->where('id', $up['id'])
                                ->update($up['payload']);
                        }
                    }
                }
            }

            // Commit the entire file transaction
            if (!$isPreview) {
                DB::commit();

                // Invalidate dashboard cache on successful execution commit
                if ($countInsert > 0 || $countUpdate > 0) {
                    PanelDashboardService::invalidate();
                }
            }

        } catch (\Throwable $e) {
            if (!$isPreview && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $errors[] = "Error crítico en proceso de reconciliación: " . $e->getMessage();
            Log::error('DATA_IMPORT_RECONCILIATION_ERROR', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            if (!$isPreview && isset($fpLock) && $fpLock) {
                flock($fpLock, LOCK_UN);
                fclose($fpLock);
            }
        }

        $totalProcessed = $countInsert + $countUpdate + $countUnchanged;
        $totalReceived = $totalProcessed + $countQuarantine + $countDuplicates;

        $summary = [
            'total_filas' => $totalReceived,
            'total_recibidos' => $totalReceived,
            'insert' => $countInsert,
            'nuevas_cuentas' => $countInsert,
            'update' => $countUpdate,
            'cuentas_actualizadas' => $countUpdate,
            'sin_cambios' => $countUnchanged,
            'registros_sin_cambios' => $countUnchanged,
            'cuarentena' => $countQuarantine,
            'retenidos_cuarentena' => $countQuarantine,
            'duplicados' => $countDuplicates,
            'duplicados_en_csv' => $countDuplicates,
            'errores_validacion' => count($quarantineDetails),
            'detalles_cuarentena' => $quarantineDetails,
            'cambios_financieros' => $financialChanges,
            'errores' => $errors,
            'db_fingerprint' => $currentDbFingerprint,
            'alertas' => $alerts,
        ];

        return [
            $totalProcessed,
            $countQuarantine + $countDuplicates,
            $errors,
            $summary,
        ];
    }

    /**
     * Map CSV row to associative data array while strictly preserving strings.
     */
    protected function mapRowToData(array $row, array $map, string $now, int $rowNum): array
    {
        $data = [
            'created_at' => $now,
            'updated_at' => $now,
            '_fila' => $rowNum,
            '_monto_error' => null,
        ];

        foreach ($row as $i => $val) {
            $key = $map[$i] ?? null;
            if (!$key) continue;

            $v = $this->toUtf8($val);

            switch ($key) {
                case 'NUMDOC':        $data['numdoc']        = $v !== null ? (string) $v : null; break;
                case 'OPERACION':     $data['operacion']     = $v !== null ? (string) $v : null; break;
                case 'CUENTA':        $data['cuenta']        = $v !== null ? (string) $v : null; break;
                case 'NOMBRE':        $data['nombre']        = $v; break;
                case 'PRODUCTO':      $data['producto']      = $v; break;
                case 'DPTO':          $data['dpto']          = $v; break;
                case 'PROVINCIA':     $data['provincia']     = $v; break;
                case 'DISTRITO':      $data['distrito']      = $v; break;
                case 'DIRECCION':     $data['direccion']     = $v; break;
                case 'ENTIDAD':       $data['entidad']       = $v; break;
                case 'COSECHA':       $data['cosecha']       = $v; break;
                case 'MONEDA':        $data['moneda']        = $v; break;
                case 'FECHA_COMPRA':  $data['fecha_compra']  = $this->parseDate($v); break;
                case 'FECHA_CASTIGO': $data['fecha_castigo'] = $this->parseDate($v); break;

                case 'DEUDA_CAPITAL':
                    $res = $this->parseFinancialAmount($v);
                    if (!$res['valid']) {
                        $data['_monto_error'] = "DEUDA_CAPITAL: " . $res['error'];
                    } else {
                        $data['deuda_capital'] = $res['value'] !== null ? (float) $res['value'] : null;
                    }
                    break;

                case 'INTERES':
                    $res = $this->parseFinancialAmount($v);
                    if (!$res['valid']) {
                        $data['_monto_error'] = "INTERES: " . $res['error'];
                    } else {
                        $data['interes'] = $res['value'] !== null ? (float) $res['value'] : null;
                    }
                    break;

                case 'DEUDA_TOTAL':
                    $res = $this->parseFinancialAmount($v);
                    if (!$res['valid']) {
                        $data['_monto_error'] = "DEUDA_TOTAL: " . $res['error'];
                    } else {
                        $data['deuda_total'] = $res['value'] !== null ? (float) $res['value'] : null;
                    }
                    break;
            }
        }

        return $data;
    }

    /**
     * Compare two rows from the same CSV to detect conflicts.
     */
    protected function detectCsvRowDifferences(array $a, array $b): array
    {
        $diffs = [];
        $checkFields = ['deuda_capital', 'interes', 'deuda_total', 'producto', 'cuenta', 'cosecha', 'fecha_compra', 'fecha_castigo', 'moneda'];

        foreach ($checkFields as $field) {
            $valA = $a[$field] ?? null;
            $valB = $b[$field] ?? null;

            if (in_array($field, ['deuda_capital', 'interes', 'deuda_total'], true)) {
                $numA = (float) ($valA ?? 0);
                $numB = (float) ($valB ?? 0);
                if (abs($numA - $numB) > 0.005) {
                    $diffs[$field] = ['row_a' => $numA, 'row_b' => $numB];
                }
            } else {
                if ($valA !== null || $valB !== null) {
                    if (trim((string) $valA) !== trim((string) $valB)) {
                        $diffs[$field] = ['row_a' => (string) $valA, 'row_b' => (string) $valB];
                    }
                }
            }
        }

        return $diffs;
    }

    protected function mapHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $norm = $this->toUtf8($h);
            if ($norm !== null) {
                $norm = str_replace("\xEF\xBB\xBF", '', $norm);
                $norm = preg_replace('/^\x{FEFF}/u', '', $norm);
                $norm = trim($norm);
                $norm = strtoupper($norm);
                $norm = preg_replace('/\s+/', '_', $norm);
                $norm = preg_replace('/[^A-Z0-9_]/', '', $norm);
            }
            if ($norm === 'ENTIDAD_FINANCIERA') {
                $norm = 'ENTIDAD';
            }
            $map[$i] = $norm;
        }
        return $map;
    }

    protected function prepareInsertPayload(array $row): array
    {
        $payload = $row;
        unset($payload['_fila'], $payload['_norm_entidad'], $payload['_monto_error']);
        return $payload;
    }

    protected function prepareUpdatePayload(array $row): array
    {
        return [
            'nombre' => $row['nombre'] ?? null,
            'cuenta' => $row['cuenta'] ?? null,
            'producto' => $row['producto'] ?? null,
            'dpto' => $row['dpto'] ?? null,
            'provincia' => $row['provincia'] ?? null,
            'distrito' => $row['distrito'] ?? null,
            'direccion' => $row['direccion'] ?? null,
            'entidad' => $row['entidad'] ?? null,
            'cosecha' => $row['cosecha'] ?? null,
            'fecha_compra' => $row['fecha_compra'] ?? null,
            'fecha_castigo' => $row['fecha_castigo'] ?? null,
            'moneda' => $row['moneda'] ?? null,
            'deuda_capital' => $row['deuda_capital'] ?? null,
            'interes' => $row['interes'] ?? null,
            'deuda_total' => $row['deuda_total'] ?? null,
            'updated_at' => Carbon::now()->toDateTimeString(),
        ];
    }

    protected function detectFieldDifferences(object $target, array $row): array
    {
        $diffs = [];
        $checkFields = ['deuda_capital', 'interes', 'deuda_total', 'producto', 'cuenta', 'cosecha', 'fecha_compra', 'fecha_castigo', 'moneda'];

        foreach ($checkFields as $field) {
            $oldVal = $target->$field ?? null;
            $newVal = $row[$field] ?? null;

            if (in_array($field, ['deuda_capital', 'interes', 'deuda_total'], true)) {
                $oldNum = (float) ($oldVal ?? 0);
                $newNum = (float) ($newVal ?? 0);
                if (abs($oldNum - $newNum) > 0.005) {
                    $diffs[$field] = ['old' => $oldNum, 'new' => $newNum];
                }
            } else {
                if ($newVal !== null && trim((string) $oldVal) !== trim((string) $newVal)) {
                    $diffs[$field] = ['old' => (string) $oldVal, 'new' => (string) $newVal];
                }
            }
        }

        return $diffs;
    }

    /**
     * Check if client names have a severe irreconcilable mismatch (homonymy safeguard).
     */
    protected function hasSevereNameDiscrepancy(string $oldName, string $newName): bool
    {
        $wOld = preg_split('/\s+/', mb_strtolower(trim($oldName), 'UTF-8'));
        $wNew = preg_split('/\s+/', mb_strtolower(trim($newName), 'UTF-8'));

        if (count($wOld) < 2 || count($wNew) < 2) {
            return false;
        }

        $common = array_intersect($wOld, $wNew);
        return count($common) === 0;
    }
}
