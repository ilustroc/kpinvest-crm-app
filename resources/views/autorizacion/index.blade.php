{{-- resources/views/autorizacion/index.blade.php --}}
@extends('layouts.app')
@section('title','Autorización de Promesas')
@section('crumb','Autorización')

@push('head')
<style>
  /* ======= Marca KP: verde primario + azul acento ======= */
  :root{
    --brand:#00a81c;        /* Verde KP (primario) */
    --brand-ink:#008517;    /* Hover/ink */
    --accent:#0b4ea2;       /* Azul KP (acento) */
    --accent-ink:#093f82;

    --surface:#ffffff; --surface-2:#f3f6fb; --border:#e8ecf3;
    --ink:#151a23; --muted:#6d7b8a;
  }

  /* Botonería con coherencia de marca */
  .btn-primary{ background:var(--brand); border-color:var(--brand) }
  .btn-primary:hover{ background:var(--brand-ink); border-color:var(--brand-ink) }
  .btn-outline-primary{ color:var(--accent); border-color:var(--accent) }
  .btn-outline-primary:hover{ color:#fff; background:var(--accent-ink); border-color:var(--accent-ink) }

  /* Inputs / buscador */
  .card .form-control{ background:var(--surface); border-color:var(--border) }
  .card .form-control::placeholder{ color:var(--muted) }
  .card .form-control:focus{
    border-color: color-mix(in oklab, var(--accent) 60%, var(--border));
    box-shadow: 0 0 0 .2rem color-mix(in oklab, var(--accent) 22%, transparent);
  }

  /* Tablas: encabezado fijo + hover suave con acento */
  .table-responsive{ max-height: none } /* deja crecer; los modales ya son scrollables */
  table thead th{
    position:sticky; top:0; z-index:1;
    background: color-mix(in oklab, var(--surface-2) 55%, transparent) !important;
    color: var(--ink);
    text-transform: uppercase; letter-spacing:.3px; font-size:.82rem;
    border-bottom:1px solid var(--border);
    box-shadow:0 3px 8px rgba(15,23,42,.06);
  }
  .table.table-hover tbody tr:hover{
    background: color-mix(in oklab, var(--accent) 10%, var(--brand) 6%);
  }

  /* Chips/badges suaves (si se usan en celdas) */
  .badge-soft{
    background:color-mix(in oklab, var(--brand) 12%, transparent);
    color:var(--brand);
    border:1px solid color-mix(in oklab, var(--brand) 24%, transparent);
    border-radius:999px; padding:.18rem .55rem; font-weight:600
  }

  /* Paginación bootstrap (si aparece) */
  .pagination .page-link{ border-color:var(--border); color:var(--ink) }
  .pagination .page-link:hover{
    background:color-mix(in oklab, var(--brand) 10%, transparent);
    border-color:color-mix(in oklab, var(--brand) 28%, transparent);
  }
  .pagination .page-item.active .page-link{
    background:var(--brand); border-color:var(--brand)
  }

  /* Scrollbar horizontal en tablas anchas */
  .table-responsive::-webkit-scrollbar{ height:10px }
  .table-responsive::-webkit-scrollbar-thumb{
    background: color-mix(in oklab, var(--accent) 22%, transparent); border-radius:10px
  }
</style>
@endpush

@section('content')
<div class="container-fluid">
  <div class="card">
    <div class="card-body d-flex align-items-center justify-content-between">
      <h5 class="mb-0">
        Autorización de Promesas
        <small class="text-muted ms-2">{{ $isSupervisor ? 'Bandeja del Supervisor' : 'Bandeja del Administrador' }}</small>
      </h5>
      <form class="d-flex" method="GET" action="{{ route('autorizacion') }}">
        <input name="q" value="{{ $q }}" class="form-control form-control-sm me-2" placeholder="DNI / Operación / Nota">
        <button class="btn btn-primary btn-sm">Buscar</button>
        @if($q)
          <a class="btn btn-outline-secondary btn-sm ms-2" href="{{ route('autorizacion') }}">Limpiar</a>
        @endif
      </form>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success mt-3 py-2">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger mt-3 py-2">{{ $errors->first() }}</div>
  @endif

  {{-- ====== Promesas ====== --}}
  <div class="card mt-3">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead>
            <tr>
              <th class="text-center">DNI</th>
              <th class="text-center">Operación(es)</th>
              <th class="text-center">Fecha</th>
              <th class="text-end">Monto (S/)</th>
              <th>Nota</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          @forelse($rows as $p)
            @php
              $fechaYmd = $p->fecha_promesa ? substr((string)$p->fecha_promesa,0,10) : '—';
              $fechaDmy = $p->fecha_promesa ? \Carbon\Carbon::parse($p->fecha_promesa)->format('d/m/Y') : '';
              $crono    = $p->cuotas_json ?? [];
              $hasBalon = (bool)($p->has_balon ?? false);
              $montoMostrar = (float)($p->monto > 0 ? $p->monto : $p->monto_convenio);
            @endphp
            <tr>
              <td class="text-center text-nowrap">{{ $p->dni }}</td>
              <td class="text-center text-nowrap">{{ $p->operacion ?: '—' }}</td>
              <td class="text-center text-nowrap">{{ $fechaYmd }}</td>
              <td class="text-end text-nowrap">{{ number_format($montoMostrar, 2) }}</td>
              <td class="text-truncate" style="max-width:420px" title="{{ $p->nota }}">{{ $p->nota }}</td>
              <td class="text-end">
                <button
                  class="btn btn-outline-secondary btn-sm js-ver-ficha"
                  data-tipo="{{ $p->tipo }}"
                  data-dni="{{ $p->dni }}"
                  data-operacion="{{ $p->operacion ?? '' }}"
                  data-fecha="{{ $fechaDmy }}"
                  data-asesor="{{ $p->asesor_nombre ?: $p->creador_nombre ?: '—' }}"
                  data-titular="{{ $p->titular ?? '—' }}"
                  data-deuda="{{ number_format((float)($p->deuda_total ?? 0),2) }}"
                  data-capital-raw="{{ (float)($p->saldo_capital ?? 0) }}"
                  data-negociado="{{ number_format($montoMostrar, 2) }}"
                  data-totalconvenio-raw="{{ (float)($p->monto_convenio ?? 0) }}"
                  data-detalle="{{ $p->nota ?? '' }}"
                  data-nota-sup="{{ $p->nota_preaprobacion ?? '' }}"
                  data-nota-gen="{{ $p->nota ?? '' }}"
                  data-crono='@json($crono)'
                  data-hasbalon="{{ $hasBalon ? 1 : 0 }}"
                  data-cuentas='@json($p->cuentas_cliente_json ?? [])'
                  data-bs-toggle="modal" data-bs-target="#modalFicha">
                  Ver ficha
                </button>
                @if($isSupervisor)
                  <button type="button" class="btn btn-primary btn-sm js-open-nota"
                          data-title="Pre-aprobar"
                          data-action="{{ route('autorizacion.preaprobar',$p) }}">
                    Pre-aprobar
                  </button>

                  <button type="button" class="btn btn-outline-danger btn-sm js-open-rechazo"
                          data-action="{{ route('autorizacion.rechazar.sup',$p) }}"
                          data-bs-toggle="modal" data-bs-target="#modalRechazo">
                    Rechazar
                  </button>
                @else
                  <button type="button" class="btn btn-primary btn-sm js-open-nota"
                          data-title="Aprobar"
                          data-action="{{ route('autorizacion.aprobar',$p) }}">
                    Aprobar
                  </button>

                  <button type="button" class="btn btn-outline-danger btn-sm js-open-rechazo"
                          data-action="{{ route('autorizacion.rechazar.admin',$p) }}"
                          data-bs-toggle="modal" data-bs-target="#modalRechazo">
                    Rechazar
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Sin pendientes.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Modal RECHAZO (nota obligatoria) --}}
  <div class="modal fade" id="modalRechazo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="formRechazo" method="POST" action="#">
        @csrf
        <div class="modal-header">
          <h6 class="modal-title">Motivo / Nota de rechazo</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <textarea name="nota_estado" id="motivoTxt" class="form-control" rows="5" maxlength="500" required></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-danger" type="submit">Rechazar</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal de NOTA --}}
  <div class="modal fade" id="modalNotaEstado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="formNotaEstado" method="POST" action="#">
        @csrf
        <div class="modal-header">
          <h6 class="modal-title" id="modalNotaEstadoTitulo">Agregar nota</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <textarea name="nota_estado" id="notaEstadoTxt" class="form-control" rows="5" maxlength="500"
                    placeholder="(opcional) Escribe una nota para esta decisión…"></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal FICHA --}}
  <div class="modal fade" id="modalFicha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">Detalle de Propuesta</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <strong>DNI:</strong> <span id="f_dni">—</span> &nbsp;&nbsp;
            <strong>Operación(es):</strong> <span id="f_op">—</span> &nbsp;&nbsp;
            <strong>Fecha:</strong> <span id="t_fecha">—</span>
          </div>

          {{-- Datos generales (simplificado) --}}
          <table class="table table-sm">
            <tbody>
              <tr><th style="width:220px">Tipo</th><td id="t_tipo">—</td></tr>
              <tr><th>Asesor</th><td id="t_asesor">—</td></tr>
              <tr><th>Cliente</th><td id="t_titular">—</td></tr>
              <tr><th>Deuda Total</th><td id="t_deuda">—</td></tr>
              <tr><th>Monto Negociado</th><td id="t_neg">—</td></tr>
            </tbody>
          </table>

          {{-- Notas visibles para ADMIN: general + supervisor (si existen) --}}
          <div class="mb-2">
            <strong>Notas</strong>
            <div class="small text-muted mt-1" id="nota_general_wrap" style="display:none">
              <span class="badge bg-secondary me-1">General</span>
              <span id="nota_general_txt"></span>
            </div>
            <div class="small text-muted mt-1" id="nota_sup_wrap" style="display:none">
              <span class="badge bg-info me-1">Supervisor</span>
              <span id="nota_sup_txt"></span>
            </div>
          </div>

          {{-- Acordeón por cuenta --}}
          <h6 class="mt-3">Cuentas incluidas</h6>
          <div class="accordion" id="acc_cuentas"></div>

          {{-- Cronograma (si aplica) --}}
          <div id="crono_wrap" class="d-none mt-3">
            <h6 class="mb-2" id="crono_titulo">Cronograma de cuotas</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th style="width:60px" class="text-center">#</th>
                    <th style="width:160px" class="text-center">Fecha</th>
                    <th class="text-end">Importe (S/)</th>
                    <th style="width:120px" class="text-center"></th>
                  </tr>
                </thead>
                <tbody id="crono_body"></tbody>
                <tfoot>
                  <tr>
                    <th colspan="2" class="text-end">TOTAL CONVENIO</th>
                    <th class="text-end" id="crono_total">0.00</th>
                    <th></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          {{-- /Cronograma --}}
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  {{-- ====== Solicitudes de CNA ====== --}}
  <div class="card mt-4">
    <div class="card-header fw-bold">Solicitudes de CNA</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th class="text-center">DNI</th>
              <th>Producto</th>
              <th class="text-center">Operación</th>
              <th class="text-center">Fecha</th>
              <th class="text-end col-actions">Acciones</th>
            </tr>
          </thead>
          <tbody>
          @forelse($cnaRows as $cna)
            @php
              $ops = collect((array)($cna->operaciones ?? []))
                        ->map(fn($x)=>trim((string)$x))
                        ->filter()
                        ->values();

              $productos = $ops->map(fn($op) => $prodByOp[(string)$op] ?? null)
                              ->filter()
                              ->unique()
                              ->values();

              $productoTxt = $productos->isEmpty()
                ? '—'
                : ($productos->count()===1 ? $productos->first() : $productos->implode(' · '));

              $opTxt = $ops->isEmpty() ? '—' : $ops->implode(', ');
            @endphp

            <tr>
              <td class="text-center text-nowrap">{{ $cna->dni }}</td>
              <td class="text-nowrap">{{ $productoTxt }}</td>
              <td class="text-center text-nowrap">{{ $opTxt }}</td>
              <td class="text-center text-nowrap">{{ optional($cna->created_at)->format('Y-m-d') }}</td>

              <td class="text-end">
                {{-- Ficha CNA (con pagos) --}}
                <button type="button"
                        class="btn btn-outline-secondary btn-sm me-1 js-ver-cna"
                        data-dni="{{ $cna->dni }}"
                        data-nrocarta="{{ $cna->nro_carta }}"
                        data-producto="{{ $productoTxt }}"
                        data-operaciones='@json($ops)'
                        data-fecha="{{ optional($cna->created_at)->format('Y-m-d') }}"
                        data-fecha-pago="{{ $cna->fecha_pago_realizado }}"
                        data-monto-pagado="{{ (float)($cna->monto_pagado ?? 0) }}"
                        data-observacion="{{ $cna->observacion }}"
                        data-pagos='@json($pagosByDni[$cna->dni] ?? [])'
                        data-bs-toggle="modal" data-bs-target="#modalCnaFicha">
                  Ficha
                </button>

                @if($isSupervisor)
                  <button type="button"
                          class="btn btn-primary btn-sm js-open-nota"
                          data-title="Pre-aprobar CNA"
                          data-action="{{ route('cna.preaprobar', $cna) }}">
                    Pre-aprobar
                  </button>
                  <button type="button"
                          class="btn btn-outline-danger btn-sm js-open-rechazo"
                          data-action="{{ route('cna.rechazar.sup', $cna) }}"
                          data-bs-toggle="modal" data-bs-target="#modalRechazo">
                    Rechazar
                  </button>
                @else
                  <button type="button"
                          class="btn btn-primary btn-sm js-open-nota"
                          data-title="Aprobar CNA"
                          data-action="{{ route('cna.aprobar', $cna) }}">
                    Aprobar
                  </button>
                  <button type="button"
                          class="btn btn-outline-danger btn-sm js-open-rechazo"
                          data-action="{{ route('cna.rechazar.admin', $cna) }}"
                          data-bs-toggle="modal" data-bs-target="#modalRechazo">
                    Rechazar
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Sin solicitudes de CNA.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="p-2">
        {{ $cnaRows->withQueryString()->onEachSide(1)->links('pagination::bootstrap-5') }}
      </div>
    </div>
  </div>

  {{-- Modal: Ficha CNA + pagos --}}
  <div class="modal fade" id="modalCnaFicha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">
            CNA — DNI <span id="cna_dni">—</span> · Carta <span id="cna_carta">—</span>
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">
          {{-- Datos solicitud CNA --}}
          <div class="row g-3">
            <div class="col-md-6">
              <table class="table table-sm mb-0">
                <tbody>
                  <tr><th style="width:220px">Fecha solicitud</th><td id="cna_fecha">—</td></tr>
                  <tr><th>Operación(es)</th><td id="cna_ops">—</td></tr>
                </tbody>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-sm mb-0">
                <tbody>
                  <tr><th style="width:220px">Fecha pago realizado</th><td id="cna_fecha_pago">—</td></tr>
                  <tr><th>Monto pagado (S/)</th><td id="cna_monto_pagado">0.00</td></tr>
                </tbody>
              </table>
            </div>
            <div class="col-12">
              <div class="fw-semibold mb-1">Observación</div>
              <div id="cna_obs" class="border rounded p-2 small" style="background:#fafbfc">—</div>
            </div>
          </div>

          {{-- Total de pagos (destacado al centro) --}}
          <div class="my-3 text-center">
            <div class="fw-bold">Total de pagos del cliente</div>
            <div id="cna_total_pagos" class="display-6" style="font-size:1.75rem">S/ 0.00</div>
          </div>

          {{-- Tabla de pagos del cliente --}}
          <h6 class="mt-3">Pagos realizados</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="position-sticky top-0 bg-body">
                <tr>
                  <th class="text-nowrap">Operación</th>
                  <th class="text-nowrap">Fecha</th>
                  <th class="text-end text-nowrap">Monto (S/)</th>
                  <th class="text-nowrap">Gestor</th>
                  <th class="text-nowrap">Entidad</th>
                  <th class="text-nowrap">Cosecha</th>
                  <th class="text-nowrap">Cuenta recaudo</th>
                </tr>
              </thead>
              <tbody id="cna_pagos_tbody"></tbody>
            </table>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
