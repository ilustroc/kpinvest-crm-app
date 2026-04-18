@extends('layouts.app')
@section('title', 'Dashboard')
@section('crumb', 'Estadísticas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/dashboard-stats.css') }}?v={{ time() }}">
@endpush

@section('content')
@php
    $dashboardJson = [
        'meses'    => $meses ?? [],
        'serieMto' => $serie_pagos_monto ?? [],
    ];
@endphp

<form id="filtrosDash" class="dash-card dash-filters" method="GET" action="{{ route('dashboard') }}">
    <div class="row g-3">
        <div class="col-12 col-md-3">
            <label class="form-label">Mes</label>
            <input type="month" name="mes" class="form-control" value="{{ $mes ?? now()->format('Y-m') }}">
        </div>

        <div class="col-12 col-md-2">
            <label class="form-label">Día hábil</label>
            <input type="number" min="0" step="1" name="dia_habil" class="form-control" value="{{ $dia_habil ?? 0 }}">
        </div>

        <div class="col-12 col-md-2">
            <label class="form-label">Cosecha</label>
            <select name="cosecha" class="form-select">
                <option value="">Todas</option>
                @foreach($cosechas as $c)
                    <option value="{{ $c }}" {{ ($fCosecha ?? '') === $c ? 'selected' : '' }}>{{ $c }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-12 col-md-2">
            <label class="form-label">Entidad financiera</label>
            <select name="entidad" class="form-select">
                <option value="">Todas</option>
                @foreach($entidades as $e)
                    <option value="{{ $e }}" {{ ($fEntidad ?? '') === $e ? 'selected' : '' }}>{{ $e }}</option>
                @endforeach
            </select>
        </div>

        @unless($isAsesor ?? false)
        <div class="col-12 col-md-3">
            <label class="form-label">Asesor</label>
            <select name="asesor" class="form-select">
                <option value="">Todos</option>
                @foreach($asesores as $a)
                    <option value="{{ $a }}" {{ ($fAsesor ?? '') === $a ? 'selected' : '' }}>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        @endunless

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-success">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </div>
</form>

<div class="row g-3 mt-1">
    @foreach([
        ['# Promesas generadas', $k['pdp_gen'] ?? 0, false],
        ['Monto negociado', $k['pdp_monto'] ?? 0, true],
        ['# Pagos', $k['pagos_num'] ?? 0, false],
        ['Monto pagado', $k['pagos_monto'] ?? 0, true],
    ] as [$label, $value, $isMoney])
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi">
                <div class="label">{{ $label }}</div>
                <div class="value">
                    {{ $isMoney ? 'S/ ' . number_format((float) $value, 2) : number_format((float) $value, 0) }}
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="dash-card">
            <div class="dash-card__head">
                <div>
                    <h5 class="dash-card__title">Evolución de Pagos</h5>
                    <p class="dash-card__subtitle">
                        Últimos 12 meses · Día hábil {{ $dia_habil ?? 0 }} · Corte {{ $fecha_corte_texto ?? '-' }}
                    </p>
                </div>

                <div class="dash-chip">12 meses</div>
            </div>

            <div class="chart-wrap">
                <canvas id="linePagos12"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="dash-card">
            <div class="dash-card__head">
                <div>
                    <h5 class="dash-card__title">Top Entidades</h5>
                    <p class="dash-card__subtitle">Comparativo últimos 3 meses</p>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table table-compare mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Entidad</th>
                            <th class="text-end">{{ $periodosComp['m2']['label'] ?? '-' }}</th>
                            <th class="text-end">{{ $periodosComp['m1']['label'] ?? '-' }}</th>
                            <th class="text-end">{{ $periodosComp['m0']['label'] ?? '-' }}</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($comparativoEntidades ?? [] as $fila)
                            <tr>
                                <td class="fw-semibold">{{ $fila['nombre'] }}</td>
                                <td class="text-end">S/ {{ number_format($fila['m2'], 2) }}</td>
                                <td class="text-end">S/ {{ number_format($fila['m1'], 2) }}</td>
                                <td class="text-end">S/ {{ number_format($fila['m0'], 2) }}</td>
                                <td class="text-end fw-bold">S/ {{ number_format($fila['total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Sin datos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @unless($isAsesor ?? false)
    <div class="col-12">
        <div class="dash-card">
            <div class="dash-card__head">
                <div>
                    <h5 class="dash-card__title">Top Asesores</h5>
                    <p class="dash-card__subtitle">Comparativo últimos 3 meses</p>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table table-compare mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Asesor</th>
                            <th class="text-end">{{ $periodosComp['m2']['label'] ?? '-' }}</th>
                            <th class="text-end">{{ $periodosComp['m1']['label'] ?? '-' }}</th>
                            <th class="text-end">{{ $periodosComp['m0']['label'] ?? '-' }}</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($comparativoAsesores ?? [] as $fila)
                            <tr>
                                <td class="fw-semibold">{{ $fila['nombre'] }}</td>
                                <td class="text-end">S/ {{ number_format($fila['m2'], 2) }}</td>
                                <td class="text-end">S/ {{ number_format($fila['m1'], 2) }}</td>
                                <td class="text-end">S/ {{ number_format($fila['m0'], 2) }}</td>
                                <td class="text-end fw-bold">S/ {{ number_format($fila['total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Sin datos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endunless
</div>
@endsection

@push('scripts')
    <script id="dashboard-json" type="application/json">{!! json_encode($dashboardJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
    <script src="{{ asset('js/dashboard-stats.js') }}?v={{ time() }}"></script>
@endpush