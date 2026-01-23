{{-- resources/views/panel/resumen.blade.php --}}
@extends('layouts.app')
@section('title','Panel')
@section('crumb','Resumen')

@push('head')
<style>
  .card.pad{ background:#fff }
  .shadow-soft{ box-shadow:0 6px 20px rgba(15,23,42,.06) }

  .kpi{
    display:flex; align-items:center; gap:.75rem;
    border:1px solid var(--bs-border-color);
    border-radius:14px; padding:.9rem 1rem; background:#fff
  }
  .kpi .ico{
    width:44px;height:44px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    background: color-mix(in oklab, var(--brand) 14%, #fff);
    color: var(--brand);
  }
  .kpi .lbl{ font-size:.86rem; color:var(--bs-secondary-color) }
  .kpi .val{ font-weight:800; font-size:1.15rem; line-height:1 }

  .quick a{ border-radius:12px }

  .chart-card .toolbar{ display:flex; align-items:center; gap:.5rem }
  .chart-wrap{ position:relative; width:100%; height:280px }

  .notifs{
    border:1px solid var(--bs-border-color); border-radius:16px; overflow:hidden; background:#fff
  }
  .notifs-header{
    background: var(--brand); color:#fff; font-weight:700; padding:.7rem .95rem;
    display:flex; align-items:center; gap:.55rem
  }
  .notifs-body{ padding:.6rem .6rem .2rem; max-height:70vh; overflow:auto; background:#fff }
  .notif-group{ padding:.25rem .25rem .6rem }
  .notif-title{
    display:flex; align-items:center; gap:.5rem; padding:.25rem .15rem; font-weight:600
  }
  .notif-title .icon{
    width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;
    background: color-mix(in oklab, var(--accent) 12%, #fff); color: var(--accent)
  }
  .notif-count{
    margin-left:auto; font-weight:700; font-size:.8rem; background:#fff; color:var(--accent);
    border:1px solid color-mix(in oklab, var(--accent) 35%, #fff); border-radius:999px; padding:.15rem .55rem
  }
  .notif-list{ list-style:none; padding-left:0; margin:0 }
  .notif-item{
    display:flex; align-items:center; gap:.7rem; padding:.6rem; border-radius:12px; text-decoration:none; color:inherit;
    border:1px solid transparent; background:#fff; transition:.15s
  }
  .notif-item:hover{ background:var(--bs-tertiary-bg); border-color:var(--bs-border-color) }
  .notif-dot{ width:9px;height:9px;border-radius:50%; background:var(--accent) }
  .notif-body .notif-main{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
  .notif-body .notif-sub{ font-size:.85rem; color:var(--bs-secondary-color); white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
  .notif-cta{
    font-size:.75rem; border:1px solid var(--bs-border-color); background:#fff; border-radius:999px; padding:.18rem .55rem; white-space:nowrap
  }
  .notifs-footer{ border-top:1px dashed var(--bs-border-color); padding:.5rem .6rem .6rem; background:#fff }

  /* === Autocomplete (sugerencias) === */
  .quick-menu{
    position:absolute; top:100%; left:0; z-index:1000;
    display:none; width:540px; max-height:320px; overflow:auto;
    margin-top:.25rem; border:1px solid var(--border, var(--bs-border-color));
    border-radius:8px; background:var(--surface, #fff);
    box-shadow:0 10px 24px rgba(20,20,40,.12);
  }
  .quick-menu.show{ display:block; }
  .quick-item{
    display:flex; align-items:center; justify-content:space-between;
    gap:.75rem; padding:.5rem .75rem; cursor:pointer; text-decoration:none; color:inherit;
  }
  .quick-item:hover, .quick-item.active{
    background: color-mix(in oklab, var(--accent) 12%, transparent);
  }
  .quick-left{ display:flex; align-items:center; gap:.5rem; min-width:0 }
  .quick-dni{
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Roboto Mono", monospace;
    font-weight:700; letter-spacing:.2px; white-space:nowrap;
  }
  .quick-sep{ opacity:.6 }
  .quick-name{
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .quick-meta{ color:var(--bs-secondary-color); font-size:.85rem; white-space:nowrap }
  .quick-hl{ font-weight:700; background: color-mix(in oklab, var(--brand) 25%, transparent) }
</style>
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
<script>
  /* ====================== AUTOCOMPLETE ====================== */
  (function(){
    const frm = document.getElementById('frmQuick');
    const inp = document.getElementById('inpQuick');
    const sug = document.getElementById('quickSug');
    if (!frm || !inp || !sug) return;

    let timer=null, idx=-1;

    const SUG_URL = @json(route('clientes.suggest'));
    function hide(){ sug.classList.remove('show'); sug.innerHTML=''; idx=-1; }
    function show(){ if(!sug.classList.contains('show')) sug.classList.add('show'); }

    const escRx = s => s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
    function mark(txt, q){
      if(!q) return txt;
      const rx = new RegExp('('+escRx(q)+')','ig');
      return String(txt||'').replace(rx,'<span class="quick-hl">$1</span>');
    }
    function setActive(i){
      const items = [...sug.querySelectorAll('.quick-item')];
      items.forEach((a,k)=>a.classList.toggle('active', k===i));
      idx = i;
    }
    function render(items, q){
      if(!items.length){ hide(); return; }
      sug.innerHTML = items.map((it,i)=>`
        <a href="${it.url}" class="quick-item ${i===0?'active':''}" data-idx="${i}">
          <div class="quick-left">
            <span class="quick-dni">${mark(it.dni,q)}</span>
            <span class="quick-sep">»</span>
            <span class="quick-name">${mark(it.nombre,q)}</span>
          </div>
          <div class="quick-meta">${mark(it.operacion||'—',q)} · ${it.cosecha||'—'}</div>
        </a>
      `).join('');
      sug.querySelectorAll('.quick-item').forEach((a,i)=>{
        a.addEventListener('mouseenter',()=>setActive(i));
      });
      show(); idx=0;
    }
    function fetchSuggest(q){
      if (!q || q.trim().length<1){ hide(); return; }
      fetch(SUG_URL+'?q='+encodeURIComponent(q.trim()), {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(data=>render(data, q)).catch(()=>hide());
    }

    inp.addEventListener('input', ()=>{
      clearTimeout(timer);
      timer=setTimeout(()=>fetchSuggest(inp.value), 140);
    });
    inp.addEventListener('focus', ()=>{ if(inp.value.trim()) fetchSuggest(inp.value); });

    // Teclado
    inp.addEventListener('keydown', (e)=>{
      const items = [...sug.querySelectorAll('.quick-item')];
      if(e.key==='ArrowDown' && items.length){ e.preventDefault(); setActive(Math.min(idx+1, items.length-1)); }
      if(e.key==='ArrowUp'   && items.length){ e.preventDefault(); setActive(Math.max(idx-1, 0)); }
      if(e.key==='Enter'     && items.length && idx>=0){
        e.preventDefault(); items[idx].click();
      }
      if(e.key==='Escape'){ hide(); }
    });

    document.addEventListener('click', (e)=>{ if(!e.target.closest('#frmQuick')) hide(); });
  })();

  /* ====================== SELECTOR DE MES ====================== */
  document.getElementById('mesPicker')?.addEventListener('change', (e)=>{
    const ym = e.target.value || '';
    const url = new URL(window.location.href);
    url.searchParams.set('mes', ym);
    window.location.assign(url.toString());
  });

  /* ====================== CHART: PAGOS DEL MES ====================== */
  (function(){
    const el = document.getElementById('chartPagos');
    if(!el) return;
    const payload = (()=>{ try{ return JSON.parse(el.dataset.chart||'{}'); }catch(_){ return {}; }})();
    const labels = payload.labels || [];
    const data   = payload.data   || [];

    const css    = (v)=>getComputedStyle(document.documentElement).getPropertyValue(v).trim();
    const ACCENT = css('--accent') || '#0b4ea2';

    const hexToRgba = (hex, a=1)=>{
      const h = hex.replace('#','').trim();
      const bigint = parseInt(h.length===3 ? h.split('').map(x=>x+x).join('') : h, 16);
      const r=(bigint>>16)&255, g=(bigint>>8)&255, b=bigint&255;
      return `rgba(${r}, ${g}, ${b}, ${a})`;
    };

    new Chart(el.getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'S/ por día',
          data,
          borderWidth: 2,
          borderColor: ACCENT,
          backgroundColor: hexToRgba(ACCENT, .15),
          hoverBackgroundColor: hexToRgba(ACCENT, .25),
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 250 },
        scales: {
          x: { grid: { display:false } },
          y: {
            beginAtZero:true,
            ticks: { callback:(v)=>'S/ '+Number(v).toLocaleString() }
          }
        },
        plugins: {
          legend: { display:false },
          tooltip: {
            callbacks: {
              label: (ctx)=> 'S/ ' + Number(ctx.parsed.y ?? 0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})
            }
          }
        }
      }
    });
  })();
</script>
@endpush
