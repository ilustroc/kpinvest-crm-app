@extends('layouts.app')
@section('title','Dashboard')
@section('crumb','Estadísticas')

@push('head')
  <link rel="stylesheet"
        href="{{ asset('css/dashboard-stats.css') }}?v={{ @filemtime(public_path('css/dashboard-stats.css')) }}">
@endpush
@section('content')
  {{-- Filtros --}}
  <form id="filtrosDash" class="card pad" method="GET" action="{{ route('dashboard') }}">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <label class="form-label">Mes</label>
        <input type="month" name="mes" class="form-control" value="{{ $mes ?? request('mes', now()->format('Y-m')) }}">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Cosecha</label>
        <select name="cosecha" class="form-select">
          <option value="">Todas</option>
          @foreach($cosechas as $c)
            <option value="{{ $c }}" {{ ($fCosecha ?? '')===$c ? 'selected' : '' }}>{{ $c }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Entidad financiera</label>
        <select name="entidad" class="form-select">
          <option value="">Todas</option>
          @foreach($entidades as $e)
            <option value="{{ $e }}" {{ ($fEntidad ?? '')===$e ? 'selected' : '' }}>{{ $e }}</option>
          @endforeach
        </select>
      </div>

      @unless($isAsesor ?? false)
      <div class="col-12 col-md-3">
        <label class="form-label">Asesor</label>
        <select name="asesor" class="form-select">
          <option value="">Todos</option>
          @foreach($asesores as $a)
            <option value="{{ $a }}" {{ ($fAsesor ?? '')===$a ? 'selected' : '' }}>{{ $a }}</option>
          @endforeach
        </select>
      </div>
      @endunless

      <div class="col-12 d-flex gap-2 mt-1">
        <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filtrar</button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Limpiar</a>
      </div>
    </div>
  </form>

  {{-- KPIs --}}
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mt-3">
    @foreach([
        ['# Promesas generadas', $k['pdp_gen'] ?? 0, ''],
        ['Monto negociado', $k['pdp_monto'] ?? 0, 'S/ '],
        ['# Pagos', $k['pagos_num'] ?? 0, ''],
        ['Monto pagado', $k['pagos_monto'] ?? 0, 'S/ ']
    ] as $item)
    <div class="col">
      <div class="kpi">
        <div class="label">{{ $item[0] }}</div>
        <div class="value">{{ $item[2] }}{{ is_numeric($item[1]) ? number_format($item[1], ($item[2] ? 2 : 0)) : $item[1] }}</div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Visualizaciones --}}
  <div class="row g-3 mt-2">
    {{-- Cuadro Evolución de Pagos --}}
    <div class="col-12 col-xl-6">
      <div class="viz card pad">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h6 class="mb-0">Evolución de Pagos</h6>
            <div class="sub">Monto total</div>
          </div>
          <div class="mini-tabs">
            <button id="btnEv12" class="btn btn-outline-primary btn-sm active">12 meses</button>
            <button id="btnEvMes" class="btn btn-outline-primary btn-sm">Este mes</button>
          </div>
        </div>
        <div class="chart-container">
          <canvas id="linePagos12"></canvas>
          <canvas id="linePagosDia" class="d-none"></canvas>
        </div>
      </div>
    </div>

    {{-- Cuadro Top Entidades --}}
    <div class="col-12 col-xl-6">
      <div class="viz card pad">
        <h6 class="mb-0">Top Entidades</h6>
        <div class="sub mb-3">Participación por monto</div>
        <div class="chart-container">
            <canvas id="pieEntidades"></canvas>
        </div>
      </div>
    </div>

    {{-- Cuadro Top Asesores --}}
    @unless($isAsesor ?? false)
    <div class="col-12">
      <div class="viz card pad">
        <h6 class="mb-0">Top Asesores</h6>
        <div class="sub mb-3">Monto recuperado (S/)</div>
        <div class="chart-wrap--ases">
            <canvas id="barAsesores"></canvas>
        </div>
      </div>
    </div>
    @endunless
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>

  <script>
    window.DASHBOARD_DATA = {
      meses: @json($meses ?? []),
      serieMto: @json($serie_pagos_monto ?? []),
      dias: @json($dias ?? []),
      serieDia: @json($serie_pagos_dia ?? []),
      entLabels: @json($entLabels ?? []),
      entData: @json($entData ?? []),
      asesLabels: @json($asesLabels ?? []),
      asesData: @json($asesData ?? []),
      hasTopAses: {{ ($isAsesor ?? false) ? 'false' : 'true' }},
    };
  </script>

  <script defer src="{{ asset('js/dashboard-stats.js') }}?v={{ @filemtime(public_path('js/dashboard-stats.js')) }}"></script>
@endpush