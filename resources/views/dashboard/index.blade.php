@extends('layouts.app')
@section('tailwind_only', true)
@section('title', 'Estadísticas')
@section('crumb', 'Estadísticas')

@section('content')
@php
    $dashboardJson = [
        'meses' => $meses ?? [],
        'serieMto' => $serie_pagos_monto ?? [],
    ];

    $money = fn ($value) => \App\Support\Money::format($value, 'PEN');
    $periodo = \Carbon\Carbon::createFromFormat('Y-m', $mes ?? now()->format('Y-m'))->locale('es');
    $periodoLabel = ucfirst($periodo->isoFormat('MMMM YYYY'));

    $pdpGen = (float) ($k['pdp_gen'] ?? 0);
    $pdpMonto = (float) ($k['pdp_monto'] ?? 0);
    $pagosNum = (float) ($k['pagos_num'] ?? 0);
    $pagosMonto = (float) ($k['pagos_monto'] ?? 0);
    $ticketPromedio = $pagosNum > 0 ? $pagosMonto / $pagosNum : 0;
    $avance = $pdpMonto > 0 ? min(100, round(($pagosMonto / $pdpMonto) * 100)) : null;
    $maxEntidad = collect($comparativoEntidades ?? [])->max('total') ?: 0;
    $maxAsesor = collect($comparativoAsesores ?? [])->max('total') ?: 0;
    $filtrosActivos = count(array_filter([$fCosecha ?? '', $fEntidad ?? '', $fAsesor ?? '']));
    $serieVacia = collect($serie_pagos_monto ?? [])->sum() <= 0;
@endphp

