@extends('layouts.app')
@section('tailwind_only', true)
@section('title', 'Dashboard')
@section('crumb', 'Dashboard')

@section('content')
@php
    $dashboardJson = [
        'meses' => $meses ?? [],
        'serieMto' => $serie_pagos_monto ?? [],
    ];

    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
@endphp

<div class="space-y-4" data-dashboard-page>
    <script id="dashboard-json" type="application/json">{!! json_encode($dashboardJson, JSON_UNESCAPED_SLASHES) !!}</script>

    <x-ui.card>
        <form id="filtrosDash" class="grid gap-3 lg:grid-cols-12 lg:items-end" method="GET" action="{{ route('dashboard') }}">
            <div class="lg:col-span-2">
                <x-ui.date label="Mes" type="month" name="mes" value="{{ $mes ?? now()->format('Y-m') }}" />
            </div>

            <div class="lg:col-span-2">
                <x-ui.input label="Dia habil" type="number" min="0" step="1" name="dia_habil" value="{{ $dia_habil ?? 0 }}" />
            </div>

            <div class="lg:col-span-2">
                <x-ui.select label="Cosecha" name="cosecha">
                    <option value="">Todas</option>
                    @foreach($cosechas as $c)
                        <option value="{{ $c }}" @selected(($fCosecha ?? '') === $c)>{{ $c }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="lg:col-span-2">
                <x-ui.select label="Entidad financiera" name="entidad">
                    <option value="">Todas</option>
                    @foreach($entidades as $e)
                        <option value="{{ $e }}" @selected(($fEntidad ?? '') === $e)>{{ $e }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            @unless($isAsesor ?? false)
                <div class="lg:col-span-2">
                    <x-ui.select label="Asesor" name="asesor">
                        <option value="">Todos</option>
                        @foreach($asesores as $a)
                            <option value="{{ $a }}" @selected(($fAsesor ?? '') === $a)>{{ $a }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endunless

            <div class="flex gap-2 lg:col-span-2">
                <x-ui.button type="submit" class="flex-1">Filtrar</x-ui.button>
                <x-ui.button href="{{ route('dashboard') }}" variant="secondary">Limpiar</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Promesas generadas', $k['pdp_gen'] ?? 0, false],
            ['Monto negociado', $k['pdp_monto'] ?? 0, true],
            ['Pagos registrados', $k['pagos_num'] ?? 0, false],
            ['Monto pagado', $k['pagos_monto'] ?? 0, true],
        ] as [$label, $value, $isMoney])
            <x-ui.card>
                <div class="text-sm font-semibold text-kp-muted">{{ $label }}</div>
                <div class="mt-2 text-2xl font-black text-kp-ink">
                    {{ $isMoney ? $money($value) : number_format((float) $value, 0) }}
                </div>
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card title="Evolucion de pagos" subtitle="Ultimos 12 meses segun filtros aplicados.">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm text-kp-muted">
                Dia habil {{ $dia_habil ?? 0 }}. Corte {{ $fecha_corte_texto ?? '-' }}.
            </div>
            <x-ui.badge variant="success">12 meses</x-ui.badge>
        </div>
        <div class="h-80">
            <canvas id="linePagos12"></canvas>
        </div>
    </x-ui.card>

    <x-ui.card title="Top entidades" subtitle="Comparativo de los ultimos 3 meses.">
        <x-ui.table>
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-kp-muted">Entidad</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-kp-muted">{{ $periodosComp['m2']['label'] ?? '-' }}</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-kp-muted">{{ $periodosComp['m1']['label'] ?? '-' }}</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-kp-muted">{{ $periodosComp['m0']['label'] ?? '-' }}</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-kp-muted">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-kp-border bg-white">
                @forelse($comparativoEntidades ?? [] as $fila)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-semibold text-kp-ink">{{ $fila['nombre'] }}</td>
                        <td class="px-4 py-3 text-right text-kp-muted">{{ $money($fila['m2']) }}</td>
                        <td class="px-4 py-3 text-right text-kp-muted">{{ $money($fila['m1']) }}</td>
                        <td class="px-4 py-3 text-right text-kp-muted">{{ $money($fila['m0']) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-kp-ink">{{ $money($fila['total']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6">
                            <x-ui.empty-state title="Sin datos" message="No hay pagos para mostrar con los filtros actuales." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    @unless($isAsesor ?? false)
        <x-ui.card title="Top asesores" subtitle="Comparativo de los ultimos 3 meses.">
            <x-ui.table>
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-kp-muted">Asesor</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-kp-muted">{{ $periodosComp['m2']['label'] ?? '-' }}</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-kp-muted">{{ $periodosComp['m1']['label'] ?? '-' }}</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-kp-muted">{{ $periodosComp['m0']['label'] ?? '-' }}</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-kp-muted">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-kp-border bg-white">
                    @forelse($comparativoAsesores ?? [] as $fila)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-semibold text-kp-ink">{{ $fila['nombre'] }}</td>
                            <td class="px-4 py-3 text-right text-kp-muted">{{ $money($fila['m2']) }}</td>
                            <td class="px-4 py-3 text-right text-kp-muted">{{ $money($fila['m1']) }}</td>
                            <td class="px-4 py-3 text-right text-kp-muted">{{ $money($fila['m0']) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-kp-ink">{{ $money($fila['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6">
                                <x-ui.empty-state title="Sin datos" message="No hay asesores para mostrar con los filtros actuales." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endunless
</div>
@endsection
