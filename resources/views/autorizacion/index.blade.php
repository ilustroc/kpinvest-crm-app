{{-- resources/views/autorizacion/index.blade.php --}}
@extends('layouts.app')
@section('title','Autorización')
@section('crumb','Autorización')

@push('head')
  <link rel="stylesheet"
        href="{{ asset('css/auth/autorizacion.css') }}?v={{ @filemtime(public_path('css/auth/autorizacion.css')) }}">

  <meta name="autz-pagos-url" content="{{ route('autorizacion.pagos', '__DNI__') }}">
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
  <script defer src="{{ asset('js/auth/autorizacion-nota-estado.js') }}?v={{ @filemtime(public_path('js/auth/autorizacion-nota-estado.js')) }}"></script>
  <script defer src="{{ asset('js/auth/autorizacion-ficha.js') }}?v={{ @filemtime(public_path('js/auth/autorizacion-ficha.js')) }}"></script>
  <script defer src="{{ asset('js/auth/autorizacion-cna.js') }}?v={{ @filemtime(public_path('js/auth/autorizacion-cna.js')) }}"></script>
@endpush