<div class="space-y-4" data-dashboard-page>
    <script id="dashboard-json" type="application/json">{!! json_encode($dashboardJson, JSON_UNESCAPED_SLASHES) !!}</script>

    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-[0.16em] text-kp-primary-ink">Analítica de cobranza</span>
                <span class="h-3 w-px bg-kp-border"></span>
                <span class="text-[11px] font-medium text-kp-muted">{{ $periodoLabel }}</span>
            </div>
            <h1 class="mt-2 text-[26px] font-semibold tracking-[-0.03em] text-kp-ink">Estadísticas</h1>
            <p class="mt-1 max-w-2xl text-[13px] leading-5 text-kp-muted">
                Evolución de pagos, resultados del periodo y comparativo de la operación.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:flex">
            <div class="rounded-lg border border-kp-border bg-white px-3.5 py-2.5">
                <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Fecha de corte</p>
                <p class="mt-0.5 text-[13px] font-semibold text-kp-ink">{{ $fecha_corte_texto ?? '-' }}</p>
            </div>
            <div class="rounded-lg border border-kp-border bg-white px-3.5 py-2.5">
                <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Día hábil</p>
                <p class="mt-0.5 text-[13px] font-semibold text-kp-ink">{{ $dia_habil ?? 0 }}</p>
            </div>
            <div class="hidden items-center gap-2 rounded-lg border border-kp-primary-border bg-kp-primary-soft px-3.5 py-2.5 sm:flex">
                <span class="size-2 rounded-full bg-kp-green"></span>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-navy/80">Periodo</p>
                    <p class="mt-0.5 text-[13px] font-semibold text-kp-navy">{{ $periodoLabel }}</p>
                </div>
            </div>
        </div>
    </header>

    <section class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3">
            <div class="flex items-center gap-2.5">
                <div class="flex size-7 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M6.75 4.5v3m10.5-3v3M4.5 19.5h15a1.5 1.5 0 0 0 1.5-1.5V9H3v9a1.5 1.5 0 0 0 1.5 1.5Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-[13px] font-semibold text-kp-ink">Filtros de análisis</h2>
                    <p class="text-[11px] text-kp-muted">{{ $filtrosActivos > 0 ? $filtrosActivos.' filtro(s) adicional(es) aplicado(s)' : 'Sin filtros adicionales' }}</p>
                </div>
            </div>
            @if($filtrosActivos > 0 || ($dia_habil ?? 0) > 0 || ($mes ?? '') !== now('America/Lima')->format('Y-m'))
                <a href="{{ route('dashboard') }}" class="text-[11px] font-semibold text-kp-secondary hover:text-kp-navy">Restablecer todo</a>
            @endif
        </header>

        <form id="filtrosDash" class="grid gap-3 px-4 py-4 md:grid-cols-2 xl:grid-cols-12 xl:items-end" method="GET" action="{{ route('dashboard') }}">
            <div class="xl:col-span-2">
                <x-ui.date label="Mes" type="month" name="mes" value="{{ $mes ?? now()->format('Y-m') }}" />
            </div>

            <div class="xl:col-span-1">
                <x-ui.input label="Día hábil" type="number" min="0" step="1" name="dia_habil" value="{{ $dia_habil ?? 0 }}" />
            </div>

            <div class="xl:col-span-2">
                <x-ui.select label="Cosecha" name="cosecha">
                    <option value="">Todas</option>
                    @foreach($cosechas as $c)
                        <option value="{{ $c }}" @selected(($fCosecha ?? '') === $c)>{{ $c }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="xl:col-span-3">
                <x-ui.select label="Entidad financiera" name="entidad">
                    <option value="">Todas</option>
                    @foreach($entidades as $e)
                        <option value="{{ $e }}" @selected(($fEntidad ?? '') === $e)>{{ $e }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            @unless($isAsesor ?? false)
                <div class="xl:col-span-2">
                    <x-ui.select label="Asesor" name="asesor">
                        <option value="">Todos</option>
                        @foreach($asesores as $a)
                            <option value="{{ $a }}" @selected(($fAsesor ?? '') === $a)>{{ $a }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endunless

            <div class="flex gap-2 md:col-span-2 xl:col-span-2">
                <x-ui.button type="submit" class="flex-1">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18l-7.5 8.25V19.5l-3 1.5v-8.25L3 4.5Z" />
                    </svg>
                    Filtrar
                </x-ui.button>
                <x-ui.button href="{{ route('dashboard') }}" variant="secondary">Limpiar</x-ui.button>
            </div>
        </form>
    </section>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Promesas generadas" value="{{ number_format($pdpGen, 0) }}" hint="Compromisos del periodo" tone="primary" data-dashboard-kpi>
            <x-slot:icon>
                <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4.5h4.5M7.5 3.75h9A2.25 2.25 0 0 1 18.75 6v13.5l-3-2.25-3 2.25-3-2.25-3 2.25V6A2.25 2.25 0 0 1 7.5 3.75Z" />
                </svg>
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card label="Monto negociado" value="{{ $money($pdpMonto) }}" hint="Valor comprometido" tone="neutral" data-dashboard-kpi>
            <x-slot:icon>
                <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3.75-9.75c0-1.24-1.68-2.25-3.75-2.25s-3.75 1.01-3.75 2.25S9.93 10.5 12 10.5s3.75 1.01 3.75 2.25S14.07 15 12 15s-3.75-1.01-3.75-2.25" />
                </svg>
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card label="Pagos registrados" value="{{ number_format($pagosNum, 0) }}" hint="Abonos registrados" tone="warning" data-dashboard-kpi>
            <x-slot:icon>
                <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5v16.5l-5.25-3-5.25 3V3.75Zm2.5 5.25h5.5m-5.5 3h5.5" />
                </svg>
            </x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card label="Monto pagado" value="{{ $money($pagosMonto) }}" hint="Recaudación del periodo" tone="success" data-dashboard-kpi>
            <x-slot:icon>
                <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm2.25-7.5h3" />
                </svg>
            </x-slot:icon>
        </x-ui.stat-card>
    </div>

    <div class="grid gap-4 xl:grid-cols-12">
        <section class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)] xl:col-span-8">
            <header class="flex flex-wrap items-start justify-between gap-3 border-b border-kp-border px-4 py-3.5">
                <div>
                    <h2 class="text-[15px] font-semibold text-kp-ink">Evolución de pagos</h2>
                    <p class="mt-0.5 text-xs text-kp-muted">Monto recaudado en los últimos 12 meses.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-kp-border bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-500">
                    <span class="size-1.5 rounded-full bg-kp-green"></span>
                    12 MESES
                </span>
            </header>

            <div class="px-4 pb-4 pt-3">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-kp-muted">
                    <span>Corte al {{ $fecha_corte_texto ?? '-' }} · Día hábil {{ $dia_habil ?? 0 }}</span>
                    <span class="font-medium text-slate-500">{{ $periodoLabel }}</span>
                </div>

                <div class="relative h-[286px]">
                    <div class="absolute inset-0 z-10 grid place-items-center rounded-lg bg-white/80" data-dashboard-chart-loading>
                        <div class="w-full max-w-lg px-6" aria-hidden="true">
                            <div class="kp-skeleton h-2 w-2/3 rounded-full"></div>
                            <div class="mt-8 flex h-36 items-end gap-2">
                                @foreach([32, 46, 40, 58, 52, 68, 60, 75, 70, 82, 76, 92] as $bar)
                                    <div class="kp-skeleton flex-1 rounded-t" style="height: {{ $bar }}%"></div>
                                @endforeach
                            </div>
                            <div class="mt-3 kp-skeleton h-1.5 w-full rounded-full"></div>
                        </div>
                    </div>
                    @if($serieVacia)
                        <div class="absolute inset-0 z-20 flex flex-col items-center justify-center rounded-lg bg-white px-6 text-center" data-dashboard-chart-empty>
                            <div class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7.5 15l3-3 2.25 2.25L18 9" />
                                </svg>
                            </div>
                            <p class="mt-3 text-[13px] font-semibold text-kp-ink">Sin pagos en el periodo</p>
                            <p class="mt-1 max-w-sm text-[11px] leading-5 text-kp-muted">No hay montos pagados en los últimos 12 meses para los filtros seleccionados.</p>
                        </div>
                    @endif
                    <canvas id="linePagos12" role="img" aria-label="Evolución del monto pagado en los últimos 12 meses"></canvas>
                </div>
            </div>
        </section>

        <aside class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)] xl:col-span-4">
            <header class="border-b border-kp-border px-4 py-3.5">
                <div class="flex items-center gap-2">
                    <div class="flex size-7 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7.5 15l3-3 2.25 2.25L18 9" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-[15px] font-semibold text-kp-ink">Lectura del periodo</h2>
                        <p class="text-[11px] text-kp-muted">Indicadores calculados con los filtros actuales.</p>
                    </div>
                </div>
            </header>

            <div class="divide-y divide-kp-border px-4">
                <div class="flex items-center justify-between gap-4 py-3">
                    <span class="text-xs text-kp-muted">Promesas generadas</span>
                    <span class="text-[13px] font-semibold text-kp-ink">{{ number_format($pdpGen, 0) }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span class="text-xs text-kp-muted">Monto negociado</span>
                    <span class="text-[13px] font-semibold text-kp-ink">{{ $money($pdpMonto) }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span class="text-xs text-kp-muted">Pagos registrados</span>
                    <span class="text-[13px] font-semibold text-kp-ink">{{ number_format($pagosNum, 0) }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span class="text-xs text-kp-muted">Monto ingresado</span>
                    <span class="text-[13px] font-semibold text-kp-ink">{{ $money($pagosMonto) }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <span class="text-xs text-kp-muted">Ticket promedio</span>
                    <span class="text-[13px] font-semibold text-kp-ink">{{ $pagosNum > 0 ? $money($ticketPromedio) : '—' }}</span>
                </div>
            </div>

            <div class="m-4 rounded-lg border border-kp-border bg-kp-surface-muted p-3.5">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.05em] text-slate-500">Avance de recaudación</span>
                    <span class="text-[13px] font-semibold text-kp-primary-ink">{{ $avance !== null ? $avance.'%' : '—' }}</span>
                </div>
                <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-kp-primary transition-all" style="width: {{ $avance ?? 0 }}%"></div>
                </div>
                <p class="mt-2 text-[10.5px] leading-4 text-kp-muted">
                    {{ $avance !== null ? 'Relación entre el monto pagado y el monto negociado del periodo.' : 'No hay monto negociado suficiente para calcular el avance.' }}
                </p>
            </div>
        </aside>
    </div>

    <section class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]" data-top-entities>
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3.5">
            <div>
                <h2 class="text-[15px] font-semibold text-kp-ink">Top entidades</h2>
                <p class="mt-0.5 text-xs text-kp-muted">Comparativo de pagos de los últimos 3 meses.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500">{{ count($comparativoEntidades ?? []) }} entidades</span>
        </header>

        <x-tables.table :flush="true" :contained="true" min-width="min-w-[760px]" class="[&_th]:px-4 [&_td]:px-4">
            <thead>
                <tr>
                    <x-tables.th class="w-12">#</x-tables.th>
                    <x-tables.th>Entidad</x-tables.th>
                    <x-tables.th align="right">{{ $periodosComp['m2']['label'] ?? '-' }}</x-tables.th>
                    <x-tables.th align="right">{{ $periodosComp['m1']['label'] ?? '-' }}</x-tables.th>
                    <x-tables.th align="right">{{ $periodosComp['m0']['label'] ?? '-' }}</x-tables.th>
                    <x-tables.th align="right">Total 3M</x-tables.th>
                </tr>
            </thead>
            <tbody>
                @forelse($comparativoEntidades ?? [] as $fila)
                    <tr class="transition-colors hover:bg-slate-50/80">
                        <x-tables.td class="text-[11px] font-semibold text-slate-400">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</x-tables.td>
                        <x-tables.td>
                            <div class="font-semibold text-kp-ink">{{ $fila['nombre'] }}</div>
                        </x-tables.td>
                        <x-tables.td align="right" class="tabular-nums text-kp-muted">{{ $money($fila['m2']) }}</x-tables.td>
                        <x-tables.td align="right" class="tabular-nums text-kp-muted">{{ $money($fila['m1']) }}</x-tables.td>
                        <x-tables.td align="right" class="tabular-nums text-kp-muted">{{ $money($fila['m0']) }}</x-tables.td>
                        <x-tables.td align="right" class="font-semibold tabular-nums text-kp-ink">{{ $money($fila['total']) }}</x-tables.td>
                    </tr>
                @empty
                    <x-tables.empty-row :colspan="6" message="No hay pagos de entidades para mostrar con los filtros actuales." />
                @endforelse
            </tbody>
        </x-tables.table>
    </section>

    @unless($isAsesor ?? false)
        <section class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]" data-top-advisors>
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3.5">
                <div>
                    <h2 class="text-[15px] font-semibold text-kp-ink">Top asesores</h2>
                    <p class="mt-0.5 text-xs text-kp-muted">Comparativo de pagos de los últimos 3 meses.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500">{{ count($comparativoAsesores ?? []) }} asesores</span>
            </header>

            <x-tables.table :flush="true" :contained="true" min-width="min-w-[760px]" class="[&_th]:px-4 [&_td]:px-4">
                <thead>
                    <tr>
                        <x-tables.th class="w-12">#</x-tables.th>
                        <x-tables.th>Asesor</x-tables.th>
                        <x-tables.th align="right">{{ $periodosComp['m2']['label'] ?? '-' }}</x-tables.th>
                        <x-tables.th align="right">{{ $periodosComp['m1']['label'] ?? '-' }}</x-tables.th>
                        <x-tables.th align="right">{{ $periodosComp['m0']['label'] ?? '-' }}</x-tables.th>
                        <x-tables.th align="right">Total 3M</x-tables.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comparativoAsesores ?? [] as $fila)
                        <tr class="transition-colors hover:bg-slate-50/80">
                            <x-tables.td class="text-[11px] font-semibold text-slate-400">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</x-tables.td>
                            <x-tables.td>
                                <div class="font-semibold text-kp-ink">{{ $fila['nombre'] }}</div>
                            </x-tables.td>
                            <x-tables.td align="right" class="tabular-nums text-kp-muted">{{ $money($fila['m2']) }}</x-tables.td>
                            <x-tables.td align="right" class="tabular-nums text-kp-muted">{{ $money($fila['m1']) }}</x-tables.td>
                            <x-tables.td align="right" class="tabular-nums text-kp-muted">{{ $money($fila['m0']) }}</x-tables.td>
                            <x-tables.td align="right" class="font-semibold tabular-nums text-kp-ink">{{ $money($fila['total']) }}</x-tables.td>
                        </tr>
                    @empty
                        <x-tables.empty-row :colspan="6" message="No hay pagos de asesores para mostrar con los filtros actuales." />
                    @endforelse
                </tbody>
            </x-tables.table>
        </section>
    @endunless
</div>
@endsection
