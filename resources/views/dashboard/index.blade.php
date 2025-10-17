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

  /* alturas controladas */
  .chart-wrap{ position:relative; width:100%; height:260px; }
  .chart-wrap--ases{ height:280px; }

  /* tabs de la evolución */
  .mini-tabs{ display:flex; gap:6px }
  .mini-tabs .btn{ --bs-btn-padding-y:.175rem; --bs-btn-padding-x:.55rem; --bs-btn-font-size:.8rem }
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

      <div class="col-12 d-flex gap-2 mt-1">
        <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filtrar</button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Limpiar</a>
      </div>
    </div>
  </form>

  {{-- KPIs --}}
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mt-3">
    <div class="col">
      <div class="kpi">
        <div class="label"># Promesas de pago generadas</div>
        <div class="value">{{ $k['pdp_gen'] ?? 0 }}</div>
      </div>
    </div>
    <div class="col">
      <div class="kpi">
        <div class="label">Promesas de pago (monto negociado)</div>
        <div class="value">S/ {{ number_format($k['pdp_monto'] ?? 0, 2) }}</div>
      </div>
    </div>
    <div class="col">
      <div class="kpi">
        <div class="label"># Pagos</div>
        <div class="value">{{ $k['pagos_num'] ?? 0 }}</div>
      </div>
    </div>
    <div class="col">
      <div class="kpi">
        <div class="label">Pagos (monto)</div>
        <div class="value">S/ {{ number_format($k['pagos_monto'] ?? 0,2) }}</div>
      </div>
    </div>
  </div>

  {{-- Visualizaciones --}}
  <div class="row g-3 mt-2">
    {{-- Evolución de pagos (dos vistas) --}}
    <div class="col-12 col-xl-6">
      <div class="viz">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mb-0">Evolución de Pagos</h6>
            <div class="sub">Monto total</div>
          </div>
          <div class="mini-tabs" role="tablist" aria-label="evol-tabs">
            <button id="btnEv12" class="btn btn-outline-primary btn-sm active" type="button">12 meses</button>
            <button id="btnEvMes" class="btn btn-outline-primary btn-sm" type="button">Este mes (diario)</button>
          </div>
        </div>
        <div class="mt-2 chart-wrap">
          <canvas id="linePagos12"></canvas>
          <canvas id="linePagosDia" class="d-none"></canvas>
        </div>
      </div>
    </div>

    {{-- Top entidades --}}
    <div class="col-12 col-xl-6">
      <div class="viz">
        <h6>Top Entidades (mes)</h6>
        <div class="sub mb-2">Participación por monto</div>
        <div class="chart-wrap"><canvas id="pieEntidades"></canvas></div>
      </div>
    </div>

    {{-- Top asesores --}}
    <div class="col-12">
      <div class="viz">
        <h6>Top Asesores (mes)</h6>
        <div class="sub mb-2">Monto recuperado</div>
        <div class="chart-wrap chart-wrap--ases"><canvas id="barAsesores"></canvas></div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script>
(function(){
  const css  = (v)=>getComputedStyle(document.documentElement).getPropertyValue(v).trim();

  const meses    = {!! json_encode($meses ?? []) !!};
  const serieMto = {!! json_encode($serie_pagos_monto ?? []) !!};

  const dias     = {!! json_encode($dias ?? []) !!};
  const serieDia = {!! json_encode($serie_pagos_dia ?? []) !!};

  const entLabels = {!! json_encode($entLabels ?? []) !!};
  const entData   = {!! json_encode($entData ?? []) !!};
  const asesLabels= {!! json_encode($asesLabels ?? []) !!};
  const asesData  = {!! json_encode($asesData ?? []) !!};

  // Auto-submit filtros al cambiar
  document.querySelectorAll('#filtrosDash input[name="mes"], #filtrosDash select')
    .forEach(el => el.addEventListener('change', () => document.getElementById('filtrosDash').requestSubmit()));

  // ===== Evolución 12M (monto) =====
  const ch12 = new Chart(document.getElementById('linePagos12'), {
    type:'line',
    data:{ labels: meses, datasets:[{ label:'Monto (S/)', data: serieMto, tension:.35, borderWidth:2, pointRadius:2 }]},
    options:{
      maintainAspectRatio:false,
      plugins:{ legend:{ display:true }},
      scales:{
        x:{ grid:{ color: css('--border') } },
        y:{ grid:{ color: css('--border') }, beginAtZero:true }
      }
    }
  });

  // ===== Evolución del MES (diario) =====
  const chDia = new Chart(document.getElementById('linePagosDia'), {
    type:'line',
    data:{ labels: dias, datasets:[{ label:'Monto (S/)', data: serieDia, tension:.35, borderWidth:2, pointRadius:2 }]},
    options:{
      maintainAspectRatio:false,
      plugins:{ legend:{ display:true }},
      scales:{
        x:{ grid:{ color: css('--border') } },
        y:{ grid:{ color: css('--border') }, beginAtZero:true }
      }
    }
  });

  // Toggle entre 12M y Diario
  const btn12 = document.getElementById('btnEv12');
  const btnM  = document.getElementById('btnEvMes');
  const cv12  = document.getElementById('linePagos12');
  const cvM   = document.getElementById('linePagosDia');

  function setTab(which){
    const is12 = which === '12';
    cv12.classList.toggle('d-none', !is12);
    cvM.classList.toggle('d-none',  is12);
    btn12.classList.toggle('active', is12);
    btnM.classList.toggle('active',  !is12);
    // redibujar por si el canvas estuvo oculto
    (is12 ? ch12 : chDia).resize();
  }
  btn12.addEventListener('click', ()=> setTab('12'));
  btnM .addEventListener('click', ()=> setTab('M'));
  setTab('12'); // por defecto

  // ===== DOUGHNUT: Entidades =====
  new Chart(document.getElementById('pieEntidades'), {
    type:'doughnut',
    data:{ labels: entLabels, datasets:[{ data: entData }]},
    options:{
      maintainAspectRatio:false,
      plugins:{ legend:{ position:'bottom' } },
      cutout:'58%'
    }
  });

  // ===== BAR HORIZONTAL: Asesores =====
  new Chart(document.getElementById('barAsesores'), {
    type:'bar',
    data:{ labels: asesLabels, datasets:[{ data: asesData }] },
    options:{
      maintainAspectRatio:false,
      indexAxis:'y',
      plugins:{ legend:{ display:false }},
      scales:{
        x:{ grid:{ color: css('--border') }, beginAtZero:true },
        y:{ grid:{ display:false } }
      }
    }
  });
})();
</script>
@endpush
