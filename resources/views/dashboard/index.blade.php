{{-- resources/views/dashboard/index.blade.php --}}
@extends('layouts.app')
@section('title','Dashboard')
@section('crumb','Estadísticas')

@push('head')
<style>
  .sect{ display:flex; align-items:center; gap:.6rem; font-weight:700; margin:6px 0 10px }
  .sect::before{ content:""; width:8px; height:18px; border-radius:4px; background:var(--accent) }

  .kpi{ position:relative; background:var(--surface); border:1px solid var(--border);
        border-radius:12px; padding:16px; height:100%; display:flex; flex-direction:column; gap:6px }
  .kpi::before{ content:""; position:absolute; left:0; top:0; bottom:0; width:4px;
        background:linear-gradient(180deg, var(--accent), color-mix(in oklab, var(--accent) 65%, black));
        border-top-left-radius:12px; border-bottom-left-radius:12px; opacity:.95 }
  .kpi .label{ color:var(--muted); font-size:.9rem }
  .kpi .value{ font-weight:800; font-size:1.9rem; line-height:1 }

  .viz{ background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:14px; height:100% }
  .viz h6{ margin:0 0 10px; font-weight:700; color:var(--ink) }
  .viz .sub{ color:var(--muted); font-size:.9rem }

  .table-card{ background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:14px }
  .table thead th{ color:var(--muted); font-weight:600; border-color:var(--border) }
  .table tbody td{ border-color:var(--border) }
</style>
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

      <div class="col-12 col-md-3">
        <label class="form-label">Asesor</label>
        <select name="asesor" class="form-select">
          <option value="">Todos</option>
          @foreach($asesores as $a)
            <option value="{{ $a }}" {{ ($fAsesor ?? '')===$a ? 'selected' : '' }}>{{ $a }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label">Supervisor</label>
        <select name="supervisor_id" class="form-select">
          <option value="">Todos</option>
          @foreach($supervisores as $s)
            <option value="{{ $s->id }}" {{ (string)($supervisorId ?? request('supervisor_id')) === (string)$s->id ? 'selected' : '' }}>
              {{ $s->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-12 d-flex gap-2 mt-1">
        <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filtrar</button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Limpiar</a>
      </div>
    </div>
  </form>

  {{-- KPIs --}}
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mt-3">
    <div class="col"><div class="kpi"><div class="label">CCD generadas</div><div class="value">{{ $k['ccd_gen'] ?? 0 }}</div></div></div>
    <div class="col"><div class="kpi"><div class="label">Pagos (N°)</div><div class="value">{{ $k['pagos_num'] ?? 0 }}</div></div></div>
    <div class="col"><div class="kpi"><div class="label">Pagos (Monto)</div><div class="value">S/ {{ number_format($k['pagos_monto'] ?? 0,2) }}</div></div></div>
  </div>

  {{-- Visualizaciones --}}
  <div class="row g-3 mt-2">
    <div class="col-12 col-xl-6">
      <div class="viz">
        <h6>Evolución de Pagos</h6>
        <div class="sub mb-2">12 meses (Monto vs N°)</div>
        <canvas id="linePagos" height="170"></canvas>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="viz">
        <h6>Top Entidades (mes)</h6>
        <div class="sub mb-2">Participación por monto</div>
        <canvas id="pieEntidades" height="170"></canvas>
      </div>
    </div>

    <div class="col-12">
      <div class="viz">
        <h6>Top Asesores (mes)</h6>
        <div class="sub mb-2">Monto recuperado</div>
        <canvas id="barAsesores" height="220"></canvas>
      </div>
    </div>

    {{-- (Opcional) bloque de gestiones o tablas adicionales --}}
    <div class="col-12 col-xl-6">
      <div class="table-card">
        <h6 class="mb-2">Detalle de gestiones recientes</h6>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Fecha</th><th>Cliente</th><th>Gestión</th><th>Resultado</th></tr></thead>
            <tbody>
              @forelse(($gestiones ?? []) as $g)
                <tr><td>{{ $g->fecha ?? '-' }}</td><td>{{ $g->cliente ?? '-' }}</td><td>{{ $g->tipo ?? '-' }}</td><td>{{ $g->resultado ?? '-' }}</td></tr>
              @empty
                <tr><td colspan="4" class="text-center" style="color:var(--muted)">Sin gestiones recientes</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script>
(function(){
  const css  = (v)=>getComputedStyle(document.documentElement).getPropertyValue(v).trim();
  const col  = { accent: ()=> css('--accent'), brand: ()=> css('--brand'), muted: ()=> css('--muted'), border: ()=> css('--border') };

  const meses     = {!! json_encode($meses ?? []) !!};
  const serieMto  = {!! json_encode($serie_pagos_monto ?? []) !!};
  const serieNum  = {!! json_encode($serie_pagos_num ?? []) !!};
  const entLabels = {!! json_encode($entLabels ?? []) !!};
  const entData   = {!! json_encode($entData ?? []) !!};
  const asesLabels= {!! json_encode($asesLabels ?? []) !!};
  const asesData  = {!! json_encode($asesData ?? []) !!};

  // Auto-submit filtros
  document.querySelectorAll('#filtrosDash input[name="mes"], #filtrosDash select')
    .forEach(el => el.addEventListener('change', () => document.getElementById('filtrosDash').requestSubmit()));

  // LINE+BAR: monto (linea) y # (barras)
  const ctxL = document.getElementById('linePagos');
  new Chart(ctxL, {
    data:{
      labels: meses,
      datasets:[
        { type:'bar',  label:'# Pagos',    data: serieNum,  borderWidth:1 },
        { type:'line', label:'Monto (S/)', data: serieMto,  tension:.35, borderWidth:2, pointRadius:2 }
      ]
    },
    options:{
      plugins:{ legend:{ display:true } },
      scales:{
        x:{ ticks:{ color: col.muted() }, grid:{ color: col.border() } },
        y:{ ticks:{ color: col.muted() }, grid:{ color: col.border() }, beginAtZero:true }
      }
    }
  });

  // PIE: entidades (monto mes)
  const ctxP = document.getElementById('pieEntidades');
  new Chart(ctxP, {
    type:'doughnut',
    data:{ labels: entLabels, datasets:[{ data: entData }]},
    options:{ plugins:{ legend:{ position:'bottom' } } }
  });

  // BAR HORIZONTAL: asesores (monto mes)
  const ctxB = document.getElementById('barAsesores');
  new Chart(ctxB, {
    type:'bar',
    data:{ labels: asesLabels, datasets:[{ data: asesData }] },
    options:{
      indexAxis:'y',
      plugins:{ legend:{ display:false }},
      scales:{
        x:{ ticks:{ color: col.muted() }, grid:{ color: col.border() }, beginAtZero:true },
        y:{ ticks:{ color: col.muted() }, grid:{ display:false } }
      }
    }
  });
})();
</script>
@endpush
