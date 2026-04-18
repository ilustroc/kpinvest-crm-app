<?php

namespace App\Services\Dashboard;

use App\Models\PagoPropia as Pago;
use App\Models\PromesaPago;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardStatsService
{
    public function build(Request $request, $user): array
    {
        $isAsesor = strtolower((string) ($user->role ?? '')) === 'asesor';
        $asesorNombre = trim((string) ($user->name ?? ''));

        $mes = (string) $request->query('mes', now('America/Lima')->format('Y-m'));
        $diaHabil = max(0, (int) $request->query('dia_habil', 0));
        $fCosecha = trim((string) $request->query('cosecha', ''));
        $fEntidad = trim((string) $request->query('entidad', ''));
        $fAsesor = $isAsesor
            ? ''
            : trim((string) ($request->query('asesor', '') ?: $request->query('gestor', '')));

        [$inicioMes, $finMes, $mes] = $this->resolverMes($mes);
        $corteMes = $this->fechaCortePorRetroceso($inicioMes, $diaHabil);

        $basePagos = $this->pagosBaseQuery(
            isAsesor: $isAsesor,
            asesorNombre: $asesorNombre,
            fAsesor: $fAsesor,
            fCosecha: $fCosecha,
            fEntidad: $fEntidad
        );

        $kpis = $this->buildKpis(
            inicioMes: $inicioMes,
            corteMes: $corteMes,
            isAsesor: $isAsesor,
            userId: $user->id ?? null,
            basePagos: $basePagos
        );

        $serie12Meses = $this->buildSerie12Meses($basePagos, $inicioMes, $diaHabil);

        $periodosComp = $this->buildPeriodosComparativos($inicioMes, $diaHabil);

        $comparativoEntidades = $this->buildComparativoTop(
            baseQuery: $basePagos,
            campo: 'entidad',
            periodos: $periodosComp,
            limite: 8
        );

        $comparativoAsesores = $isAsesor
            ? []
            : $this->buildComparativoTop(
                baseQuery: $basePagos,
                campo: 'gestor',
                periodos: $periodosComp,
                limite: 10
            );

        $selects = $this->buildSelects(
            inicioMes: $inicioMes,
            finMes: $finMes,
            isAsesor: $isAsesor,
            asesorNombre: $asesorNombre
        );

        return [
            'mes'                   => $mes,
            'dia_habil'             => $diaHabil,
            'fecha_corte_texto'     => $corteMes->format('d/m/Y'),
            'isAsesor'              => $isAsesor,

            'k'                     => $kpis,
            'meses'                 => $serie12Meses['meses'],
            'serie_pagos_monto'     => $serie12Meses['serie'],

            'periodosComp'          => $periodosComp,
            'comparativoEntidades'  => $comparativoEntidades,
            'comparativoAsesores'   => $comparativoAsesores,

            'cosechas'              => $selects['cosechas'],
            'entidades'             => $selects['entidades'],
            'asesores'              => $selects['asesores'],

            'fCosecha'              => $fCosecha,
            'fEntidad'              => $fEntidad,
            'fAsesor'               => $fAsesor,
        ];
    }

    private function resolverMes(string $mes): array
    {
        try {
            $inicioMes = Carbon::createFromFormat('Y-m', $mes, 'America/Lima')->startOfMonth();
        } catch (\Throwable $e) {
            $inicioMes = now('America/Lima')->startOfMonth();
            $mes = $inicioMes->format('Y-m');
        }

        $finMes = $inicioMes->copy()->endOfMonth();

        return [$inicioMes, $finMes, $mes];
    }

    private function pagosBaseQuery(
        bool $isAsesor,
        string $asesorNombre,
        string $fAsesor,
        string $fCosecha,
        string $fEntidad
    ): Builder {
        $query = Pago::query();

        if ($isAsesor && $asesorNombre !== '') {
            $query->where('gestor', $asesorNombre);
        } elseif (!$isAsesor && $fAsesor !== '') {
            $query->where('gestor', 'like', "%{$fAsesor}%");
        }

        if ($fCosecha !== '') {
            $query->where('cosecha', $fCosecha);
        }

        if ($fEntidad !== '') {
            $query->where('entidad', $fEntidad);
        }

        return $query;
    }

    private function buildKpis(
        Carbon $inicioMes,
        Carbon $corteMes,
        bool $isAsesor,
        ?int $userId,
        Builder $basePagos
    ): array {
        $pdpQuery = PromesaPago::query()
            ->whereBetween('created_at', [
                $inicioMes->copy()->startOfDay(),
                $corteMes->copy()->endOfDay(),
            ]);

        if ($isAsesor && $userId) {
            $pdpQuery->where('user_id', $userId);
        }

        $pdpGen = (clone $pdpQuery)->count();

        $pdpMonto = (float) (clone $pdpQuery)
            ->selectRaw("
                SUM(
                    CASE
                        WHEN tipo = 'convenio' THEN COALESCE(monto_convenio, 0)
                        ELSE COALESCE(monto, 0)
                    END
                ) as total
            ")
            ->value('total');

        $pagosNum = (clone $basePagos)
            ->whereBetween('fecha', [
                $inicioMes->toDateString(),
                $corteMes->toDateString(),
            ])
            ->count();

        $pagosMonto = (float) (clone $basePagos)
            ->whereBetween('fecha', [
                $inicioMes->toDateString(),
                $corteMes->toDateString(),
            ])
            ->sum('monto_pagado');

        return [
            'pdp_gen'     => $pdpGen,
            'pdp_monto'   => $pdpMonto,
            'pagos_num'   => $pagosNum,
            'pagos_monto' => $pagosMonto,
        ];
    }

    private function buildSerie12Meses(Builder $basePagos, Carbon $inicioMes, int $diaHabil): array
    {
        $meses = [];
        $serie = [];

        $cursor = $inicioMes->copy()->subMonths(11)->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $mesIterado = $cursor->copy();
            $corteIterado = $this->fechaCortePorRetroceso($mesIterado, $diaHabil);

            $meses[] = strtoupper(rtrim($mesIterado->locale('es')->isoFormat('MMM'), '.'));

            $serie[] = (float) (clone $basePagos)
                ->whereBetween('fecha', [
                    $mesIterado->toDateString(),
                    $corteIterado->toDateString(),
                ])
                ->sum('monto_pagado');

            $cursor->addMonth();
        }

        return [
            'meses' => $meses,
            'serie' => $serie,
        ];
    }

    private function buildPeriodosComparativos(Carbon $inicioMes, int $diaHabil): array
    {
        $m0 = $inicioMes->copy()->startOfMonth();
        $m1 = $inicioMes->copy()->subMonth()->startOfMonth();
        $m2 = $inicioMes->copy()->subMonths(2)->startOfMonth();

        return [
            'm2' => [
                'label'  => ucfirst($m2->locale('es')->isoFormat('MMM YYYY')),
                'inicio' => $m2->copy()->startOfMonth(),
                'corte'  => $this->fechaCortePorRetroceso($m2, $diaHabil),
            ],
            'm1' => [
                'label'  => ucfirst($m1->locale('es')->isoFormat('MMM YYYY')),
                'inicio' => $m1->copy()->startOfMonth(),
                'corte'  => $this->fechaCortePorRetroceso($m1, $diaHabil),
            ],
            'm0' => [
                'label'  => ucfirst($m0->locale('es')->isoFormat('MMM YYYY')),
                'inicio' => $m0->copy()->startOfMonth(),
                'corte'  => $this->fechaCortePorRetroceso($m0, $diaHabil),
            ],
        ];
    }

    private function buildComparativoTop(
        Builder $baseQuery,
        string $campo,
        array $periodos,
        int $limite = 10
    ): array {
        $bolsas = [];

        foreach ($periodos as $key => $periodo) {
            $bolsas[$key] = (clone $baseQuery)
                ->selectRaw("COALESCE(NULLIF(TRIM({$campo}), ''), '—') as nombre, SUM(monto_pagado) as total")
                ->whereBetween('fecha', [
                    $periodo['inicio']->toDateString(),
                    $periodo['corte']->toDateString(),
                ])
                ->groupBy('nombre')
                ->pluck('total', 'nombre')
                ->map(fn ($v) => (float) $v)
                ->toArray();
        }

        $nombres = collect($bolsas)
            ->flatMap(fn ($items) => array_keys($items))
            ->unique()
            ->values();

        return $nombres
            ->map(function ($nombre) use ($bolsas) {
                $m2 = (float) ($bolsas['m2'][$nombre] ?? 0);
                $m1 = (float) ($bolsas['m1'][$nombre] ?? 0);
                $m0 = (float) ($bolsas['m0'][$nombre] ?? 0);

                return [
                    'nombre' => $nombre,
                    'm2'     => $m2,
                    'm1'     => $m1,
                    'm0'     => $m0,
                    'total'  => $m2 + $m1 + $m0,
                ];
            })
            ->sortByDesc('total')
            ->take($limite)
            ->values()
            ->all();
    }

    private function buildSelects(
        Carbon $inicioMes,
        Carbon $finMes,
        bool $isAsesor,
        string $asesorNombre
    ): array {
        $optsMes = Pago::query()->whereBetween('fecha', [
            $inicioMes->toDateString(),
            $finMes->toDateString(),
        ]);

        if ($isAsesor && $asesorNombre !== '') {
            $optsMes->where('gestor', $asesorNombre);
        }

        $cosechas = (clone $optsMes)
            ->whereNotNull('cosecha')
            ->select('cosecha')
            ->distinct()
            ->orderBy('cosecha')
            ->pluck('cosecha');

        $entidades = (clone $optsMes)
            ->whereNotNull('entidad')
            ->select('entidad')
            ->distinct()
            ->orderBy('entidad')
            ->pluck('entidad');

        $asesores = $isAsesor
            ? collect()
            : (clone $optsMes)
                ->whereNotNull('gestor')
                ->select('gestor')
                ->distinct()
                ->orderBy('gestor')
                ->pluck('gestor');

        return [
            'cosechas'  => $cosechas,
            'entidades' => $entidades,
            'asesores'  => $asesores,
        ];
    }

    private function fechaCortePorRetroceso(Carbon $inicioMes, int $retroceso): Carbon
    {
        $cursor = $inicioMes->copy()->endOfMonth();
        $feriados = $this->feriadosPeru($cursor->year);
        $diasHabiles = [];

        while ($cursor->month === $inicioMes->month) {
            if ($this->esDiaHabil($cursor, $feriados)) {
                $diasHabiles[] = $cursor->copy();
            }
            $cursor->subDay();
        }

        if (empty($diasHabiles)) {
            return $inicioMes->copy()->endOfMonth();
        }

        $indice = min($retroceso, count($diasHabiles) - 1);

        return $diasHabiles[$indice];
    }

    private function esDiaHabil(Carbon $fecha, array $feriados): bool
    {
        if ($fecha->dayOfWeek === Carbon::SUNDAY) {
            return false;
        }

        return !in_array($fecha->toDateString(), $feriados, true);
    }

    private function feriadosPeru(int $year): array
    {
        $domingoPascua = $this->calcularDomingoPascua($year);
        $juevesSanto = $domingoPascua->copy()->subDays(3);
        $viernesSanto = $domingoPascua->copy()->subDays(2);

        return [
            Carbon::create($year, 1, 1)->toDateString(),
            $juevesSanto->toDateString(),
            $viernesSanto->toDateString(),
            Carbon::create($year, 5, 1)->toDateString(),
            Carbon::create($year, 6, 7)->toDateString(),
            Carbon::create($year, 6, 29)->toDateString(),
            Carbon::create($year, 7, 23)->toDateString(),
            Carbon::create($year, 7, 28)->toDateString(),
            Carbon::create($year, 7, 29)->toDateString(),
            Carbon::create($year, 8, 6)->toDateString(),
            Carbon::create($year, 8, 30)->toDateString(),
            Carbon::create($year, 10, 8)->toDateString(),
            Carbon::create($year, 11, 1)->toDateString(),
            Carbon::create($year, 12, 8)->toDateString(),
            Carbon::create($year, 12, 9)->toDateString(),
            Carbon::create($year, 12, 25)->toDateString(),
        ];
    }

    private function calcularDomingoPascua(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }
}