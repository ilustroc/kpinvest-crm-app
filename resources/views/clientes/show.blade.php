{{-- resources/views/clientes/show.blade.php --}}
@extends('layouts.app')
@section('title','Cliente '.$dni)
@section('crumb','Cliente')

@push('head')
  <link rel="stylesheet"
        href="{{ asset('css/clientes/show.css') }}?v={{ @filemtime(public_path('css/clientes/show.css')) }}">
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

        // CCD por DNI (para el bloque del encabezado)
        $ccdDocs = collect($ccdByDni[$dni] ?? []);
      @endphp
      <div class="d-flex flex-wrap gap-2 align-items-stretch">
        <div class="kpi-mini text-end">
          <div class="label">Deuda capital</div>
          <div class="value">S/ {{ number_format($totCapital,2) }}</div>
        </div>
        <div class="kpi-mini text-end">
          <div class="label">Deuda total</div>
          <div class="value">S/ {{ number_format($totDeuda,2) }}</div>
        </div>
        <div class="kpi-mini text-end">
          <div class="label">Pagos registrados</div>
          <div class="value">S/ {{ number_format($totPagos,2) }}</div>
        </div>

        {{-- ===== CCD en encabezado ===== --}}
        <div class="kpi-mini kpi-ccd text-start dropdown">
          @if($ccdDocs->isEmpty())
            <div class="value text-muted mt-1">Sin cartas</div>

          @elseif($ccdDocs->count() === 1)
            @php
              $d    = $ccdDocs->first();
              $href = $d->link
                ?: (preg_match('#^https?://#', (string)$d->pdf)
                    ? $d->pdf
                    : asset('storage/ccd/'.ltrim((string)$d->pdf,'/')));
            @endphp

            @if($href)
              <a href="{{ $href }}" target="_blank" class="btn btn-sm btn-outline-primary w-100 mt-1">
                <i class="bi bi-envelope-paper me-1"></i> Ver CCD
              </a>
            @else
              <div class="value text-muted mt-1">CCD no disponible</div>
            @endif

          @else
            @php $count = $ccdDocs->count(); @endphp
            <button class="btn btn-sm btn-outline-primary dropdown-toggle w-100 mt-1"
                    type="button" data-bs-toggle="dropdown">
              <i class="bi bi-envelope-paper me-1"></i>
              Cartas ({{ $count }})
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              @foreach($ccdDocs as $d)
                @php
                  $href = $d->link
                    ?: (preg_match('#^https?://#', (string)$d->pdf)
                        ? $d->pdf
                        : asset('storage/ccd/'.ltrim((string)$d->pdf,'/')));
                  $name = $d->pdf
                    ? pathinfo((string)$d->pdf, PATHINFO_FILENAME)
                    : ( ($d->cosecha ? 'CCD '.$d->cosecha : 'CCD').' #'.$d->id );
                @endphp
                @if($href)
                  <li>
                    <a class="dropdown-item" href="{{ $href }}" target="_blank">
                      {{ $name }}
                    </a>
                  </li>
                @endif
              @endforeach
            </ul>
          @endif
        </div>
        {{-- ===== /CCD encabezado ===== --}}
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
            <th class="text-nowrap">Asesor asignado</th>
            <th>Entidad</th>
            <th>Producto</th>
            <th>Cosecha</th>
            <th class="text-end text-nowrap">Deuda capital</th>
            <th class="text-end text-nowrap">Deuda total</th>
            <th class="text-nowrap">CNA(s)</th>
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
            <td class="text-nowrap">
              @if($c->asesor)
                  {{ $c->asesor }}
              @else
                <span class="text-secondary">Sin asignar</span>
              @endif
            </td>
            <td>{{ $c->entidad   ?? '—' }}</td>
            <td>{{ $c->producto  ?? '—' }}</td>
            <td>{{ $c->cosecha   ?? '—' }}</td>
            <td class="text-end text-nowrap">{{ number_format((float)($c->deuda_capital ?? $c->saldo_capital ?? 0), 2) }}</td>
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
  @php
    $canDeletePagos = in_array(strtolower((string)optional(Auth::user())->role), ['administrador','sistemas','supervisor','soporte']);
  @endphp

  <div class="card pad mb-3">
    <div class="d-flex justify-content-between align-items-center">
      <h2 class="h6 mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-receipt"></i><span>Pagos</span>
      </h2>

      <div class="d-flex align-items-center gap-2">
        @if($canDeletePagos)
          <div class="form-check form-switch me-2">
            <input class="form-check-input" type="checkbox" id="toggleDeletePagos">
            <label class="form-check-label small" for="toggleDeletePagos">Eliminar pagos</label>
          </div>
          <form id="frmDeletePagos" method="POST" action="{{ route('clientes.pagos.delete', $dni) }}"
                onsubmit="return confirm('¿Eliminar los pagos seleccionados?');" class="m-0">
            @csrf
            <button type="submit" id="btnDeletePagos" class="btn btn-danger btn-sm" disabled>
              <i class="bi bi-trash"></i> Eliminar seleccionados
            </button>
          </form>
        @endif

        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse"
                data-bs-target="#pagosCollapse">
          Ver/ocultar
        </button>
      </div>
    </div>

    <div id="pagosCollapse" class="collapse mt-2 show">
      <div class="table-responsive max-h-260">
        <table class="table table-sm align-middle tbl-compact" id="tblPagos">
          <thead class="position-sticky top-0 bg-body">
            <tr>
              @if($canDeletePagos)
                <th class="text-nowrap col-del d-none">
                  <input type="checkbox" id="chkAllPagos">
                </th>
              @endif
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
                $id      = $p->id ?? $p['id'] ?? null;
                $oper    = $p->operacion ?? $p['operacion'] ?? '-';
                $monto   = $p->monto_pagado ?? $p['monto_pagado'] ?? 0;
                $fecha   = $p->fecha ?? $p['fecha'] ?? null;
                $gestor  = $p->gestor ?? $p['gestor'] ?? '-';
                $cuenta  = $p->cuenta_recaudo ?? $p['cuenta_recaudo'] ?? '-';
              @endphp
              <tr>
                @if($canDeletePagos)
                  <td class="col-del d-none">
                    @if($id)
                      <input type="checkbox" name="ids[]" form="frmDeletePagos"
                            class="chkPago" value="{{ $id }}">
                    @endif
                  </td>
                @endif
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
        <form method="POST" action="{{ route('clientes.promesas.store', $dni) }}" id="formPropuesta" data-once>
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
                <label class="form-label">Monto de cuota (S/)</label>
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
                        <td>
                          <span id="cvSuma" class="fw-bold">0.00</span>
                          <div id="cvErr" class="small text-danger mt-1 d-none">
                            El total del cronograma debe coincidir con el Monto convenio.
                          </div>
                        </td>
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
      <form class="modal-content" method="POST" action="{{ route('clientes.cna.store', $dni) }}" data-once>  
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
  <script defer src="{{ asset('js/clientes/show.js') }}?v={{ @filemtime(public_path('js/clientes/show.js')) }}"></script>
@endpush