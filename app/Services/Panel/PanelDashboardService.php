<?php

namespace App\Services\Panel;

use App\Models\CnaSolicitud;
use App\Models\PromesaPago;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelDashboardService
{
    public const CACHE_TTL_SECONDS = 60;
    public const CACHE_VERSION_KEY = 'dashboard_panel_version';
    public const CACHE_DIRTY_FLAG = 'panel_dashboard_cache_dirty';

    public function build(User $user, ?string $mesInput = null): array
    {
        $role = strtolower((string) ($user->role ?? ''));
        $isAsesor = ($role === 'asesor');
        $isSupervisor = ($role === 'supervisor');
        $isAdmin = ($role === 'administrador');

        $timezone = 'America/Lima';
        $hoy = Carbon::today($timezone)->toDateString();

        $mes = $mesInput && preg_match('/^\d{4}-\d{2}$/', $mesInput)
            ? $mesInput
            : Carbon::today($timezone)->format('Y-m');

        // Check for dirty flag (reconciliation/invalidation mutex exhaustion safeguard)
        $isDirty = (bool) Cache::get(self::CACHE_DIRTY_FLAG, false);
        if ($isDirty) {
            // Attempt self-healing via atomic lock
            if (self::doAtomicInvalidate()) {
                $isDirty = false;
            }
        }

        // If dirty state cannot be atomically bumped, bypass stale cache to compute fresh data directly
        if ($isDirty) {
            return $this->computeDashboardData(
                $user, $role, $isAsesor, $isSupervisor, $isAdmin, $hoy, $mes, $timezone
            );
        }

        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
        $cacheKey = "panel_dashboard_v3:v{$version}:u{$user->id}:r{$role}:m{$mes}:d{$hoy}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use (
            $user, $role, $isAsesor, $isSupervisor, $isAdmin, $hoy, $mes, $timezone
        ) {
            return $this->computeDashboardData(
                $user, $role, $isAsesor, $isSupervisor, $isAdmin, $hoy, $mes, $timezone
            );
        });
    }

    /**
     * Invalidate dashboard caches across the entire system.
     * Defer execution to afterCommit when inside an active database transaction.
     */
    public static function invalidate(): bool
    {
        try {
            if (DB::transactionLevel() > 0) {
                DB::afterCommit(function () {
                    try {
                        self::doAtomicInvalidate();
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('DASHBOARD_CACHE_AFTERCOMMIT_FAILED', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
                return true;
            }
        } catch (\Throwable) {
        }

        try {
            return self::doAtomicInvalidate();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('DASHBOARD_CACHE_INVALIDATE_FAILED', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Increment the dashboard cache version atomically.
     * Uses OS-level file locking (flock) to prevent lost updates under concurrency in the file driver,
     * with secondary defense via Cache::lock.
     *
     * If all lock mechanisms fail:
     * - Refuses to perform an uncoordinated non-atomic write.
     * - Logs a critical error for observability.
     * - Sets the dirty flag so subsequent dashboard reads bypass stale cache and self-heal.
     */
    public static function doAtomicInvalidate(): bool
    {
        $lockFile = storage_path('framework/cache/panel_dashboard_version.lock');
        $dir = dirname($lockFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $fp = @fopen($lockFile, 'c+');
        if ($fp) {
            // Attempt exclusive non-blocking lock (with micro-retries and jitter)
            $locked = false;
            for ($attempt = 0; $attempt < 15; $attempt++) {
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    $locked = true;
                    break;
                }
                usleep(random_int(10000, 25000)); // 10-25ms jitter
            }

            if ($locked) {
                try {
                    $current = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
                    Cache::forever(self::CACHE_VERSION_KEY, $current + 1);
                    Cache::forget(self::CACHE_DIRTY_FLAG);
                    return true;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('DASHBOARD_CACHE_WRITE_EXCEPTION', [
                        'error' => $e->getMessage(),
                    ]);
                    return false;
                } finally {
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }
            }
            fclose($fp);
        }

        // Secondary defense: Laravel Cache::lock with 1-second block
        try {
            $acquired = Cache::lock('panel_dashboard_version_lock', 5)->block(1, function () {
                $current = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
                Cache::forever(self::CACHE_VERSION_KEY, $current + 1);
                Cache::forget(self::CACHE_DIRTY_FLAG);
            });
            if ($acquired) {
                return true;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DASHBOARD_CACHE_LOCK_EXCEPTION', [
                'error' => $e->getMessage(),
            ]);
        }

        // Exhaustion: Refuse non-atomic write, set dirty flag, log critical error
        \Illuminate\Support\Facades\Log::critical('DASHBOARD_CACHE_MUTEX_EXHAUSTION', [
            'message' => 'Unable to acquire exclusive lock for dashboard version bump. Refusing non-atomic write.',
            'lock_file' => $lockFile,
        ]);

        try {
            Cache::put(self::CACHE_DIRTY_FLAG, true, 3600);
        } catch (\Throwable) {
        }
        return false;
    }

    private function computeDashboardData(
        User $user,
        string $role,
        bool $isAsesor,
        bool $isSupervisor,
        bool $isAdmin,
        string $hoy,
        string $mes,
        string $timezone
    ): array {
        /* ========================= KPIs ========================= */
        // 1. Promesas creadas hoy (Rango half-open [00:00:00, +1 day 00:00:00) para microsegundos)
        $startOfDay = Carbon::today($timezone)->startOfDay()->toDateTimeString();
        $startOfNextDay = Carbon::today($timezone)->addDay()->startOfDay()->toDateTimeString();

        $kpiPromHoy = PromesaPago::query()
            ->when($isAsesor, fn ($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<', $startOfNextDay)
            ->count();

        // 2. Pagos de hoy -> tabla: pagos (fecha, monto_pagado, gestor)
        $q = DB::table('pagos')->where('fecha', $hoy);
        if ($isAsesor) {
            $q->where('gestor', $user->name);
        }
        $kpiPagosHoy = (float) $q->sum('monto_pagado');

        /* ======= Actividades por ROL ======= */
        $ppPendCount = 0;
        $cnaPendCount = 0;
        $ppPend = collect();
        $cnaPend = collect();

        if (!$isAsesor) {
            $workflowTarget = $isSupervisor ? 'pendiente' : 'preaprobada';

            // Promesas pendientes/preaprobadas (defensive column selection for numdoc/dni)
            $ppDocCol = Schema::hasColumn('promesas_pago', 'numdoc') ? 'numdoc' : 'dni';
            $ppRows = PromesaPago::query()
                ->select('id', DB::raw("{$ppDocCol} as numdoc"), DB::raw("{$ppDocCol} as dni"), 'operacion', 'fecha_promesa', 'monto', 'monto_convenio', 'nota', 'tipo')
                ->where('workflow_estado', $workflowTarget)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(function ($p) {
                    $p->monto_mostrar = (float) ($p->monto > 0 ? $p->monto : $p->monto_convenio);
                    return $p;
                });

            $ppPend = $ppRows;
            if ($ppRows->count() < 5) {
                $ppPendCount = $ppRows->count();
            } else {
                $ppPendCount = PromesaPago::where('workflow_estado', $workflowTarget)->count();
            }

            // CNA pendientes/preaprobados (defensive column selection for numdoc/dni)
            $cnaDocCol = Schema::hasColumn('cna_solicitudes', 'numdoc') ? 'numdoc' : 'dni';
            $cnaRows = CnaSolicitud::query()
                ->select('id', DB::raw("{$cnaDocCol} as numdoc"), DB::raw("{$cnaDocCol} as dni"), 'nro_carta', 'operaciones', 'observacion', 'created_at')
                ->where('workflow_estado', $workflowTarget)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            $cnaPend = $cnaRows;
            if ($cnaRows->count() < 5) {
                $cnaPendCount = $cnaRows->count();
            } else {
                $cnaPendCount = CnaSolicitud::where('workflow_estado', $workflowTarget)->count();
            }
        }

        // Asesor: sus propias solicitudes por estado
        $misSup = collect();
        $misPre = collect();
        $misRes = collect();
        $cnaSup = collect();
        $cnaPre = collect();
        $cnaRes = collect();

        if ($isAsesor) {
            $misSup = PromesaPago::where('user_id', $user->id)
                ->where('workflow_estado', 'pendiente')
                ->latest()->limit(5)->get();

            $misPre = PromesaPago::where('user_id', $user->id)
                ->where('workflow_estado', 'preaprobada')
                ->latest()->limit(5)->get();

            $misRes = PromesaPago::where('user_id', $user->id)
                ->whereIn('workflow_estado', ['aprobada', 'rechazada', 'rechazada_sup'])
                ->latest()->limit(5)->get();

            $cnaSup = CnaSolicitud::where('user_id', $user->id)
                ->where('workflow_estado', 'pendiente')
                ->latest()->limit(5)->get();

            $cnaPre = CnaSolicitud::where('user_id', $user->id)
                ->where('workflow_estado', 'preaprobada')
                ->latest()->limit(5)->get();

            $cnaRes = CnaSolicitud::where('user_id', $user->id)
                ->whereIn('workflow_estado', ['aprobada', 'rechazada', 'rechazada_sup'])
                ->latest()->limit(5)->get();
        }

        /* ======= Cuotas próximos 7 días (solo sup/admin) ======= */
        $venc = collect();
        $vencCount = 0;

        if (!$isAsesor) {
            $desde = Carbon::today($timezone)->toDateString();
            $hasta = Carbon::today($timezone)->addDays(7)->toDateString();
            $ppDocCol = Schema::hasColumn('promesas_pago', 'numdoc') ? 'numdoc' : 'dni';

            $vencBaseQuery = DB::table('promesa_cuotas as c')
                ->join('promesas_pago as p', 'p.id', '=', 'c.promesa_id')
                ->where('p.workflow_estado', 'aprobada')
                ->whereBetween('c.fecha', [$desde, $hasta]);

            $vencCount = (clone $vencBaseQuery)->count();
            $venc = $vencCount > 0
                ? (clone $vencBaseQuery)
                    ->select(DB::raw("p.{$ppDocCol} as numdoc"), DB::raw("p.{$ppDocCol} as dni"), 'p.operacion', 'p.tipo', 'c.promesa_id', 'c.nro', 'c.fecha', 'c.monto')
                    ->orderBy('c.fecha')
                    ->limit(8)
                    ->get()
                : collect();
        }

        /* ================= Gráfico Pagos del mes ================= */
        try {
            $ini = Carbon::createFromFormat('Y-m', $mes, $timezone)->startOfMonth();
        } catch (\Exception $e) {
            $ini = Carbon::today($timezone)->startOfMonth();
            $mes = $ini->format('Y-m');
        }
        $fin = (clone $ini)->endOfMonth();

        $chartLabels = [];
        $chartData = [];
        $sumMes = 0.0;

        $rows = DB::table('pagos')
            ->whereBetween('fecha', [$ini->toDateString(), $fin->toDateString()])
            ->when($isAsesor, fn ($q) => $q->where('gestor', $user->name))
            ->selectRaw('fecha as f, SUM(monto_pagado) as s')
            ->groupBy('fecha')->orderBy('fecha')->get()->keyBy('f');

        $days = $ini->daysInMonth;
        for ($d = 1; $d <= $days; $d++) {
            $date = $ini->copy()->day($d)->toDateString();
            $chartLabels[] = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $val = (float) ($rows[$date]->s ?? 0);
            $chartData[] = round($val, 2);
            $sumMes += $val;
        }

        return [
            'role' => $role,
            'isAsesor' => $isAsesor,
            'isSupervisor' => $isSupervisor,
            'isAdmin' => $isAdmin,
            'kpiPromHoy' => $kpiPromHoy,
            'kpiPagosHoy' => $kpiPagosHoy,
            'ppPendCount' => $ppPendCount,
            'ppPend' => $ppPend,
            'cnaPendCount' => $cnaPendCount,
            'cnaPend' => $cnaPend,
            'venc' => $venc,
            'vencCount' => $vencCount,
            'misSup' => $misSup,
            'misPre' => $misPre,
            'misRes' => $misRes,
            'cnaSup' => $cnaSup,
            'cnaPre' => $cnaPre,
            'cnaRes' => $cnaRes,
            'mes' => $mes,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'sumMes' => round($sumMes, 2),
        ];
    }
}
