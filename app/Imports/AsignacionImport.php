<?php

namespace App\Imports;

use App\Models\AsignarCliente;
use App\Models\ClienteCuenta;
use App\Models\User;
use App\Services\Asignacion\ClienteAssignmentService;
use App\Services\Panel\PanelDashboardService;
use App\Traits\CsvImportTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AsignacionImport
{
    use CsvImportTrait;

    public function __construct(
        private readonly ?ClienteAssignmentService $assignmentService = null,
    ) {
    }

    /**
     * Diagnostic preview without writing to the database.
     *
     * @return array{0: int, 1: int, 2: array<int, string>, 3: array<string, mixed>}
     */
    public function preview(string $filepath, ?int $actorId = null): array
    {
        return $this->analyzeAndOptionallyPersist($filepath, false, $actorId);
    }

    /**
     * Execute bulk assignment import atomically.
     *
     * @return array{0: int, 1: int, 2: array<int, string>, 3: array<string, mixed>}
     */
    public function execute(string $filepath, ?int $actorId = null): array
    {
        return $this->analyzeAndOptionallyPersist($filepath, true, $actorId);
    }

    /**
     * @return array{0: int, 1: int, 2: array<int, string>, 3: array<string, mixed>}
     */
    private function analyzeAndOptionallyPersist(string $filepath, bool $persist, ?int $actorId = null): array
    {
        $summary = [
            'total_filas' => 0,
            'nuevas_asignaciones' => 0,
            'sin_cambio_efectivo' => 0,
            'cuenta_inexistente' => 0,
            'cuenta_ambigua' => 0,
            'asesor_invalido' => 0,
            'duplicados' => 0,
            'datos_incompletos' => 0,
            'detalles_rechazados' => [],
        ];

        $fh = @fopen($filepath, 'r');
        if (!$fh) {
            return [0, 0, ['No se pudo abrir el archivo'], $summary];
        }

        $firstLine = fgets($fh);
        if ($firstLine === false) {
            fclose($fh);
            return [0, 0, ['Archivo vacío'], $summary];
        }

        $del = $this->detectDelimiter($firstLine);
        rewind($fh);

        $headers = fgetcsv($fh, 0, $del);
        if (!$headers) {
            fclose($fh);
            return [0, 0, ['Sin encabezados'], $summary];
        }

        if (count($headers) === 1 && str_contains($headers[0], "\t")) {
            rewind($fh);
            $del = "\t";
            $headers = fgetcsv($fh, 0, "\t");
        }

        $idx = $this->mapHeaders($headers);
        if (!isset($idx['numdoc'], $idx['operacion'], $idx['name'])) {
            fclose($fh);
            return [0, 0, ['Encabezados incompletos: se requiere NUMDOC, OPERACION, NAME'], $summary];
        }

        $advisorDirectory = $this->buildAdvisorDirectory();
        $rawRows = [];
        $seenInCsv = [];
        $err = [];
        $rejectedDetails = [];
        $rowNum = 1;

        while (($row = fgetcsv($fh, 0, $del)) !== false) {
            // Ignore completely blank trailing lines
            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;
            }

            $rowNum++;
            $summary['total_filas']++;

            $numdoc = trim($this->toUtf8((string) ($row[$idx['numdoc']] ?? '')));
            $oper   = trim($this->toUtf8((string) ($row[$idx['operacion']] ?? '')));
            $name   = trim($this->toUtf8((string) ($row[$idx['name']] ?? '')));

            if ($numdoc === '' || $oper === '' || $name === '') {
                $summary['datos_incompletos']++;
                $motivo = 'Datos incompletos (NUMDOC, OPERACION y NAME son obligatorios).';
                $err[] = "Fila {$rowNum}: {$motivo}";
                $rejectedDetails[] = [
                    'fila' => $rowNum,
                    'numdoc' => $numdoc,
                    'operacion' => $oper,
                    'asesor' => $name,
                    'categoria' => 'datos_incompletos',
                    'motivo' => $motivo,
                ];
                continue;
            }

            $pairKey = "{$numdoc}|{$oper}";
            if (isset($seenInCsv[$pairKey])) {
                $firstRow = $seenInCsv[$pairKey];
                $summary['duplicados']++;
                $motivo = "Fila duplicada dentro del archivo para documento {$numdoc} y operación {$oper} (primera aparición en fila {$firstRow}).";
                $err[] = "Fila {$rowNum}: {$motivo}";
                $rejectedDetails[] = [
                    'fila' => $rowNum,
                    'numdoc' => $numdoc,
                    'operacion' => $oper,
                    'asesor' => $name,
                    'categoria' => 'duplicado_csv',
                    'motivo' => $motivo,
                ];
                continue;
            }
            $seenInCsv[$pairKey] = $rowNum;

            // Validate advisor resolution against users table
            $advisorResolution = $this->resolveAdvisor($name, $advisorDirectory);
            if ($advisorResolution['status'] !== 'ok') {
                $summary['asesor_invalido']++;
                $motivo = $advisorResolution['message'];
                $err[] = "Fila {$rowNum}: {$motivo}";
                $rejectedDetails[] = [
                    'fila' => $rowNum,
                    'numdoc' => $numdoc,
                    'operacion' => $oper,
                    'asesor' => $name,
                    'categoria' => $advisorResolution['status'],
                    'motivo' => $motivo,
                ];
                continue;
            }

            $rawRows[] = [
                '__row' => $rowNum,
                'numdoc' => $numdoc,
                'operacion' => $oper,
                'raw_name' => $name,
                'user' => $advisorResolution['user'],
            ];
        }

        fclose($fh);

        if (empty($rawRows)) {
            $summary['detalles_rechazados'] = $rejectedDetails;
            $skipCount = count($rejectedDetails);
            return [0, $skipCount, $err, $summary];
        }

        $lockHandle = null;
        if ($persist) {
            $lockDir = storage_path('app/locks');
            if (!is_dir($lockDir)) {
                @mkdir($lockDir, 0775, true);
            }
            $lockFile = $lockDir . '/asignacion_import.lock';
            $lockHandle = @fopen($lockFile, 'c+');
            if ($lockHandle === false || !@flock($lockHandle, LOCK_EX)) {
                if (is_resource($lockHandle)) {
                    fclose($lockHandle);
                }
                throw new RuntimeException('No se pudo adquirir el bloqueo exclusivo para la importación de asignaciones.');
            }
        }

        try {
            $runner = function () use (
                $rawRows,
                $persist,
                $actorId,
                &$summary,
                &$err,
                &$rejectedDetails
            ): void {
                $numdocs = array_values(array_unique(array_column($rawRows, 'numdoc')));
                $opers   = array_values(array_unique(array_column($rawRows, 'operacion')));

                $cuentasQuery = ClienteCuenta::query()
                    ->select(['id', 'numdoc', 'operacion', 'entidad', 'producto'])
                    ->whereIn('numdoc', $numdocs)
                    ->whereIn('operacion', $opers);

                if ($persist) {
                    $cuentasQuery->lockForUpdate();
                }

                $cuentas = $cuentasQuery->get();
                $cuentasByPair = $cuentas->groupBy(fn (ClienteCuenta $c) => "{$c->numdoc}|{$c->operacion}");

                $validAccountRows = [];
                $resolvedCuentas = collect();

                foreach ($rawRows as $r) {
                    $pairKey = "{$r['numdoc']}|{$r['operacion']}";
                    $matching = $cuentasByPair->get($pairKey);

                    if (!$matching || $matching->isEmpty()) {
                        $summary['cuenta_inexistente']++;
                        $motivo = "El cliente {$r['numdoc']} Op {$r['operacion']} NO existe en clientes_cuentas.";
                        $err[] = "Fila {$r['__row']}: {$motivo}";
                        $rejectedDetails[] = [
                            'fila' => $r['__row'],
                            'numdoc' => $r['numdoc'],
                            'operacion' => $r['operacion'],
                            'asesor' => $r['raw_name'],
                            'categoria' => 'cuenta_inexistente',
                            'motivo' => $motivo,
                        ];
                        continue;
                    }

                    if ($matching->count() > 1) {
                        $summary['cuenta_ambigua']++;
                        $motivo = "La cuenta {$r['numdoc']} Op {$r['operacion']} es ambigua en clientes_cuentas ({$matching->count()} registros encontrados).";
                        $err[] = "Fila {$r['__row']}: {$motivo}";
                        $rejectedDetails[] = [
                            'fila' => $r['__row'],
                            'numdoc' => $r['numdoc'],
                            'operacion' => $r['operacion'],
                            'asesor' => $r['raw_name'],
                            'categoria' => 'cuenta_ambigua',
                            'motivo' => $motivo,
                        ];
                        continue;
                    }

                    /** @var ClienteCuenta $cuenta */
                    $cuenta = $matching->first();
                    $r['cuenta'] = $cuenta;
                    $validAccountRows[] = $r;
                    $resolvedCuentas->push($cuenta);
                }

                if (empty($validAccountRows)) {
                    return;
                }

                $service = $this->assignmentService ?? app(ClienteAssignmentService::class);
                $activeMap = $service->resolveActiveAssignmentsForAccounts($resolvedCuentas);
                $byCuentaId = $activeMap['by_cuenta_id'] ?? [];
                $byOperation = $activeMap['by_operation'] ?? [];

                $effectiveUserId = $actorId ?? Auth::id();
                $now = Carbon::now('America/Lima');
                $rowsToInsert = [];

                foreach ($validAccountRows as $item) {
                    /** @var ClienteCuenta $cuenta */
                    $cuenta = $item['cuenta'];
                    /** @var User $targetUser */
                    $targetUser = $item['user'];

                    $vigente = $byCuentaId[(int) $cuenta->id] ?? ($byOperation[(string) $cuenta->operacion] ?? null);

                    if ($this->isSameAdvisor($vigente, $targetUser)) {
                        $summary['sin_cambio_efectivo']++;
                        continue;
                    }

                    $summary['nuevas_asignaciones']++;

                    if ($persist) {
                        $rowsToInsert[] = [
                            'numdoc' => (string) $cuenta->numdoc,
                            'operacion' => (string) $cuenta->operacion,
                            'cliente_cuenta_id' => (int) $cuenta->id,
                            'name' => trim(str_replace("\xC2\xA0", ' ', (string) $targetUser->name)),
                            'solicitante_user_id' => $effectiveUserId ? (int) $effectiveUserId : null,
                            'asesor_destino_user_id' => (int) $targetUser->id,
                            'fecha_solicitud' => $now,
                            'motivo' => 'carga masiva',
                            'estado' => AsignarCliente::ESTADO_APROBADA,
                            'aprobado_por_user_id' => $effectiveUserId ? (int) $effectiveUserId : null,
                            'fecha_aprobacion' => $now,
                            'observacion_resolucion' => null,
                            'origen' => AsignarCliente::ORIGEN_CARGA_MASIVA,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($persist && !empty($rowsToInsert)) {
                    foreach (array_chunk($rowsToInsert, 500) as $chunk) {
                        DB::table('asignar_clientes')->insert($chunk);
                    }
                }
            };

            if ($persist) {
                DB::transaction($runner);
                if ($summary['nuevas_asignaciones'] > 0) {
                    PanelDashboardService::invalidate();
                }
            } else {
                $runner();
            }
        } finally {
            if (is_resource($lockHandle)) {
                @flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }

        $summary['detalles_rechazados'] = $rejectedDetails;
        $ok = $summary['nuevas_asignaciones'] + $summary['sin_cambio_efectivo'];
        $skip = count($rejectedDetails);

        return [$ok, $skip, $err, $summary];
    }

    /**
     * Build lookup directory of users by email, ID, and unique normalized name.
     *
     * @return array{
     *   by_email: array<string, User>,
     *   by_id: array<int, User>,
     *   by_norm_name: array<string, User>,
     *   ambiguous_names: array<string, int>
     * }
     */
    private function buildAdvisorDirectory(): array
    {
        $users = User::query()->select(['id', 'name', 'email', 'role'])->get();

        $byEmail = [];
        $byId = [];
        $groupedByName = [];

        foreach ($users as $user) {
            $byId[(int) $user->id] = $user;

            $emailKey = strtolower(trim((string) $user->email));
            if ($emailKey !== '') {
                $byEmail[$emailKey] = $user;
            }

            $normName = $this->normalizeUserName((string) $user->name);
            if ($normName !== '') {
                $groupedByName[$normName][] = $user;
            }
        }

        $byNormName = [];
        $ambiguousNames = [];

        foreach ($groupedByName as $normName => $list) {
            if (count($list) === 1) {
                $byNormName[$normName] = $list[0];
            } else {
                $ambiguousNames[$normName] = count($list);
            }
        }

        return [
            'by_email' => $byEmail,
            'by_id' => $byId,
            'by_norm_name' => $byNormName,
            'ambiguous_names' => $ambiguousNames,
        ];
    }

    /**
     * @param array<string, mixed> $directory
     * @return array{status: string, user?: User, message?: string}
     */
    private function resolveAdvisor(string $rawInput, array $directory): array
    {
        $clean = trim($rawInput);

        // 1. Check email match
        $emailKey = strtolower($clean);
        if (isset($directory['by_email'][$emailKey])) {
            return [
                'status' => 'ok',
                'user' => $directory['by_email'][$emailKey],
            ];
        }

        // 2. Check normalized name
        $normName = $this->normalizeUserName($clean);
        if (isset($directory['ambiguous_names'][$normName])) {
            $count = $directory['ambiguous_names'][$normName];
            return [
                'status' => 'asesor_ambiguo',
                'message' => "El asesor '{$clean}' es ambiguo (coincide con {$count} usuarios en users). Especifique su email.",
            ];
        }

        if (isset($directory['by_norm_name'][$normName])) {
            return [
                'status' => 'ok',
                'user' => $directory['by_norm_name'][$normName],
            ];
        }

        return [
            'status' => 'asesor_inexistente',
            'message' => "El asesor '{$clean}' no existe en la tabla users.",
        ];
    }

    private function isSameAdvisor(?AsignarCliente $vigente, User $targetUser): bool
    {
        if ($vigente === null) {
            return false;
        }

        if ($vigente->asesor_destino_user_id !== null) {
            return (int) $vigente->asesor_destino_user_id === (int) $targetUser->id;
        }

        return $this->normalizeUserName((string) $vigente->name) === $this->normalizeUserName((string) $targetUser->name);
    }

    private function normalizeUserName(string $name): string
    {
        $replaced = str_replace("\xC2\xA0", ' ', $name);
        $collapsed = preg_replace('/\s+/u', ' ', trim($replaced));

        return mb_strtoupper((string) $collapsed, 'UTF-8');
    }

    private function mapHeaders(array $headers): array
    {
        $idx = [];

        foreach ($headers as $i => $h) {
            $k = $this->cleanHeader($h);

            if (in_array($k, ['NUMDOC', 'DOCUMENTO', 'DNI'], true) && !isset($idx['numdoc'])) {
                $idx['numdoc'] = $i;
            }
            if ($k === 'OPERACION' && !isset($idx['operacion'])) {
                $idx['operacion'] = $i;
            }
            if (in_array($k, ['NAME', 'ASESOR', 'USUARIO', 'EMAIL', 'ASESOR_DESTINO'], true) && !isset($idx['name'])) {
                $idx['name'] = $i;
            }
        }

        return $idx;
    }

    private function cleanHeader($h): string
    {
        $h = $this->toUtf8((string) $h);
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
        $h = trim($h);
        $h = strtoupper($h);
        $h = str_replace([' ', '-', '/', '\\', '.'], '_', $h);
        $h = preg_replace('/_+/', '_', $h);
        $h = preg_replace('/[^A-Z0-9_]/', '', $h);

        return $h;
    }
}