<script>
  // ============== Modal de NOTA (Pre-aprobar / Aprobar) ==============
  (function () {
    const frm   = document.getElementById('formNotaEstado');
    const title = document.getElementById('modalNotaEstadoTitulo');
    const txt   = document.getElementById('notaEstadoTxt');
    const modalEl = document.getElementById('modalNotaEstado');
    let modal;

    function ensureModal() {
      if (!modal) modal = new bootstrap.Modal(modalEl);
      return modal;
    }

    document.querySelectorAll('.js-open-nota').forEach(btn => {
      btn.addEventListener('click', () => {
        frm.setAttribute('action', btn.dataset.action || '#');
        title.textContent = btn.dataset.title || 'Agregar nota';
        txt.value = '';
        ensureModal().show();
        setTimeout(() => txt.focus(), 120);
      });
    });
  })();

  // =========================== Rechazo (modal) =========================
  document.querySelectorAll('.js-open-rechazo').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.getElementById('formRechazo').setAttribute('action', btn.dataset.action);
      setTimeout(()=> document.getElementById('motivoTxt').focus(), 150);
    });
  });

  // ========================= Helpers de formato ========================
  const fmt = (n)=> (Math.round((Number(n)||0)*100)/100).toFixed(2);

  // ============================ Ver FICHA ==============================
  document.querySelectorAll('.js-ver-ficha').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const tipo = (btn.dataset.tipo || '').toLowerCase();

      // Encabezado
      const setRaw = (id, v)=>{ const el = document.getElementById(id); if(el) el.textContent = v || '—'; };
      setRaw('f_dni',  btn.dataset.dni || '—');
      setRaw('t_fecha',btn.dataset.fecha || '—');

      // Datos generales (solo los vigentes)
      const set = (id, v)=>{ const el = document.getElementById('t_'+id); if(el) el.textContent = v || '—'; };
      set('tipo',   tipo ? (tipo==='cancelacion' ? 'Cancelación' : 'Convenio') : '—');
      set('asesor', btn.dataset.asesor);
      set('titular',btn.dataset.titular);
      set('deuda',  btn.dataset.deuda);
      set('neg',    btn.dataset.negociado);

      // Notas (robusto con fallbacks)
      const notaGen = [btn.dataset.notaGen, btn.dataset.detalle, btn.dataset.nota]
        .map(v => (v||'').trim()).find(Boolean) || '';
      const notaSup = [btn.dataset.notaSup, btn.dataset.notaPreaprobacion]
        .map(v => (v||'').trim()).find(Boolean) || '';

      const ngWrap = document.getElementById('nota_general_wrap');
      const nsWrap = document.getElementById('nota_sup_wrap');
      if (ngWrap) {
        if (notaGen) { ngWrap.style.display='block'; document.getElementById('nota_general_txt').textContent = notaGen; }
        else { ngWrap.style.display='none'; }
      }
      if (nsWrap) {
        if (notaSup) { nsWrap.style.display='block'; document.getElementById('nota_sup_txt').textContent = notaSup; }
        else { nsWrap.style.display='none'; }
      }

      // ===== Acordeón por cuenta =====
      const acc = document.getElementById('acc_cuentas');
      if (acc) {
        acc.innerHTML = '';
        let cuentas = [];
        try {
          const raw = btn.getAttribute('data-cuentas');
          cuentas = raw ? JSON.parse(raw) : [];
        } catch(e) { cuentas = []; }

        setRaw('f_op', btn.dataset.operacion || '—');

        if (!cuentas.length) {
          acc.innerHTML = '<div class="text-secondary small">No se encontraron cuentas asociadas.</div>';
        } else {
          cuentas.forEach((c, idx)=>{
            const id = 'accItem_'+idx;
            const anioCastigo = c && c.fecha_castigo ? String(c.fecha_castigo).slice(0,4) : '—';
            const html = `
              <div class="accordion-item">
                <h2 class="accordion-header" id="${id}_h">
                  <button class="accordion-button ${idx>0?'collapsed':''}" type="button"
                          data-bs-toggle="collapse" data-bs-target="#${id}_c"
                          aria-expanded="${idx===0?'true':'false'}" aria-controls="${id}_c">
                    Operación ${c?.operacion || '—'} · ${c?.entidad || '—'} · ${c?.producto || '—'} · ${c?.cosecha || '—'}
                  </button>
                </h2>
                <div id="${id}_c" class="accordion-collapse collapse ${idx===0?'show':''}"
                    aria-labelledby="${id}_h" data-bs-parent="#acc_cuentas">
                  <div class="accordion-body p-2">
                    <table class="table table-sm mb-0">
                      <tbody>
                        <tr><th style="width:220px">Número de Operación</th><td>${c?.operacion || '—'}</td></tr>
                        <tr><th>Año Castigo</th><td>${anioCastigo}</td></tr>
                        <tr><th>Entidad</th><td>${c?.entidad || '—'}</td></tr>
                        <tr><th>Producto</th><td>${c?.producto || '—'}</td></tr>
                        <tr><th>Cosecha</th><td>${c?.cosecha || '—'}</td></tr>
                        <tr><th>Capital</th><td>S/ ${fmt(c?.saldo_capital)}</td></tr>
                        <tr><th>Deuda Total</th><td>S/ ${fmt(c?.deuda_total)}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>`;
            acc.insertAdjacentHTML('beforeend', html);
          });
        }
      }

      // ===== Cronograma (oculta si es cancelación)
      const cronoWrap  = document.getElementById('crono_wrap');
      const cronoBody  = document.getElementById('crono_body');
      const cronoTotal = document.getElementById('crono_total');
      const filaBalon  = document.getElementById('fila_balon');
      const cronoBalon = document.getElementById('crono_balon');
      const titulo     = document.getElementById('crono_titulo');

      let crono = [];
      try { crono = JSON.parse(btn.getAttribute('data-crono') || '[]'); } catch(_) { crono = []; }

      if (tipo === 'cancelacion') { cronoWrap.classList.add('d-none'); return; }

      // Detecta balón SOLO si fue seleccionado o existe una cuota marcada como balón
      const hasBalon = (btn.dataset.hasbalon === '1') ||
                       crono.some(r => r?.es_balon === true || r?.es_balon === 1 || r?.es_balon === '1');

      titulo.textContent = hasBalon ? 'Cronograma de cuotas (con balón)' : 'Cronograma de cuotas';

      cronoBody.innerHTML = '';
      let sum = 0;
      crono.forEach(r=>{
        sum += Number(r.monto) || 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="text-center">${String(r.nro ?? '').padStart(2,'0')}</td>
          <td class="text-center">${r.fecha || '—'}</td>
          <td class="text-end">${fmt(r.monto)}</td>
          <td class="text-center">${(r.es_balon ? 'BALÓN' : '')}</td>
        `;
        cronoBody.appendChild(tr);
      });
      cronoTotal.textContent = fmt(sum);
      cronoWrap.classList.remove('d-none');
    });
  });

  // ============================ FICHA CNA (fetch pagos como el modal antiguo) ======================
  (function(){
    const money = (v)=> Number(String(v ?? 0).replaceAll(',','')).toLocaleString('es-PE', {minimumFractionDigits:2, maximumFractionDigits:2});
    const $ = (id)=> document.getElementById(id);
    const tbody = $('cna_pagos_tbody');

    // Usa la misma ruta que tu modal de pagos anterior
    const RUTA_PAGOS = @json(route('autorizacion.pagos', '__DNI__')); // placeholder

    function setRowsEmpty(){
      if (!tbody) return;
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Sin pagos registrados.</td></tr>';
    }
    function addPagoRow(p){
      const tr = document.createElement('tr');
      const fechaStr = p.fecha ? new Date(p.fecha).toLocaleDateString('es-PE') : '—';
      tr.innerHTML = `
        <td class="text-nowrap">${p.operacion ?? p.oper ?? '—'}</td>
        <td class="text-nowrap">${fechaStr}</td>
        <td class="text-end text-nowrap">${money(p.monto_pagado ?? p.monto ?? 0)}</td>
        <td class="text-nowrap">${p.gestor ?? '—'}</td>
        <td class="text-nowrap">${p.entidad ?? '—'}</td>
        <td class="text-nowrap">${p.cosecha ?? '—'}</td>
        <td class="text-nowrap">${p.cuenta_recaudo ?? p.cuenta ?? '—'}</td>
      `;
      tbody.appendChild(tr);
    }

    document.querySelectorAll('.js-ver-cna').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const dni = (btn.dataset.dni || '').trim();
        // Helper: "2025-10-31 00:00:00" o "2025-10-31" -> "31/10/2025"
        const toDMY = (val) => {
          if (!val) return '—';
          const s = String(val).trim();
          const datePart = s.split(' ')[0];          // "2025-10-31"
          const sep = datePart.includes('-') ? '-' : '/';
          const [y, m, d] = datePart.split(sep);
          if (y && m && d) return `${d.padStart(2,'0')}/${m.padStart(2,'0')}/${y}`;
          return s;
        };
        
        // Cabecera
        $('cna_dni').textContent    = dni || '—';
        $('cna_carta').textContent  = btn.dataset.nrocarta || '—';

        // Datos solicitud básicos
        $('cna_fecha').textContent       = toDMY(btn.dataset.fecha)

        // Operaciones (del data-atributo)
        let ops = [];
        try { ops = JSON.parse(btn.getAttribute('data-operaciones')||'[]'); } catch(_){}
        $('cna_ops').textContent         = ops.length ? ops.join(', ') : '—';

        // cna_solicitudes
        $('cna_fecha_pago').textContent  = toDMY(btn.dataset.fechaPago); 
        $('cna_monto_pagado').textContent = money(btn.dataset.montoPagado);
        $('cna_obs').textContent          = (btn.dataset.observacion || '—');

        // Pagos por DNI (fetch como tu modal antiguo)
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-3">Cargando…</td></tr>';
        let total = 0;
        try{
          const url = RUTA_PAGOS.replace('__DNI__', encodeURIComponent(dni || ''));
          const r   = await fetch(url, { headers: { 'Accept': 'application/json' } });
          if (!r.ok) throw new Error('HTTP '+r.status);
          const j   = await r.json();
          const arr = Array.isArray(j.pagos) ? j.pagos : [];

          if (!arr.length){ setRowsEmpty(); }
          else{
            tbody.innerHTML = '';
            arr.forEach(p=>{
              total += Number(String(p.monto_pagado ?? p.monto ?? 0).toString().replaceAll(',','')) || 0;
              addPagoRow(p);
            });
          }
        }catch(e){
          console.error('CNA pagos fetch error:', e);
          tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">Error cargando pagos.</td></tr>';
        }
        $('cna_total_pagos').textContent = 'S/ ' + money(total);
      });
    });
  })();
</script>
@endpush
