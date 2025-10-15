@extends('layouts.app')
@section('title','Dashboard')
@section('crumb','Estadísticas')

@push('head')
<style>
  /* ===== Identidad KP: usa variables globales del layout (verde/azul) ===== */
  .sect{ display:flex; align-items:center; gap:.6rem; font-weight:700; margin:6px 0 10px }
  .sect::before{ content:""; width:8px; height:18px; border-radius:4px; background:var(--accent) } /* azul */

  .kpi{
    position:relative; background:var(--surface); border:1px solid var(--border);
    border-radius:12px; padding:16px; height:100%;
    display:flex; flex-direction:column; justify-content:center; gap:6px;
  }
  .kpi::before{
    content:""; position:absolute; left:0; top:0; bottom:0; width:4px;
    background:linear-gradient(180deg, var(--accent), color-mix(in oklab, var(--accent) 65%, black));
    border-top-left-radius:12px; border-bottom-left-radius:12px; opacity:.95;
  }
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
  {{-- Toolbar de filtros --}}
  <form id="filtrosDash" class="card pad" method="GET" action="{{ route('dashboard') }}">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Mes</label>
        <input
          type="month"
          name="mes"
          class="form-control"
          value="{{ $mes ?? request('mes', now()->format('Y-m')) }}"
        >
      </div>

      {{-- Cartera (fijo: General) --}}
      <div class="col-md-4">
        <label class="form-label">Cartera</label>
        <input type="text" class="form-control" value="General" readonly>
      </div>

      <div class="col-md-4">
        <label class="form-label">Supervisor</label>
        <select name="supervisor_id" class="form-select">
          <option value="">Todos los supervisores</option>
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

  {{-- KPIs fila 1 --}}
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mt-3">
    <div class="col"><div class="kpi h-100"><div class="label">CCD generadas</div><div class="value">{{ $k['ccd_gen'] ?? 0 }}</div></div></div>
    <div class="col"><div class="kpi h-100"><div class="label">Pagos (N°)</div><div class="value">{{ $k['pagos_num'] ?? 0 }}</div></div></div>
    <div class="col"><div class="kpi h-100"><div class="label">Pagos (Monto)</div><div class="value">S/ {{ number_format($k['pagos_monto'] ?? 0,2) }}</div></div></div>
  </div>

  {{-- KPIs fila 2 (PDP) --}}
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mt-2">
    <div class="col"><div class="kpi h-100"><div class="label">PDP generadas</div><div class="value">{{ $k['pdp_gen'] ?? 0 }}</div></div></div>
    <div class="col"><div class="kpi h-100"><div class="label">PDP vigentes</div><div class="value">{{ $k['pdp_vig'] ?? 0 }}</div></div></div>
    <div class="col"><div class="kpi h-100"><div class="label">PDP cumplidas</div><div class="value">{{ $k['pdp_cumpl'] ?? 0 }}</div></div></div>
    <div class="col"><div class="kpi h-100"><div class="label">PDP caídas</div><div class="value">{{ $k['pdp_caidas'] ?? 0 }}</div></div></div>
  </div>

  {{-- Visualizaciones --}}
  <div class="row g-3 mt-2">
    <div class="col-12 col-xl-6">
      <div class="viz">
        <h6>Evolución de Pagos</h6><div class="sub mb-2">Últimos 12 meses</div>
        <canvas id="linePagos" height="160"></canvas>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="viz">
        <h6>Cumplimiento de Promesas</h6><div class="sub mb-2">Generadas vs Cumplidas vs Caídas</div>
        <canvas id="barPDP" height="160"></canvas>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="viz">
        <h6>% Cumplimiento</h6><div class="sub mb-2">Cumplidas / Generadas</div>
        <canvas id="gaugePDP" height="200"></canvas>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="table-card">
        <h6 class="mb-2">Detalle de gestiones recientes</h6>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Gestión</th>
                <th>Resultado</th>
              </tr>
            </thead>
            <tbody>
              @forelse(($gestiones ?? []) as $g)
                <tr>
                  <td>{{ $g->fecha ?? '-' }}</td>
                  <td>{{ $g->cliente ?? '-' }}</td>
                  <td>{{ $g->tipo ?? '-' }}</td>
                  <td>{{ $g->resultado ?? '-' }}</td>
                </tr>
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
  const col  = {
    accent: ()=> css('--accent'),   // azul
    brand:  ()=> css('--brand'),    // verde
    muted:  ()=> css('--muted'),
    border: ()=> css('--border')
  };

  const meses      = {!! json_encode($meses ?? ['E','F','M','A','M','J','J','A','S','O','N','D']) !!};
  const pagosSerie = {!! json_encode($serie_pagos ?? array_fill(0,12,0)) !!};
  const pdpGen  = {{ (int)($k['pdp_gen'] ?? 0) }};
  const pdpCum  = {{ (int)($k['pdp_cumpl'] ?? 0) }};
  const pdpCai  = {{ (int)($k['pdp_caidas'] ?? 0) }};
  const pctCumpl = (pdpGen>0)? Math.round((pdpCum/pdpGen)*100):0;

  // Auto-submit en cambios de filtros (mes/supervisor)
  document.querySelectorAll('#filtrosDash input[name="mes"], #filtrosDash select')
    .forEach(el => el.addEventListener('change', () => document.getElementById('filtrosDash').requestSubmit()));

  // LINE: Pagos
  const ctxL = document.getElementById('linePagos');
  const line = new Chart(ctxL, {
    type:'line',
    data:{ labels:meses, datasets:[{ label:'Pagos', data:pagosSerie, tension:.35, borderWidth:2, pointRadius:2 }]},
    options:{
      plugins:{ legend:{display:false} },
      scales:{
        x:{ ticks:{ color: col.muted() }, grid:{ color: col.border() } },
        y:{ ticks:{ color: col.muted() }, grid:{ color: col.border() } }
      }
    }
  });

  // BAR: Generadas / Cumplidas / Caídas
  const ctxB = document.getElementById('barPDP');
  const bar = new Chart(ctxB, {
    type:'bar',
    data:{ labels:['Generadas','Cumplidas','Caídas'], datasets:[{ data:[pdpGen,pdpCum,pdpCai]}] },
    options:{
      plugins:{ legend:{display:false} },
      scales:{
        x:{ ticks:{ color: col.muted() }, grid:{ display:false } },
        y:{ ticks:{ color: col.muted() }, grid:{ color: col.border() }, beginAtZero:true }
      }
    }
  });

  // GAUGE: Cumplimiento
  const ctxG = document.getElementById('gaugePDP');
  const gauge = new Chart(ctxG, {
    type:'doughnut',
    data:{ labels:['Cumplido','Pendiente'], datasets:[{ data:[pctCumpl, 100-pctCumpl], cutout:'70%' }]},
    options:{ rotation:-90, circumference:180, plugins:{ legend:{display:false}, tooltip:{enabled:false} } }
  });

  // Colores desde CSS vars
  function colorize(){
    const a = col.accent(), g = col.brand(), m = col.muted(), b = col.border();
    line.data.datasets[0].borderColor = a; line.data.datasets[0].backgroundColor = a;
    line.options.scales.x.ticks.color = m; line.options.scales.y.ticks.color = m;
    line.options.scales.x.grid.color  = b; line.options.scales.y.grid.color  = b;

    bar.data.datasets[0].backgroundColor = [a, g, 'color-mix(in oklab, '+a+' 35%, white)'];
    bar.options.scales.x.ticks.color = m; bar.options.scales.y.ticks.color = m; bar.options.scales.y.grid.color = b;

    gauge.data.datasets[0].backgroundColor = [g, b];

    line.update(); bar.update(); gauge.update();
  }
  colorize();
})();
</script>
@endpush
