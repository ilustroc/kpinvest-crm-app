@extends('layouts.app')
@section('title','Dashboard')
@section('crumb','Estadísticas')

@push('head')
  @vite([
    'resources/css/dashboard-stats.css',
    'resources/js/dashboard-stats.js'
  ])
@endpush

@section('content')
<div class="admin-compact space-y-4">

  {{-- FILTROS (ACORDEÓN) --}}
  <div class="kp-card p-5 dash-filters">
    <details class="group" open>
      <summary class="flex items-center justify-between cursor-pointer select-none">
        <div class="flex items-center gap-3">
          <span class="h-9 w-9 rounded-xl grid place-items-center bg-emerald-500/10 text-emerald-700">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
              <path d="M3 4h18"></path><path d="M6 8h12"></path><path d="M10 12h4"></path>
              <path d="M6 20h12"></path><path d="M9 16h6"></path>
            </svg>
          </span>
          <div>
            <div class="text-sm sm:text-base font-extrabold tracking-tight text-slate-900">Filtros</div>
            <div class="text-xs text-slate-500">Mes / cosecha / entidad / asesor</div>
          </div>
        </div>

        <span class="h-9 w-9 rounded-xl grid place-items-center bg-slate-100 text-slate-600 group-open:rotate-180 transition">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M6 9l6 6 6-6"/>
          </svg>
        </span>
      </summary>

      <form id="filtrosDash" class="mt-4" method="GET" action="{{ route('dashboard') }}">
        <div class="grid grid-cols-12 gap-3 items-end">

          <div class="col-span-12 md:col-span-3">
            <label class="kp-label">Mes</label>
            <input type="month" name="mes"
                   class="kp-input"
                   value="{{ $mes ?? request('mes', now()->format('Y-m')) }}">
          </div>

          <div class="col-span-12 md:col-span-3">
            <label class="kp-label">Cosecha</label>
            <select name="cosecha" class="kp-input">
              <option value="">Todas</option>
              @foreach($cosechas as $c)
                <option value="{{ $c }}" {{ ($fCosecha ?? '')===$c ? 'selected' : '' }}>{{ $c }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-span-12 md:col-span-3">
            <label class="kp-label">Entidad financiera</label>
            <select name="entidad" class="kp-input">
              <option value="">Todas</option>
              @foreach($entidades as $e)
                <option value="{{ $e }}" {{ ($fEntidad ?? '')===$e ? 'selected' : '' }}>{{ $e }}</option>
              @endforeach
            </select>
          </div>

          @unless($isAsesor ?? false)
          <div class="col-span-12 md:col-span-3">
            <label class="kp-label">Asesor</label>
            <select name="asesor" class="kp-input">
              <option value="">Todos</option>
              @foreach($asesores as $a)
                <option value="{{ $a }}" {{ ($fAsesor ?? '')===$a ? 'selected' : '' }}>{{ $a }}</option>
              @endforeach
            </select>
          </div>
          @endunless

        </div>

        <div class="flex flex-wrap items-center gap-2 pt-3">
          <button class="kp-btn kp-btn-primary" type="submit">
            <span class="inline-flex items-center gap-2">
              Filtrar 
            </span>
          </button>

          <a href="{{ route('dashboard') }}" class="kp-btn kp-btn-ghost">Limpiar</a>
        </div>
      </form>
    </details>
  </div>

  {{-- KPIs --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
    @foreach([
        ['# Promesas generadas', $k['pdp_gen'] ?? 0, 'promesas'],
        ['Monto negociado', $k['pdp_monto'] ?? 0, 'mto_neg'],
        ['# Pagos', $k['pagos_num'] ?? 0, 'pagos'],
        ['Monto pagado', $k['pagos_monto'] ?? 0, 'mto_pag']
    ] as $item)
      <div class="dash-kpi" data-kpi="{{ $item[2] }}">
        <div class="dash-kpi-top">
          <div class="dash-kpi-label">{{ $item[0] }}</div>
          <div class="dash-kpi-ic" aria-hidden="true"></div>
        </div>
        <div class="dash-kpi-value">
          @if(in_array($item[2], ['mto_neg','mto_pag']))
            S/ {{ number_format($item[1], 2) }}
          @else
            {{ number_format($item[1], 0) }}
          @endif
        </div>
      </div>
    @endforeach
  </div>

  {{-- VISUALES --}}
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">

    {{-- Evolución --}}
    <div class="kp-card p-5 dash-viz">
      <div class="flex items-start justify-between gap-3 mb-4">
        <div>
          <div class="dash-title">Evolución de Pagos</div>
          <div class="dash-sub" id="evSub">Monto total (12 meses)</div>
        </div>

        <div class="dash-seg">
          <button id="btnEv12" type="button" class="dash-seg-btn is-active">12 meses</button>
          <button id="btnEvMes" type="button" class="dash-seg-btn">Este mes</button>
        </div>
      </div>

      <div class="dash-chart h-[320px]">
        <canvas id="linePagos"></canvas>
      </div>
    </div>

    {{-- Top Entidades --}}
    <div class="kp-card p-5 dash-viz">
      <div>
        <div class="dash-title">Top Entidades</div>
        <div class="dash-sub">Participación por monto</div>
      </div>

      <div class="dash-chart h-[320px] mt-4">
        <canvas id="pieEntidades"></canvas>
      </div>
    </div>

  </div>

  {{-- Top Asesores --}}
  @unless($isAsesor ?? false)
  <div class="kp-card p-5 dash-viz">
    <div>
      <div class="dash-title">Top Asesores</div>
      <div class="dash-sub">Monto recuperado (S/)</div>
    </div>

    <div class="dash-chart mt-4" id="asesWrap" style="height: 360px;">
      <canvas id="barAsesores"></canvas>
    </div>
  </div>
  @endunless

</div>
@endsection

@push('scripts')
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
@endpush