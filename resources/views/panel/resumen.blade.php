{{-- resources/views/panel/resumen.blade.php --}}
@extends('layouts.app')
@section('title','Panel')
@section('crumb','Resumen')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/panel/resumen.css') }}?v={{ @filemtime(public_path('css/panel/resumen.css')) }}">
  <meta name="clientes-suggest-url" content="{{ route('clientes.suggest') }}">
@endpush

@section('content')
@php
  $role  = $role  ?? strtolower(auth()->user()->role ?? '');
  $isAsesor = $isAsesor ?? ($role==='asesor');
  $isSupervisor = $isSupervisor ?? ($role==='supervisor');
  $isAdmin = $isAdmin ?? in_array($role,['administrador','sistemas']);

  $misSup = $misSup ?? collect();
  $misPre = $misPre ?? collect();
  $misRes = $misRes ?? collect();
  $cnaSup = $cnaSup ?? collect();
  $cnaPre = $cnaPre ?? collect();
  $cnaRes = $cnaRes ?? collect();
@endphp

<div class="container-fluid">
  <div class="row g-3">
    {{-- ===== Izquierda ===== --}}
    <div class="col-lg-8">

      {{-- Bienvenida + buscador --}}
      <div class="card pad shadow-soft">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <h5 class="mb-1">¡Bienvenido(a)!</h5>
            <div class="text-secondary small">Panel inicial.</div>

            @if (session('quick_error'))
              <div class="alert alert-warning py-1 px-2 mt-2 mb-0" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i> {{ session('quick_error') }}
              </div>
            @endif
          </div>

          <form id="frmQuick" class="position-relative d-flex align-items-center" role="search"
                action="{{ route('clientes.quick') }}" method="GET" autocomplete="off">
            <input id="inpQuick"
                   name="q"
                   class="form-control form-control-sm me-2"
                   placeholder="DNI / Operación / Nombre"
                   aria-label="Buscar por DNI, Operación o Nombre">
            <button class="btn btn-primary btn-sm" type="submit">
              <i class="bi bi-search me-1"></i> Buscar
            </button>

            {{-- Dropdown de sugerencias --}}
            <div id="quickSug" class="quick-menu"></div>
          </form>
        </div>
      </div>

      {{-- Lista de coincidencias ambigua (opcional) --}}
      @if (session('quick_list'))
        <div class="card pad mt-2">
          <h6 class="mb-2">Coincidencias</h6>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr><th>DNI</th><th>Nombre</th><th>Operación</th><th>Cosecha</th><th></th></tr></thead>
              <tbody>
              @foreach(session('quick_list') as $r)
                @php
                  $dni      = data_get($r, 'dni');
                  $nombre   = data_get($r, 'nombre');
                  $operacion= data_get($r, 'operacion');
                  $cosecha  = data_get($r, 'cosecha');
                @endphp
                <tr>
                  <td class="text-nowrap">{{ $dni }}</td>
                  <td>{{ $nombre }}</td>
                  <td class="text-nowrap">{{ $operacion }}</td>
                  <td class="text-nowrap">{{ $cosecha }}</td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('clientes.show', $dni) }}">Ver</a>
                  </td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif

      {{-- KPIs --}}
      <div class="row g-3">
        <div class="col-sm-6">
          <div class="kpi shadow-soft">
            <div class="ico"><i class="bi bi-clipboard2-check"></i></div>
            <div>
              <div class="lbl">Promesas creadas hoy</div>
              <div class="val">{{ number_format($kpiPromHoy) }}</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="kpi shadow-soft">
            <div class="ico"><i class="bi bi-cash-coin"></i></div>
            <div>
              <div class="lbl">Pagos registrados hoy</div>
              <div class="val">S/ {{ number_format($kpiPagosHoy,2) }}</div>
            </div>
          </div>
        </div>
      </div>

      {{-- Gráfica: Pagos del mes --}}
      <div class="card pad shadow-soft chart-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-bar-chart-steps" style="color:var(--accent)"></i> Pagos del mes
          </h6>
          <div class="toolbar">
            @php $curr = \Carbon\Carbon::createFromFormat('Y-m',$mes); @endphp
            <a class="btn btn-outline-secondary btn-sm"
               href="{{ url()->current().'?mes='.$curr->copy()->subMonth()->format('Y-m') }}"><i class="bi bi-chevron-left"></i></a>
            <input type="month" id="mesPicker" class="form-control form-control-sm" value="{{ $curr->format('Y-m') }}">
            <a class="btn btn-outline-secondary btn-sm"
               href="{{ url()->current().'?mes='.$curr->copy()->addMonth()->format('Y-m') }}"><i class="bi bi-chevron-right"></i></a>
          </div>
        </div>
        <div class="chart-wrap">
          <canvas id="chartPagos" data-chart='@json(["labels"=>$chartLabels,"data"=>$chartData])'></canvas>
        </div>
      </div>
    </div>

    {{-- ===== Derecha: Actividades ===== --}}
    <div class="col-lg-4">
      <div class="notifs shadow-soft sticky-right">
        <div class="notifs-header">
          <i class="bi bi-list-task"></i>
          {{ $isAsesor ? 'Tus actividades' : 'Actividades' }}
        </div>

        <div class="notifs-body">
          {{-- ===== VISTA ASESOR ===== --}}
          @if($isAsesor)
            {{-- Promesas del asesor --}}
            <section class="notif-group">
              <div class="notif-title">
                <div class="icon"><i class="bi bi-clipboard-check"></i></div>
                <span>Promesas — En Supervisor</span>
                <span class="notif-count">{{ $misSup->count() }}</span>
              </div>
              <ul class="notif-list">
                @forelse($misSup as $p)
                  <li>
                    <a href="{{ route('clientes.show',$p->dni) }}" class="notif-item">
                      <div class="notif-dot"></div>
                      <div class="notif-body">
                        <div class="notif-main">
                          <span class="fw-semibold">{{ $p->dni }}</span>
                          <span class="text-secondary"> • {{ $p->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }}</span>
                        </div>
                        <div class="notif-sub">
                          {{ $p->operacion ?: '—' }} — Pendiente de Supervisor
                        </div>
                      </div>
                      <span class="notif-cta">Ver cliente</span>
                    </a>
                  </li>
                @empty
                  <div class="text-secondary small px-2 pb-2">Sin pendientes con Supervisor.</div>
                @endforelse
              </ul>
            </section>

            <section class="notif-group">
              <div class="notif-title">
                <div class="icon"><i class="bi bi-hourglass-split"></i></div>
                <span>Promesas — Pre-aprobadas</span>
                <span class="notif-count">{{ $misPre->count() }}</span>
              </div>
              <ul class="notif-list">
                @forelse($misPre as $p)
                  <li>
                    <a href="{{ route('clientes.show',$p->dni) }}" class="notif-item">
                      <div class="notif-dot" style="background:var(--accent)"></div>
                      <div class="notif-body">
                        <div class="notif-main">
                          <span class="fw-semibold">{{ $p->dni }}</span>
                          <span class="text-secondary"> • {{ $p->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }}</span>
                        </div>
                        <div class="notif-sub">
                          {{ $p->operacion ?: '—' }} — Pre-aprobada (esperando Administración)
                        </div>
                      </div>
                      <span class="notif-cta">Ver cliente</span>
                    </a>
                  </li>
                @empty
                  <div class="text-secondary small px-2 pb-2">No hay pre-aprobadas.</div>
                @endforelse
              </ul>
            </section>

            <section class="notif-group">
              <div class="notif-title">
                <div class="icon"><i class="bi bi-check2-circle"></i></div>
                <span>Promesas — Resueltas</span>
                <span class="notif-count">{{ $misRes->count() }}</span>
              </div>
              <ul class="notif-list">
                @forelse($misRes as $p)
                  @php
                    $estado = strtoupper($p->workflow_estado);
                    $badgeC = 'var(--bs-success)';
                    if ($estado==='RECHAZADA' || $estado==='RECHAZADA_SUP') $badgeC = 'var(--bs-danger)';
                  @endphp
                  <li>
                    <a href="{{ route('clientes.show',$p->dni) }}" class="notif-item">
                      <div class="notif-dot" style="background:{{ $badgeC }}"></div>
                      <div class="notif-body">
                        <div class="notif-main">
                          <span class="fw-semibold">{{ $p->dni }}</span>
                          <span class="text-secondary"> • {{ ucfirst($p->workflow_estado) }}</span>
                        </div>
                        <div class="notif-sub">{{ $p->operacion ?: '—' }}</div>
                      </div>
                      <span class="notif-cta">Ver cliente</span>
                    </a>
                  </li>
                @empty
                  <div class="text-secondary small px-2 pb-2">Aún no hay resoluciones.</div>
                @endforelse
              </ul>
            </section>

            {{-- CNA del asesor --}}
            <section class="notif-group">
              <div class="notif-title">
                <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
                <span>CNA — En Supervisor</span>
                <span class="notif-count">{{ $cnaSup->count() }}</span>
              </div>
              <ul class="notif-list">
                @forelse($cnaSup as $c)
                  <li>
                    <a href="{{ route('clientes.show',$c->dni) }}" class="notif-item">
                      <div class="notif-dot"></div>
                      <div class="notif-body">
                        <div class="notif-main"><span class="fw-semibold">DNI {{ $c->dni }}</span></div>
                        <div class="notif-sub">Pendiente de Supervisor</div>
                      </div>
                      <span class="notif-cta">Ver cliente</span>
                    </a>
                  </li>
                @empty
                  <div class="text-secondary small px-2 pb-2">Sin CNA en supervisor.</div>
                @endforelse
              </ul>
            </section>

            <section class="notif-group">
              <div class="notif-title">
                <div class="icon"><i class="bi bi-hourglass-split"></i></div>
                <span>CNA — Pre-aprobadas</span>
                <span class="notif-count">{{ $cnaPre->count() }}</span>
              </div>
              <ul class="notif-list">
                @forelse($cnaPre as $c)
                  <li>
                    <a href="{{ route('clientes.show',$c->dni) }}" class="notif-item">
                      <div class="notif-dot" style="background:var(--accent)"></div>
                      <div class="notif-body">
                        <div class="notif-main"><span class="fw-semibold">DNI {{ $c->dni }}</span></div>
                        <div class="notif-sub">Pre-aprobada (esperando Administración)</div>
                      </div>
                      <span class="notif-cta">Ver cliente</span>
                    </a>
                  </li>
                @empty
                  <div class="text-secondary small px-2 pb-2">Sin CNA pre-aprobadas.</div>
                @endforelse
              </ul>
            </section>

            <section class="notif-group">
              <div class="notif-title">
                <div class="icon"><i class="bi bi-check2-circle"></i></div>
                <span>CNA — Resueltas</span>
                <span class="notif-count">{{ $cnaRes->count() }}</span>
              </div>
              <ul class="notif-list">
                @forelse($cnaRes as $c)
                  @php
                    $estado = strtoupper($c->workflow_estado);
                    $badgeC = 'var(--bs-success)';
                    if ($estado==='RECHAZADA' || $estado==='RECHAZADA_SUP') $badgeC = 'var(--bs-danger)';
                  @endphp
                  <li>
                    <a href="{{ route('clientes.show',$c->dni) }}" class="notif-item">
                      <div class="notif-dot" style="background:{{ $badgeC }}"></div>
                      <div class="notif-body">
                        <div class="notif-main">
                          <span class="fw-semibold">DNI {{ $c->dni }}</span>
                          <span class="text-secondary"> • {{ ucfirst($c->workflow_estado) }}</span>
                        </div>
                        <div class="notif-sub">—</div>
                      </div>
                      <span class="notif-cta">Ver cliente</span>
                    </a>
                  </li>
                @empty
                  <div class="text-secondary small px-2 pb-2">Sin CNA resueltas.</div>
                @endforelse
              </ul>
            </section>

          @else
          {{-- ===== VISTA SUPERVISOR / ADMIN ===== --}}

            {{-- Promesas por aprobar --}}
            <section class="notif-group">
              <div class="notif-title">
                <div class="icon"><i class="bi bi-clipboard-check"></i></div>
                <span>Promesas por aprobar</span>
                <span class="notif-count">{{ $ppPendCount }}</span>
              </div>
              <ul class="notif-list">
                @forelse($ppPend as $p)
                  <li>
                    <a href="{{ route('autorizacion') }}" class="notif-item">
                      <div class="notif-dot"></div>
                      <div class="notif-body">
                        <div class="notif-main">
                          <span class="fw-semibold">{{ $p->dni }}</span>
                          <span class="text-secondary"> • {{ $p->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }}</span>
                        </div>
                        <div class="notif-sub">
                          {{ $p->operacion ?: '—' }} — {{ \Carbon\Carbon::parse($p->fecha_promesa)->format('Y-m-d') }}
                          • <b>S/ {{ number_format($p->monto_mostrar,2) }}</b>
                        </div>
                      </div>
                      <span class="notif-cta">Revisar</span>
                    </a>
                  </li>
                @empty
                  <div class="text-secondary small px-2 pb-2">Nada pendiente aquí.</div>
                @endforelse
              </ul>
            </section>

            {{-- CNA por aprobar --}}
            <section class="notif-group">
              <div class="notif-title">
                <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
                <span>Solicitudes de CNA</span>
                <span class="notif-count">{{ $cnaPendCount }}</span>
              </div>
              <ul class="notif-list">
                @forelse($cnaPend as $c)
                  @php $ops = collect((array)$c->operaciones)->filter()->implode(', '); @endphp
                  <li>
                    <a href="{{ route('autorizacion') }}#cna" class="notif-item">
                      <div class="notif-dot" style="background:var(--accent)"></div>
                      <div class="notif-body">
                        <div class="notif-main">
                          <span class="fw-semibold">CNA #{{ $c->nro_carta }}</span>
                          <span class="text-secondary"> • DNI {{ $c->dni }}</span>
                        </div>
                        <div class="notif-sub">{{ $ops ?: '—' }} — {{ optional($c->created_at)->format('Y-m-d') }}</div>
                      </div>
                      <span class="notif-cta">Revisar</span>
                    </a>
                  </li>
                @empty
                  <div class="text-secondary small px-2 pb-2">Sin nuevas CNA.</div>
                @endforelse
              </ul>
            </section>

            {{-- Próximos vencimientos --}}
            <section class="notif-group">
              <div class="notif-title">
                <div class="icon"><i class="bi bi-calendar-event"></i></div>
                <span>Cuotas en los próximos 7 días</span>
                <span class="notif-count">{{ $vencCount }}</span>
              </div>
              <ul class="notif-list">
                @forelse($venc as $v)
                  <li>
                    <div class="notif-item">
                      <div class="notif-dot" style="background:var(--bs-warning)"></div>
                      <div class="notif-body">
                        <div class="notif-main">
                          <span class="fw-semibold">{{ \Carbon\Carbon::parse($v->fecha)->format('d/m') }}</span>
                          <span class="text-secondary"> • DNI {{ $v->dni }}</span>
                        </div>
                        <div class="notif-sub">
                          {{ $v->operacion ?: '—' }} — {{ $v->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }} #{{ $v->nro }}
                        </div>
                      </div>
                      <span class="notif-cta">S/ {{ number_format((float)$v->monto,2) }}</span>
                    </div>
                  </li>
                @empty
                  <div class="text-secondary small px-2 pb-2">No hay vencimientos próximos.</div>
                @endforelse
              </ul>
            </section>
          @endif
        </div>

        @unless($isAsesor)
          <div class="notifs-footer text-end">
            <a class="small" href="{{ route('autorizacion') }}">Ver bandeja completa →</a>
          </div>
        @endunless
      </div>
    </div>
  </div>
</div>
@endsection
@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <script src="{{ asset('js/panel/resumen.js') }}?v={{ @filemtime(public_path('js/panel/resumen.js')) }}"></script>
@endpush
