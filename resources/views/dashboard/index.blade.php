@extends('layouts.app')
@section('title','Dashboard')
@section('crumb','Estadísticas')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/dashboard-stats.css') }}?v={{ time() }}">
    <style>
        /* Ajuste para que los cuadros tengan la misma altura */
        .viz { height: 100%; min-height: 380px; display: flex; flex-direction: column; }
        .chart-container { flex-grow: 1; position: relative; }
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
(function(){
    const data = {
        meses: {!! json_encode($meses ?? []) !!},
        serieMto: {!! json_encode($serie_pagos_monto ?? []) !!},
        dias: {!! json_encode($dias ?? []) !!},
        serieDia: {!! json_encode($serie_pagos_dia ?? []) !!},
        entLabels: {!! json_encode($entLabels ?? []) !!},
        entData: {!! json_encode($entData ?? []) !!},
        asesLabels: {!! json_encode($asesLabels ?? []) !!},
        asesData: {!! json_encode($asesData ?? []) !!},
        hasTopAses: {{ $isAsesor ? 'false' : 'true' }}
    };

    Chart.register(ChartDataLabels);
    const brandColor = '#00a81c';

    // 1. Gráfico Evolución 12 Meses
    const ctx12 = document.getElementById('linePagos12').getContext('2d');
    const chLine12 = new Chart(ctx12, {
        type: 'line',
        data: { 
            labels: data.meses, 
            datasets: [{ label: 'Monto (S/)', data: data.serieMto, tension: 0.3, borderColor: brandColor, backgroundColor: brandColor + '15', fill: true }] 
        },
        options: { maintainAspectRatio: false, plugins: { datalabels: { display: false } } }
    });

    // 2. Gráfico Evolución Este Mes (Diario) - INICIALMENTE OCULTO
    const ctxDia = document.getElementById('linePagosDia').getContext('2d');
    const chLineDia = new Chart(ctxDia, {
        type: 'line',
        data: { 
            labels: data.dias, 
            datasets: [{ label: 'Monto Diario (S/)', data: data.serieDia, tension: 0.3, borderColor: '#16a34a', backgroundColor: '#16a34a15', fill: true }] 
        },
        options: { maintainAspectRatio: false, plugins: { datalabels: { display: false } } }
    });

    // LÓGICA DE LOS BOTONES PARA CAMBIAR VISTA
    const btn12 = document.getElementById('btnEv12');
    const btnMes = document.getElementById('btnEvMes');
    const canv12 = document.getElementById('linePagos12');
    const canvDia = document.getElementById('linePagosDia');

    btnMes.addEventListener('click', () => {
        canv12.classList.add('d-none');
        canvDia.classList.remove('d-none');
        btnMes.classList.add('active');
        btn12.classList.remove('active');
        chLineDia.update(); // Actualizar para renderizar correctamente
    });

    btn12.addEventListener('click', () => {
        canvDia.classList.add('d-none');
        canv12.classList.remove('d-none');
        btn12.classList.add('active');
        btnMes.classList.remove('active');
        chLine12.update();
    });

    // 3. Donut Entidades
    new Chart(document.getElementById('pieEntidades'), {
        type: 'doughnut',
        data: { 
            labels: data.entLabels, 
            datasets: [{ data: data.entData, backgroundColor: [brandColor, '#16a34a', '#22c55e', '#4ade80', '#86efac'] }] 
        },
        options: { maintainAspectRatio: false, cutout: '70%', plugins: { datalabels: { display: false }, legend: { position: 'bottom' } } }
    });

    // 4. Gráfico Asesores
    if (data.hasTopAses) {
        const numAsesores = data.asesLabels.length;
        const altoCalculado = Math.max(300, numAsesores * 35);
        document.querySelector('.chart-wrap--ases').style.height = altoCalculado + 'px';

        new Chart(document.getElementById('barAsesores'), {
            type: 'bar',
            data: { 
                labels: data.asesLabels, 
                datasets: [{ 
                    label: 'Monto Recaudado',
                    data: data.asesData, 
                    backgroundColor: brandColor,
                    borderRadius: 5,
                    barThickness: 20
                }] 
            },
            options: {
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        offset: 5,
                        formatter: (val) => 'S/ ' + Intl.NumberFormat('es-PE', {minimumFractionDigits: 2}).format(val),
                        font: { weight: 'bold', size: 11 },
                        color: '#151a23'
                    }
                },
                scales: {
                    x: { display: false, grace: '25%' },
                    y: { grid: { display: false }, ticks: { font: { size: 11, weight: '600' } } }
                }
            }
        });
    }
})();
</script>
@endpush