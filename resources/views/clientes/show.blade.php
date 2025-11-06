{{-- resources/views/clientes/show.blade.php --}}
@extends('layouts.app')
@section('title','Cliente '.$dni)
@section('crumb','Cliente')

@push('head')
<style>
  /* ====== Marca KP: verde primario + azul acento (fallback si el layout está cacheado) ====== */
  :root{
    --brand:#00a81c;
    --brand-ink:#008517;
    --accent:#0b4ea2;
    --accent-ink:#093f82;

    --surface:#ffffff; --surface-2:#f3f6fb; --border:#e8ecf3;
    --ink:#151a23; --muted:#6d7b8a;
  }

  /* ====== Densidad / helpers ====== */
  .tbl-compact.table> :not(caption)>*>*{ padding:.5rem .65rem }
  .max-h-320{ max-height:320px; overflow:auto }
  .max-h-260{ max-height:260px; overflow:auto }

  /* ====== Encabezado ====== */
  .cli-head .dni-pill{
    display:inline-flex; align-items:center; gap:.4rem;
    background:color-mix(in oklab, var(--brand) 10%, transparent);
    color:var(--brand);
    border:1px solid color-mix(in oklab, var(--brand) 25%, transparent);
    padding:.18rem .55rem; border-radius:999px; font-weight:600
  }
  .cli-head .meta{ display:flex; flex-wrap:wrap; gap:.5rem 1rem; color:var(--muted) }
  .kpi-mini{ background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:10px 12px }
  .kpi-mini .label{ color:var(--muted); font-size:.83rem }
  .kpi-mini .value{ font-weight:800; font-size:1.15rem; line-height:1.1 }

  /* ====== Tablas ====== */
  .tbl-compact thead th{
    position: sticky; top: 0; z-index: 1;
    background: color-mix(in oklab, var(--surface-2) 55%, transparent);
    color: var(--ink);
    text-transform: uppercase; font-size:.8rem; letter-spacing:.3px;
    border-bottom:1px solid var(--border);
    box-shadow:0 3px 8px rgba(15,23,42,.06);
  }
  .tbl-compact tbody tr:nth-child(even){
    background: color-mix(in oklab, var(--surface-2) 14%, transparent);
  }
  .tbl-compact tbody tr:hover{
    background: color-mix(in oklab, var(--accent) 10%, var(--brand) 6%);
  }
  .tbl-compact tfoot td{
    background: color-mix(in oklab, var(--surface-2) 35%, transparent);
    font-weight:700; border-top:1px solid var(--border);
  }

  /* ====== Promesas ====== */
  .promesas .table>tbody>tr{ transition:box-shadow .2s ease, transform .05s ease }
  .promesas .table>tbody>tr:hover{ box-shadow:0 1px 0 rgba(15,23,42,.06) inset, 0 2px 10px rgba(15,23,42,.06) }

  .promesas .pp-state-preaprobada  { box-shadow: 4px 0 0 0 var(--accent) inset; background: color-mix(in oklab, var(--accent) 18%, transparent) }
  .promesas .pp-state-aprobada     { box-shadow: 4px 0 0 0 var(--brand) inset;  background: color-mix(in oklab, var(--brand) 18%, transparent) }
  .promesas .pp-state-rechazada    { box-shadow: 4px 0 0 0 #e38074 inset;       background: color-mix(in oklab,#e38074 16%, transparent) }
  .promesas .pp-state-pendiente    { box-shadow: 4px 0 0 0 #cfd7e1 inset;       background: color-mix(in oklab,#cfd7e1 16%, transparent) }

  .promesas .card-head{ display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.5rem }
  .promesas .hint{ color:var(--muted); font-size:.85rem }
  .promesas .nota-clamp{
    display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical;
    overflow:hidden; max-width: 28rem;
  }

  .badge-pill{ border-radius:999px; padding:.18rem .6rem; font-weight:700 }
  .badge-pp-warning{ background:#f7e9c4; color:#7a5a00; border:1px solid #e7d08d }
  .badge-pp-info{ background:#dfeffd; color:#0b4e9b; border:1px solid #b7d3fb }
  .badge-pp-success{ background:#dff7ea; color:#0a6b3a; border:1px solid #bce9d0 }
  .badge-pp-danger{ background:#fde0de; color:#8a1a10; border:1px solid #f3b7b2 }
  .badge-pp-secondary{ background:#e9edf3; color:#495869; border:1px solid #cfd7e1 }

  .badge-soft{
    background:color-mix(in oklab, var(--brand) 12%, transparent);
    color:var(--brand);
    border:1px solid color-mix(in oklab, var(--brand) 24%, transparent);
    border-radius:999px; padding:.18rem .55rem; font-weight:600
  }

  .table-responsive::-webkit-scrollbar{ height:10px }
  .table-responsive::-webkit-scrollbar-thumb{ background: color-mix(in oklab, var(--accent) 22%, transparent); border-radius:10px }

  /* ====== DECISIONES ====== */
  .decision-cell{ min-width:260px }
  .decision-cell .nota-clamp{ -webkit-line-clamp:2; max-width:32rem }
  .decision-cell .meta{ color:var(--muted); font-size:.82rem }

  .decision-box{
    border:1px solid var(--border);
    border-left-width:4px;
    border-radius:12px;
    padding:.45rem .6rem;
    background:var(--surface);
  }
  .decision-box.is-preaprobada{ border-left-color:var(--accent); background:color-mix(in oklab, var(--accent) 20%, transparent) }
  .decision-box.is-aprobada{ border-left-color:var(--brand); background:color-mix(in oklab, var(--brand) 20%, transparent) }
  .decision-box.is-rechazada{ border-left-color:#e38074; background:color-mix(in oklab,#e38074 20%, transparent) }

  /* ====== Botones ====== */
  .btn-primary{ background:var(--brand); border-color:var(--brand) }
  .btn-primary:hover{ background:var(--brand-ink); border-color:var(--brand-ink) }

  .btn-outline-primary{ color:var(--accent); border-color:var(--accent) }
  .btn-outline-primary:hover{ color:#fff; background:var(--accent-ink); border-color:var(--accent-ink) }
</style>
@endpush

@section('content')
  @if(session('ok')) <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle me-2"></i><div>{{ session('ok') }}</div></div> @endif
  @if($errors->any()) <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle me-2"></i><div>{{ $errors->first() }}</div></div> @endif

  {{-- ENCABEZADO --}}
  <div class="card pad cli-head mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
      <div>
        <h1 class="h5 mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-person-badge"></i><span>{{ $titular }}</span>
        </h1>
        <div class="meta">
          <div class="d-flex align-items-center gap-1">
            <span class="dni-pill"><i class="bi bi-credit-card-2-front"></i> DNI {{ $dni }}</span>
          </div>
          @if(isset($cuentas) && count($cuentas)) <div><i class="bi bi-wallet2 me-1"></i>{{ count($cuentas) }} cuenta(s)</div>@endif
          @if(isset($pagos))   <div><i class="bi bi-receipt me-1"></i>{{ count($pagos) }} pago(s)</div>@endif
          @if(isset($promesas))<div><i class="bi bi-flag me-1"></i>{{ count($promesas) }} promesa(s)</div>@endif
        </div>
      </div>
      @php
        // Campos nuevos: deuda_capital / interes / deuda_total
        $totCapital = (float) $cuentas->sum(fn($x)=>(float)($x->deuda_capital ?? $x->saldo_capital ?? 0));
        $totDeuda   = (float) $cuentas->sum(fn($x)=>(float)($x->deuda_total   ?? 0));
        $totPagos   = (float) $pagos->sum(fn($p)=>(float)($p->monto_pagado ?? $p->monto ?? 0));
      @endphp
      <div class="d-flex flex-wrap gap-2">
        <div class="kpi-mini text-end"><div class="label">Deuda capital</div><div class="value">S/ {{ number_format($totCapital,2) }}</div></div>
        <div class="kpi-mini text-end"><div class="label">Deuda total</div><div class="value">S/ {{ number_format($totDeuda,2) }}</div></div>
        <div class="kpi-mini text-end"><div class="label">Pagos registrados</div><div class="value">S/ {{ number_format($totPagos,2) }}</div></div>
      </div>
    </div>
  </div>

  {{-- ========== CUENTAS ========== --}}
  <div class="card pad mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h6 mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-wallet2"></i><span>Cuentas</span>
      </h2>

      <div class="d-flex align-items-center gap-2">
        {{-- Generar propuesta --}}
        <button class="btn btn-primary btn-sm" id="btnPropuesta" type="button" data-bs-toggle="modal" data-bs-target="#modalPropuesta" disabled>
          <i class="bi bi-flag"></i> Generar propuesta
          <span class="ms-1 badge rounded-pill text-bg-light align-middle" id="selCount">0</span>
        </button>
      </div>
    </div>

    <div class="table-responsive max-h-320">
      <table class="table table-sm table-striped table-hover align-middle tbl-compact mb-0" id="tblCuentas">
        <thead>
          <tr>
            <th class="text-center" style="width:36px"><input type="checkbox" id="chkAll"></th>
            <th class="text-nowrap">Operación</th>
            <th>Entidad</th>
            <th>Producto</th>
            <th>Cosecha</th>
            <th class="text-end text-nowrap">Deuda capital</th>
            <th class="text-end text-nowrap">Interés</th>
            <th class="text-end text-nowrap">Deuda total</th>
            <th class="text-nowrap">CNA(s)</th>
            <th class="text-nowrap">CCD</th>
            <th class="text-nowrap">
              Pagos
              <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" title="Conteo y total de pagos aplicados a esta operación."></i>
            </th>
          </tr>
        </thead>
        <tbody>
        @foreach ($cuentas as $c)
          @php
            $cnt = (int)($c->pagos_count ?? 0);
            $sum = (float)($c->pagos_sum ?? 0);
            $hasList = isset($c->pagos_list) && (
              ($c->pagos_list instanceof \Illuminate\Support\Collection && $c->pagos_list->count()) ||
              (is_array($c->pagos_list) && count($c->pagos_list))
            );
            $docsCcd = collect($ccdByDni[$dni] ?? []);

            $cnas = collect($cnasByCuenta[$c->cuenta] ?? []);

            $badgeFor = fn($estado) => match (true) {
              str_contains(strtolower((string)$estado),'aprob')  => 'success',
              str_contains(strtolower((string)$estado),'pre')    => 'primary',
              str_contains(strtolower((string)$estado),'rechaz') => 'danger',
              default => 'secondary',
            };
          @endphp

          {{-- Importante: data-cuenta para poder agrupar por cuenta en el modal --}}
          <tr data-cuenta="{{ $c->cuenta }}"
              data-oper="{{ $c->operacion }}"
              data-cosecha="{{ $c->cosecha }}"
              data-entidad="{{ $c->entidad }}">
            <td class="text-center">
              <input type="checkbox" class="chkOp" value="{{ $c->operacion }}" {{ empty($c->operacion) ? 'disabled' : '' }}>
            </td>
            <td class="text-nowrap">{{ $c->operacion ?? '—' }}</td>
            <td>{{ $c->entidad   ?? '—' }}</td>
            <td>{{ $c->producto  ?? '—' }}</td>
            <td>{{ $c->cosecha   ?? '—' }}</td>
            <td class="text-end text-nowrap">{{ number_format((float)($c->deuda_capital ?? $c->saldo_capital ?? 0), 2) }}</td>
            <td class="text-end text-nowrap">{{ number_format((float)($c->interes ?? 0), 2) }}</td>
            <td class="text-end text-nowrap">{{ number_format((float)($c->deuda_total ?? 0), 2) }}</td>

            {{-- === CELDA CNA === --}}
            <td class="text-nowrap">
              @php
                $cnas = collect($cnasByCuenta[$c->cuenta] ?? []);
                $last   = $cnas->sortByDesc(fn($x) => $x->created_at)->first();
                $estado = strtolower((string)($last->workflow_estado ?? ''));
              @endphp

              @if($last && (str_contains($estado,'pend') || str_contains($estado,'pre')))
                {{-- En proceso: spinner y deshabilitado --}}
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                        title="CNA en proceso de aprobación">
                  <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                  <span class="d-none d-md-inline">En proceso</span>
                </button>

              @elseif($last && str_contains($estado,'aprob') && !str_contains($estado,'pre'))
                {{-- Aprobada: descarga (pdf con fallback a docx) --}}
                <a href="{{ route('cna.pdf', $last->id) }}"
                  class="btn btn-sm btn-outline-danger"
                  title="Descargar CNA aprobada (PDF)">
                  <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                  <span class="visually-hidden">Descargar CNA</span>
                </a>

              @else
                {{-- Sin CNA o última rechazada: martillo para generar --}}
                <button type="button"
                        class="btn btn-sm btn-outline-success genCnaBtn"
                        title="Generar CNA para esta cuenta"
                        data-dni="{{ $dni }}"
                        data-cuenta="{{ $c->cuenta }}"
                        data-oper="{{ $c->operacion }}"
                        data-cosecha="{{ $c->cosecha }}"
                        data-entidad="{{ $c->entidad }}"
                        data-bs-toggle="modal"
                        data-bs-target="#modalCna">
                  <i class="bi bi-hammer" aria-hidden="true"></i>
                  <span class="visually-hidden">Generar CNA</span>
                </button>
              @endif
            </td>
            {{-- === /CELDA CNA === --}}

            {{-- === CELDA CCD === --}}
            <td class="text-nowrap">
              @php $docs = $docsCcd; @endphp

              @if($docs->count() === 1)
                @php
                  $d = $docs->first();
                  // href: usa link si viene; si pdf es URL absoluta úsala; si no, asset() a storage
                  $href = $d->link
                    ?: (preg_match('#^https?://#', (string)$d->pdf) ? $d->pdf : asset('storage/ccd/'.ltrim((string)$d->pdf,'/')));
                @endphp
                @if($href)
                  <a href="{{ $href }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Abrir CCD">
                    <i class="bi bi-filetype-pdf"></i>
                  </a>
                @else
                  <span class="text-secondary">—</span>
                @endif

              @elseif($docs->count() > 1)
                <div class="btn-group">
                  <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-filetype-pdf me-1"></i> CCD ({{ $docs->count() }})
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    @foreach($docs as $d)
                      @php
                        $href = $d->link
                          ?: (preg_match('#^https?://#', (string)$d->pdf) ? $d->pdf : asset('storage/ccd/'.ltrim((string)$d->pdf,'/')));
                        // Nombre legible SOLO con campos existentes: pdf/cosecha/id
                        $name = $d->pdf
                          ? pathinfo((string)$d->pdf, PATHINFO_FILENAME)
                          : ( ($d->cosecha ? 'CCD '.$d->cosecha : 'CCD').' #'.$d->id );
                      @endphp
                      @if($href)
                        <li><a class="dropdown-item" href="{{ $href }}" target="_blank">{{ $name }}</a></li>
                      @endif
                    @endforeach
                  </ul>
                </div>
              @else
                <span class="text-secondary">—</span>
              @endif
            </td>
            {{-- === /CELDA CCD === --}}

            <td class="text-nowrap">
              <span class="badge rounded-pill text-bg-light border">{{ $cnt }} pago(s)</span>
              @if($sum > 0)
                <small class="text-secondary ms-1">· S/ {{ number_format($sum, 2) }}</small>
              @endif

              @if($hasList)
                @php $collapseId = 'pagos-'.$loop->index; @endphp
                <button class="btn btn-sm btn-outline-secondary ms-2" type="button"
                        data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                        aria-expanded="false" aria-controls="{{ $collapseId }}">
                  Ver detalle
                </button>
                <div class="collapse mt-2" id="{{ $collapseId }}">
                  <ul class="list-unstyled mb-0 small">
                    @foreach($c->pagos_list as $p)
                      @php
                        $f = !empty($p->fecha) ? \Carbon\Carbon::parse($p->fecha)->format('d/m/Y') : '—';
                        $m = number_format((float)($p->monto ?? 0), 2);
                        $src = $p->fuente ?? '—';
                      @endphp
                      <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-dot"></i>
                        <span class="text-nowrap">{{ $f }}</span>
                        <span>· S/ {{ $m }}</span>
                        <span class="text-secondary">· {{ $src }}</span>
                      </li>
                    @endforeach
                  </ul>
                </div>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- PAGOS --}}
  <div class="card pad mb-3">
    <div class="d-flex justify-content-between align-items-center">
      <h2 class="h6 mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-receipt"></i><span>Pagos</span>
      </h2>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#pagosCollapse">
          Ver/ocultar
        </button>
      </div>
    </div>

    <div id="pagosCollapse" class="collapse mt-2 show">
      <div class="table-responsive max-h-260">
        <table class="table table-sm align-middle tbl-compact" id="tblPagos">
          <thead class="position-sticky top-0 bg-body">
            <tr>
              <th class="text-nowrap">Fecha</th>
              <th class="text-nowrap">Operación</th>
              <th class="text-end text-nowrap">Monto (S/)</th>
              <th class="text-nowrap">Agente</th>
              <th class="text-nowrap">Cuenta Recaudo</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pagos as $p)
              @php
                $oper    = $p->operacion ?? $p['operacion'] ?? '-';
                $monto   = $p->monto_pagado ?? $p['monto_pagado'] ?? 0;
                $fecha   = $p->fecha ?? $p['fecha'] ?? null;
                $gestor  = $p->gestor ?? $p['gestor'] ?? '-';
                $cuenta  = $p->cuenta_recaudo ?? $p['cuenta_recaudo'] ?? '-';
              @endphp
              <tr>
                <td class="text-nowrap">{{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '' }}</td>
                <td class="text-nowrap">{{ $oper }}</td>
                <td class="text-end text-nowrap">{{ number_format((float)$monto, 2, '.', ',') }}</td>
                <td class="text-nowrap">{{ $gestor }}</td>
                <td class="text-nowrap">{{ $cuenta }}</td>
              </tr>
            @empty
              <tr><td colspan="9" class="text-secondary">Sin pagos</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- PROMESAS --}}
  <div class="promesas">
    <div class="card pad h-100">
      <div class="card-head">
        <h2 class="h6 mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-flag"></i> <span>Promesas de pago</span>
        </h2>
        @if(($promesas ?? collect())->count())
          <div class="hint">{{ $promesas->count() }} registro(s)</div>
        @endif
      </div>

      <div class="table-responsive max-h-320">
        <table class="table align-middle tbl-compact" id="tblPromesas">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Operación(es)</th>
              <th class="text-end">Monto negociación</th>
              <th class="text-center">Nota</th>
              <th class="text-center">Campaña</th>
              <th>Usuario creación</th>
              <th>Estado aprobación</th>
            </tr>
          </thead>
          <tbody>
          @forelse($promesas as $pp)
            @php
              $tipoLabel = match($pp->tipo){
                'convenio_balon' => 'Convenio [Cuota Balón]',
                'convenio'       => 'Convenio',
                'cancelacion'    => 'Cancelación',
                default          => strtoupper((string)$pp->tipo)
              };

              $ops = $pp->relationLoaded('operaciones') && $pp->operaciones->count()
                ? $pp->operaciones->pluck('operacion')->all()
                : array_filter(array_map('trim', explode(',', (string)($pp->operacion ?? ''))));

              // Monto negociación: si es 0, toma la primera cuota
              $montoNeg = (float)($pp->monto ?? 0);
              if ($montoNeg <= 0) {
                $montoNeg = (float)($pp->cuotas->first()->monto ?? 0);
              }

              $estado = ucfirst(str_replace('_',' ', (string)($pp->workflow_estado ?? 'pendiente')));
              $nota   = trim((string)($pp->nota ?? ''));

              // Dataset para el modal reutilizable
              $ds = [
                'tipo'   => (string)$pp->tipo,
                'cuotas' => $pp->tipo === 'cancelacion'
                            ? [[ 'nro' => 1, 'fecha' => optional($pp->fecha_pago)->format('Y-m-d'), 'monto' => (float)($pp->monto ?? 0), 'es_balon' => 0 ]]
                            : $pp->cuotas->map(fn($c)=>[
                                'nro'      => (int)$c->nro,
                                'fecha'    => optional($c->fecha)->format('Y-m-d'),
                                'monto'    => (float)$c->monto,
                                'es_balon' => (int)($c->es_balon ?? 0),
                              ])->values(),
              ];
            @endphp

            <tr class="cursor-pointer"
                data-open-cronograma="1"
                data-promesa-id="{{ $pp->id }}"
                data-dataset='@json($ds)'>
              <td class="text-nowrap">{{ optional($pp->created_at)->format('d/m/Y') ?? '—' }}</td>
              <td class="text-nowrap">{{ $tipoLabel }}</td>
              <td class="text-nowrap">
                @if($ops) {{ implode(', ', $ops) }} @else <span class="text-secondary">—</span> @endif
              </td>
              <td class="text-end text-nowrap">S/ {{ number_format($montoNeg, 2) }}</td>
              <td class="text-center">
                @if($nota !== '')
                  <i class="bi bi-journal-text" data-bs-toggle="tooltip" title="{{ $nota }}"></i>
                @else
                  <span class="text-secondary">—</span>
                @endif
              </td>
              <td class="text-center">
                @if(strtolower($pp->workflow_estado ?? '') === 'aprobada')
                  <a class="btn btn-outline-primary btn-sm"
                    href="{{ route('promesas.acuerdo', $pp) }}"
                    target="_blank" data-bs-toggle="tooltip" title="Descargar acuerdo en PDF">
                    <i class="bi bi-filetype-pdf"></i>
                  </a>
                @else
                  <span class="text-secondary">—</span>
                @endif
              </td>
              <td class="text-nowrap">{{ $pp->user->name ?? '—' }}</td>
              <td class="text-nowrap">{{ $estado }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-secondary">Sin promesas</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- MODAL: Cronograma de pagos --}}
  <div class="modal fade" id="cronModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">Cronograma</h6>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th class="text-end" style="width:60px">#</th>
                  <th>Fecha de pago</th>
                  <th class="text-end">Monto</th>
                  <th class="text-center d-none" id="thBalon">¿Balón?</th>
                </tr>
              </thead>
              <tbody id="cronTbody"></tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>


  {{-- MODAL: Generar Propuesta --}}
  <div class="modal fade" id="modalPropuesta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="{{ route('clientes.promesas.store', $dni) }}" id="formPropuesta">
          @csrf

          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi bi-flag me-1"></i>
              Generar propuesta
              <span id="modalTipoTag" class="badge rounded-pill text-bg-light border ms-2">Convenio</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>

          <div class="modal-body">
            {{-- Operaciones seleccionadas --}}
            <div class="mb-3">
              <div class="form-label">Operaciones a incluir</div>
              <div id="opsResumen" class="small"></div>
              <div id="opsHidden"></div>
            </div>

            {{-- Tipo de propuesta + Teléfono + Observación --}}
            <div class="row g-2 mb-2">
              <div class="col-md-4">
                <label class="form-label">Tipo de propuesta</label>
                <select name="tipo" id="tipoPropuesta" class="form-select" required>
                  <optgroup label="Convenios">
                    <option value="convenio" data-balon="0">Convenio</option>
                    <option value="convenio_balon" data-balon="1">Convenio (cuota balón)</option>
                  </optgroup>
                  <optgroup label="Otros">
                    <option value="cancelacion">Cancelación</option>
                  </optgroup>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">Teléfono de contacto</label>
                <input
                  name="telefono"
                  id="telefonoPropuesta"
                  class="form-control"
                  placeholder="+51 9XXXXXXXX"
                  maxlength="30"
                  inputmode="tel"
                  pattern="^\+?\d[\d\s\-]{5,}$"
                  title="Ingresa un teléfono válido"
                  required>
                <div class="form-text">Será el teléfono principal de contacto para esta propuesta.</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Observación (opcional)</label>
                <input name="nota" class="form-control" maxlength="500" placeholder="Detalle (máx. 500)">
              </div>
            </div>

            {{-- CONVENIO (mismo formulario para convenio y “convenio (cuota balón)”) --}}
            <div id="formConvenio" class="row g-2">
              <div class="col-md-3">
                <label class="form-label">Nro cuotas</label>
                <input type="number" min="1" step="1" name="nro_cuotas" id="cvNro" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Monto convenio (S/)</label>
                <input type="number" step="0.01" min="0.01" name="monto_convenio" id="cvTotal" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Monto de cuota (S/) (sugerido)</label>
                <input type="number" step="0.01" min="0.01" name="monto_cuota" id="cvCuota" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">Fecha inicial (opción auto)</label>
                <input type="date" id="cvFechaIni" class="form-control">
                <div class="form-text" id="cvHintDia">Día de pago: —</div>
              </div>

              <div class="col-12">
                <div class="d-flex gap-2 align-items-center mb-2">
                  <button type="button" id="cvGen" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-magic"></i> Generar cronograma
                  </button>
                  <span class="text-secondary small">Puedes editar fechas y montos después de generar.</span>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm align-middle tbl-compact" id="tblCrono">
                    <thead>
                      <tr>
                        <th style="width:70px">#</th>
                        <th style="width:180px">Fecha</th>
                        <th>Importe (S/)</th>
                      </tr>
                    </thead>
                    <tbody>
                      {{-- filas dinámicas --}}
                    </tbody>
                    <tfoot>
                      <tr>
                        <td colspan="2" class="text-end fw-bold">Total cronograma</td>
                        <td><span id="cvSuma" class="fw-bold">0.00</span></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                {{-- inputs ocultos que se envían --}}
                <div id="cvHidden"></div>
                <input type="hidden" name="cron_balon" id="cronBalon">

                <div class="form-text mt-1">
                  * El total del cronograma debe coincidir con el <b>Monto convenio</b>.
                  <span class="d-block" id="hintBalon" style="display:none">
                    (En cuota balón: la suma de las cuotas regulares debe coincidir; la última fila es la cuota balón).
                  </span>
                </div>
              </div>
            </div>

            {{-- CANCELACIÓN --}}
            <div id="formCancelacion" class="row g-2 d-none">
              <div class="col-md-6">
                <label class="form-label">Fecha de pago</label>
                <input type="date" name="fecha_pago_cancel" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Monto (S/)</label>
                <input type="number" step="0.01" min="0.01" name="monto_cancel" class="form-control">
              </div>
            </div>

            <div class="small text-secondary mt-2">
              * La propuesta se asociará al DNI {{ $dni }} y a las operaciones seleccionadas.
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Guardar propuesta</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- ===== Modal: Solicitar Carta de No Adeudo (CNA) ===== --}}
  <div class="modal fade" id="modalCna" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="POST" action="{{ route('clientes.cna.store', $dni) }}">
        @csrf
        <div class="modal-header">
          <h6 class="modal-title d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-text"></i>
            Solicitar Carta de No Adeudo (CNA)
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-info small">
            <div><b>Cuenta:</b> <span id="cnaCuenta">—</span></div>
            <div><b>Cosecha:</b> <span id="cnaCosecha">—</span></div>
            <div><b>Origen / Plantilla:</b> <span id="cnaPlantilla">—</span></div>
            <div><b>Operaciones incluidas:</b> <span id="cnaOpsList" class="d-inline-flex flex-wrap gap-1 align-middle"></span></div>
            <div class="mt-1">El correlativo se asignará por <b>serie</b> (KPI, F, F2) al guardar.</div>
          </div>

          {{-- Campos principales --}}
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Fecha de pago realizado <span class="text-danger">*</span></label>
              <input type="date" name="fecha_pago_realizado" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Monto pagado (S/.) <span class="text-danger">*</span></label>
              <input type="number" name="monto_pagado" step="0.01" min="0.01" class="form-control" required>
            </div>
          </div>

          <div class="mb-3 mt-2">
            <label class="form-label">Observación (opcional)</label>
            <textarea name="observacion" class="form-control" rows="3" placeholder="Algún comentario contextual"></textarea>
          </div>

          {{-- Hidden: cuenta y operaciones[] --}}
          <input type="hidden" name="cuenta" id="cnaCuentaInput">
          <div id="cnaOpsHidden"></div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-success" type="submit">
            <i class="bi bi-send me-1"></i> Enviar solicitud
          </button>
        </div>
      </form>
    </div>
  </div>

@endsection

@push('scripts')
<script>
  (function(){
    const tbl = document.getElementById('tblPromesas');
    const cronModal = document.getElementById('cronModal');
    const modal = cronModal ? new bootstrap.Modal(cronModal) : null;
    const tb = document.getElementById('cronTbody');
    const thBalon = document.getElementById('thBalon');

    function fmtFecha(iso){ if(!iso) return '—';
      const d = new Date(iso + 'T00:00:00'); 
      return isNaN(d) ? '—' : d.toLocaleDateString('es-PE', {day:'2-digit', month:'2-digit', year:'numeric'});
    }
    function fmtMonto(n){ n = Number(n)||0; return 'S/ ' + n.toFixed(2); }

    tbl?.addEventListener('click', (e)=>{
      const tr = e.target.closest('tr[data-open-cronograma]');
      if(!tr || !modal) return;

      const ds = tr.getAttribute('data-dataset');
      if(!ds) return;

      let data;
      try { data = JSON.parse(ds); } catch { data = null; }
      if(!data) return;

      // header
      const title = cronModal.querySelector('.modal-title');
      title.textContent = 'Cronograma — ' + (data.tipo === 'convenio_balon' ? 'Convenio [Cuota Balón]' :
                                            data.tipo === 'convenio' ? 'Convenio' : 'Cancelación');

      // cuerpo
      tb.innerHTML = '';
      const showBalon = (data.tipo === 'convenio_balon');
      thBalon.classList.toggle('d-none', !showBalon);

      (data.cuotas || []).forEach((c) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="text-end">${c.nro ?? ''}</td>
          <td>${fmtFecha(c.fecha)}</td>
          <td class="text-end">${fmtMonto(c.monto)}</td>
          ${showBalon ? `<td class="text-center">${(c.es_balon==1)?'<span class="badge text-bg-warning">Balón</span>':''}</td>` : ''}
        `;
        tb.appendChild(tr);
      });

      modal.show();
    });
  })();
  (function () {
    /* ========= Refs ========= */
    const tipoSel   = document.getElementById('tipoPropuesta');
    const tipoTag   = document.getElementById('modalTipoTag');
    const formConv  = document.getElementById('formConvenio');
    const formCanc  = document.getElementById('formCancelacion');
    const hintBalon = document.getElementById('hintBalon');

    // Convenio fields
    const nro   = document.getElementById('cvNro');
    const total = document.getElementById('cvTotal');
    const cuota = document.getElementById('cvCuota');
    const fIni  = document.getElementById('cvFechaIni');
    const gen   = document.getElementById('cvGen');
    const tblEl = document.getElementById('tblCrono');
    const suma  = document.getElementById('cvSuma');
    const hid   = document.getElementById('cvHidden');
    const hintDia = document.getElementById('cvHintDia');
    const btnGuardar = document.querySelector('#formPropuesta button[type="submit"]');
    const cronBalonInput = document.getElementById('cronBalon');

    const tbl = tblEl?.querySelector('tbody');

    /* ========= Datos deuda (si existen en la vista) ========= */
    const OP_DEUDAS = (typeof window.OP_DEUDAS !== 'undefined')
        ? window.OP_DEUDAS
        : ((typeof window.OP_SALDOS !== 'undefined') ? window.OP_SALDOS : {});
    const selectedOps = () => [...document.querySelectorAll('#opsHidden input[name="operaciones[]"]')].map(i => i.value);
    const deudaSeleccionada = () => selectedOps().reduce((s, op) => s + (Number(OP_DEUDAS?.[op]) || 0), 0);

    /* ========= Helpers ========= */
    const to2  = n => String(n).padStart(2,'0');
    const fmt2 = n => (Math.round((Number(n)||0)*100)/100).toFixed(2);
    const num  = v => { const s = String(v ?? '').replace(/[^\d,.\-]/g,'').replace(/,/g,''); const n = parseFloat(s); return isNaN(n) ? 0 : n; };
    const isBalon = () => false;

    function addMonthsNoOverflow(base, months){
      const d = new Date(base);
      const day = d.getDate();
      d.setMonth(d.getMonth() + months);
      if (d.getDate() !== day) d.setDate(0);
      return d;
    }

    /* ========= UI por tipo ========= */
    function applyTipoUI() {
      const t = (tipoSel?.value || '').toLowerCase();
      const isConv = t === 'convenio' || t === 'convenio_balon';

      formConv?.classList.toggle('d-none', !isConv);
      formCanc?.classList.toggle('d-none', isConv);

      const req = (el, on)=> el && (on ? el.setAttribute('required','required') : el.removeAttribute('required'));
      req(nro,   isConv);
      req(total, isConv);
      req(document.querySelector('[name="fecha_pago_cancel"]'), !isConv);
      req(document.querySelector('[name="monto_cancel"]'),     !isConv);

      if (tipoTag) tipoTag.textContent = tipoSel?.selectedOptions?.[0]?.textContent?.trim() || 'Convenio';
      if (hintBalon) hintBalon.style.display = 'none';

      // re-render filas según tipo (en balón serán N + 1)
      if (tbl) renderRows(nro?.value || 1);
    }

    document.getElementById('modalPropuesta')?.addEventListener('show.bs.modal', applyTipoUI);
    tipoSel?.addEventListener('change', applyTipoUI);
    applyTipoUI();

    /* ========= Cronograma ========= */
    function renderRows(nBase){
      if (!tbl) return;
      const n = Math.max(1, parseInt(nBase || '1', 10));
      const rows = n; // última = balón
      tbl.innerHTML = '';
      for (let i=1; i<=rows; i++){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="text-center">${to2(i)}</td>
          <td><input type="date" class="form-control form-control-sm cr-fecha"></td>
          <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm cr-monto"></td>
        `;
        tbl.appendChild(tr);
      }
      recalc();
    }

    function recalc(){
      if (!tbl) return;

      const rows = [...tbl.querySelectorAll('tr')];
      const convenio = num(total?.value);

      // Suma total
      let s = 0;
      rows.forEach(tr => s += num(tr.querySelector('.cr-monto')?.value));
      if (suma) suma.textContent = fmt2(s);

      // Guardar ocultos
      if (hid){
        hid.innerHTML = '';
        rows.forEach(tr => {
          const f = tr.querySelector('.cr-fecha')?.value || '';
          const m = tr.querySelector('.cr-monto')?.value || '';
          hid.insertAdjacentHTML('beforeend', `<input type="hidden" name="cron_fecha[]" value="${f}">`);
          hid.insertAdjacentHTML('beforeend', `<input type="hidden" name="cron_monto[]" value="${m}">`);
        });
      }

      // Validación única: suma total = convenio
      const ok = Math.abs(s - convenio) <= 0.01;
      if (btnGuardar) btnGuardar.disabled = !ok;
      if (suma) suma.classList.toggle('text-danger', !ok);
    }

    function genAuto(){
      if (!tbl) return;
      const n = Math.max(1, parseInt(nro?.value || '0', 10));
      if (!n) return;

      renderRows(n);

      const start = fIni?.value ? new Date(fIni.value + 'T00:00:00') : null;
      const convenio   = num(total?.value);
      const montoCuota = num(cuota?.value);
      const rows = [...tbl.querySelectorAll('tr')];

      rows.forEach((tr, idx) => {
        const f = tr.querySelector('.cr-fecha');
        const m = tr.querySelector('.cr-monto');
        if (start){
          const d = addMonthsNoOverflow(start, idx);
          f.valueAsDate = d;
        }
        const val = (montoCuota > 0) ? montoCuota : (convenio / rows.length);
        m.value = fmt2(val);
      });

      recalc();
    }

    // Eventos cronograma
    gen?.addEventListener('click', genAuto);
    nro?.addEventListener('change',   () => renderRows(nro.value));
    tblEl?.addEventListener('input',  e => { if (e.target.matches('.cr-monto, .cr-fecha')) recalc(); });
    total?.addEventListener('input',  recalc);
    fIni?.addEventListener('change',  () => {
      const v = fIni.value;
      if (!hintDia) return;
      if (!v){ hintDia.textContent = 'Día de pago: —'; return; }
      const d = new Date(v + 'T00:00:00');
      hintDia.textContent = `Día de pago: ${d.getDate()} de cada mes`;
    });

    // Enviar índice de la cuota balón (última fila) solo cuando aplique
    document.getElementById('formPropuesta')?.addEventListener('submit', () => {
      if (cronBalonInput) cronBalonInput.value = ''; // no usamos cuota balón
    });

    // ====== Selección de cuentas (chips + ocultos) ======
    const chkAll   = document.getElementById('chkAll');
    const chks     = Array.from(document.querySelectorAll('.chkOp'));
    const btnProp  = document.getElementById('btnPropuesta');
    const selCount = document.getElementById('selCount');

    function refreshSelection(){
      const selected = chks.filter(c => c.checked && !c.disabled).map(c => c.value).filter(Boolean);
      selCount && (selCount.textContent = String(selected.length));
      btnProp  && (btnProp.disabled = selected.length === 0);
      return selected;
    }
    chkAll?.addEventListener('change', () => {
      chks.forEach(c => { if(!c.disabled) c.checked = chkAll.checked; });
      refreshSelection();
    });
    chks.forEach(c => c.addEventListener('change', () => {
      const enabled = chks.filter(x => !x.disabled).length;
      const checked = chks.filter(x => x.checked && !x.disabled).length;
      if (enabled) chkAll.checked = (checked === enabled);
      refreshSelection();
    }));

    const modalProp = document.getElementById('modalPropuesta');
    const opsResumen = document.getElementById('opsResumen');
    const opsHidden  = document.getElementById('opsHidden');

    modalProp?.addEventListener('show.bs.modal', () => {
      const ops = refreshSelection();
      opsResumen.innerHTML = ops.length
        ? ops.map(o => `<span class="badge rounded-pill text-bg-light border me-1">${o}</span>`).join('')
        : '<span class="text-secondary">Ninguna</span>';

      opsHidden.innerHTML = '';
      ops.forEach(op => {
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = 'operaciones[]'; i.value = String(op);
        opsHidden.appendChild(i);
      });

      total?.dispatchEvent(new Event('input'));
    });

    // Render inicial
    renderRows(nro?.value || 1);
  })();
  /* ================== Utilidades generales de la vista ================== */

  // Tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>{ new bootstrap.Tooltip(el); });

  // Modal Nota — soporta data-nota y data-nota-json
  (function(){
    const modal = document.getElementById('modalNota');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', (ev) => {
      const btn = ev.relatedTarget;
      let txt = '';
      if (!btn) return;

      if (btn.hasAttribute('data-nota-json')) {
        try { txt = JSON.parse(btn.getAttribute('data-nota-json') || '""') || ''; }
        catch { txt = ''; }
      } else if (btn.hasAttribute('data-nota')) {
        txt = btn.getAttribute('data-nota') || '';
      }

      const tgt = modal.querySelector('#notaFull');
      if (tgt) tgt.textContent = String(txt);
    });
  })();

  // ===== Selección de cuentas (para el modal de propuesta)
  const chkAll   = document.getElementById('chkAll');
  const chks     = Array.from(document.querySelectorAll('.chkOp'));
  const btnProp  = document.getElementById('btnPropuesta');
  const selCount = document.getElementById('selCount');

  function refreshSelection(){
    const selected = chks.filter(c => c.checked && !c.disabled).map(c => c.value).filter(Boolean);
    selCount.textContent = String(selected.length);
    btnProp.disabled = selected.length === 0;
    return selected;
  }
  chkAll?.addEventListener('change', () => {
    chks.forEach(c => { if(!c.disabled) c.checked = chkAll.checked; });
    refreshSelection();
  });
  chks.forEach(c => c.addEventListener('change', () => {
    const enabled = chks.filter(x => !x.disabled).length;
    const checked = chks.filter(x => x.checked && !x.disabled).length;
    if (enabled) chkAll.checked = (checked === enabled);
    refreshSelection();
  }));

  // ===== Modal Propuesta
  const modalProp = document.getElementById('modalPropuesta');
  const opsResumen = document.getElementById('opsResumen');
  const opsHidden  = document.getElementById('opsHidden');

  modalProp?.addEventListener('show.bs.modal', () => {
    const ops = refreshSelection();

    opsResumen.innerHTML = ops.length
      ? ops.map(o => `<span class="badge rounded-pill text-bg-light border me-1">${o}</span>`).join('')
      : '<span class="text-secondary">Ninguna</span>';

    opsHidden.innerHTML = '';
    ops.forEach(op => {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = 'operaciones[]'; i.value = String(op);
      opsHidden.appendChild(i);
    });

    document.getElementById('cvTotal')?.dispatchEvent(new Event('input'));
  });

  // ===== Alternar bloques por tipo
  const tipo = document.getElementById('tipoPropuesta');
  const fCon = document.getElementById('formConvenio');
  const fCan = document.getElementById('formCancelacion');

  function setEnabled(container, enabled){
    if (!container) return;
    container.querySelectorAll('input,select,textarea,button').forEach(el=>{
      if (enabled) el.removeAttribute('disabled');
      else el.setAttribute('disabled','disabled');
    });
    if (!enabled){
      container.querySelectorAll('input:not([type="hidden"]),textarea').forEach(el=>{ el.value=''; });
    }
  }
  function req(el, on){
    if (!el) return;
    if (on) el.setAttribute('required','required'); else el.removeAttribute('required');
  }
  function toggleTipo(){
    const t = tipo.value;
    const isConv = (t === 'convenio' || t === 'convenio_balon');
    fCon.classList.toggle('d-none', !isConv);
    fCan.classList.toggle('d-none', isConv ? true : (t !== 'cancelacion'));

    setEnabled(fCon, isConv);
    setEnabled(fCan, !isConv && t === 'cancelacion');

    const fields = {
      convenio: ['nro_cuotas','monto_convenio'],
      convenio_balon: ['nro_cuotas','monto_convenio'], // <- añade esta línea
      cancelacion: ['fecha_pago_cancel','monto_cancel']
    };
    [...fields.convenio, ...fields.cancelacion]
      .forEach(n => req(document.querySelector(`[name="${n}"]`), false));
    (fields[t] || []).forEach(n => req(document.querySelector(`[name="${n}"]`), true));
  }
  tipo?.addEventListener('change', toggleTipo);
  toggleTipo();

  document.getElementById('formPropuesta')?.addEventListener('submit', () => {
    const tipo = (document.getElementById('tipoPropuesta')?.value || '').toLowerCase();
    const rows = document.querySelectorAll('#tblCrono tbody tr').length || 0;
    const hidden = document.getElementById('cronBalon');
    if (hidden) hidden.value = (tipo === 'convenio_balon' && rows) ? String(rows) : '';
  });
  
  // Hint opcional
  const fechaPagoConvenio = document.getElementById('fechaPagoConvenio') || document.querySelector('[name="fecha_pago"]');
  const hintDiaMes = document.getElementById('hintDiaMes');
  function actualizarHint(){
    const v = fechaPagoConvenio?.value || '';
    if (!hintDiaMes) return;
    if (!v) { hintDiaMes.textContent = 'Día de pago: —'; return; }
    const d = new Date(v + 'T00:00:00');
    if (isNaN(d)) { hintDiaMes.textContent = 'Día de pago: —'; return; }
    const dia = d.getDate();
    hintDiaMes.textContent = `Día de pago: ${dia} de cada mes (si el mes no tiene ese día, se ajusta al último)`;
  }
  fechaPagoConvenio?.addEventListener('change', actualizarHint);
  actualizarHint();

  /* ====== Selección de cuentas (para propuesta clásica) ====== */
  (function(){
    const chkAll   = document.getElementById('chkAll');
    const chks     = Array.from(document.querySelectorAll('.chkOp'));
    const btnProp  = document.getElementById('btnPropuesta');
    const selCount = document.getElementById('selCount');

    function refreshSelection(){
      const selected = chks.filter(c => c.checked && !c.disabled).map(c => c.value).filter(Boolean);
      if (selCount) selCount.textContent = String(selected.length);
      if (btnProp)  btnProp.disabled     = selected.length === 0;
      return selected;
    }
    chkAll?.addEventListener('change', () => {
      chks.forEach(c => { if(!c.disabled) c.checked = chkAll.checked; });
      refreshSelection();
    });
    chks.forEach(c => c.addEventListener('change', () => {
      const enabled = chks.filter(x => !x.disabled).length;
      const checked = chks.filter(x => x.checked && !x.disabled).length;
      if (enabled) chkAll.checked = (checked === enabled);
      refreshSelection();
    }));
    refreshSelection();
  })();

  /* ====== Generar CNA (por martillo) — AGRUPAR POR CUENTA ====== */
  (function(){
    const modal     = document.getElementById('modalCna');
    const opsHidden = document.getElementById('cnaOpsHidden');
    const opsList   = document.getElementById('cnaOpsList');
    const inCuenta  = document.getElementById('cnaCuentaInput');
    const lblCuenta = document.getElementById('cnaCuenta');
    const lblCosech = document.getElementById('cnaCosecha');
    const lblPlant  = document.getElementById('cnaPlantilla');

    function origenFromCosecha(c){
      c = (c||'').toUpperCase().trim();
      const FAA  = new Set(['BBVA3','BBVA4','BBVA5','BBVA6','CAJAAQP3']);
      const FAA2 = new Set(['BBVA7','BBVA8','CONFIANZA_5']);
      const KPI  = new Set([
        'BBVA1','BBVA2','CAJAAQP1','CAJAAQP2','CAJAAQP4','COMPARTAMOS_1','CONFIANZA','CONFIANZA_2','CONFIANZA_3',
        'CONFIANZA_4','CONFIANZA_6','CONFIANZA_7','CONFIANZA_8','CONFIANZA_9','CONFIANZA_10',
        'CONFIANZA_11','CONFIANZA_12','SEMBRANDO'
      ]);
      if (FAA.has(c))  return {origen:'FONDO ACREENCIA AREQUIPA', serie:'F',  plantilla:'cna_fondo_acreencia_arequipa.docx'};
      if (FAA2.has(c)) return {origen:'ACREENCIA II',            serie:'F2', plantilla:'cna_fondo_acreencia_arequipa_2.docx'};
      if (KPI.has(c))  return {origen:'KP INVEST SAC',           serie:'KPI',plantilla:'cna_kpinvest.docx'};
      return {origen:'(no reconocido)', serie:'—', plantilla:'—'};
    }

    modal?.addEventListener('show.bs.modal', (ev) => {
      const btn = ev.relatedTarget;
      if (!btn) return;

      const oper    = btn.getAttribute('data-oper')    || '';
      let   cuenta  = btn.getAttribute('data-cuenta')  || btn.closest('tr')?.getAttribute('data-cuenta') || '';
      const cosecha = btn.getAttribute('data-cosecha') || '';
      const entidad = btn.getAttribute('data-entidad') || '';

      // Fallback por si faltara data-cuenta en la fila
      if (!cuenta) {
        const tr = document.querySelector(`#tblCuentas tbody tr[data-oper="${oper}"]`);
        cuenta = tr?.getAttribute('data-cuenta') || tr?.querySelector('.genCnaBtn')?.getAttribute('data-cuenta') || '';
      }

      // UI modal (usamos CUENTA real)
      inCuenta.value        = cuenta;
      lblCuenta.textContent = cuenta || '—';
      lblCosech.textContent = cosecha || '—';
      const info = origenFromCosecha(cosecha);
      lblPlant.textContent  = `${info.origen} · Serie ${info.serie} · ${info.plantilla}`;

      // Operaciones incluidas = todas las filas con la MISMA CUENTA
      const rows = Array.from(document.querySelectorAll('#tblCuentas tbody tr'));
      const ops  = rows
        .filter(tr => (tr.getAttribute('data-cuenta') || '') === cuenta)
        .map(tr => tr.getAttribute('data-oper') || tr.querySelector('.genCnaBtn')?.getAttribute('data-oper') || '')
        .filter(Boolean);

      const uniq = [...new Set(ops.length ? ops : [oper].filter(Boolean))];

      // Chips UI
      opsList.innerHTML = uniq.map(op =>
        `<span class="badge rounded-pill text-bg-light border me-1">${op}</span>`
      ).join('');

      // Hidden inputs operaciones[]
      opsHidden.innerHTML = '';
      uniq.forEach(op => {
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = 'operaciones[]'; i.value = op;
        opsHidden.appendChild(i);
      });
    });
  })();
</script>
@endpush
