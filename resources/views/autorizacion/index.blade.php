{{-- resources/views/autorizacion/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Autorizacion de Promesas')
@section('crumb', 'Autorizacion')
@section('tailwind_only', true)

@section('content')
<x-layout.page-shell
  data-module="autorizacion-index"
  data-pagos-url-template="{{ route('autorizacion.pagos', '__DNI__') }}"
>
  <x-layout.page-header
    title="Autorizacion"
    subtitle="{{ $isSupervisor ? 'Bandeja del Supervisor' : 'Bandeja del Administrador' }}"
  >
  </x-layout.page-header>

  @if(session('ok'))
    <x-feedback.alert variant="success">{{ session('ok') }}</x-feedback.alert>
  @endif

  @if($errors->any())
    <x-feedback.alert variant="danger">{{ $errors->first() }}</x-feedback.alert>
  @endif

  <x-ui.card title="Promesas de pago">
    <x-tables.table>
      <thead>
        <tr>
          <x-tables.th>DNI</x-tables.th>
          <x-tables.th>Operacion(es)</x-tables.th>
          <x-tables.th>Fecha</x-tables.th>
          <x-tables.th align="right">Monto (S/)</x-tables.th>
          <x-tables.th>Nota</x-tables.th>
          <x-tables.th align="right">Acciones</x-tables.th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $p)
          @php
            $fechaYmd = $p->fecha_promesa ? substr((string) $p->fecha_promesa, 0, 10) : '-';
            $fechaDmy = $p->fecha_promesa ? \Carbon\Carbon::parse($p->fecha_promesa)->format('d/m/Y') : '';
            $crono = $p->cuotas_json ?? [];
            $hasBalon = (bool) ($p->has_balon ?? false);
            $montoMostrar = (float) ($p->monto > 0 ? $p->monto : $p->monto_convenio);
          @endphp
          <tr>
            <x-tables.td>{{ $p->dni }}</x-tables.td>
            <x-tables.td class="max-w-56 truncate">{{ $p->operacion ?: '-' }}</x-tables.td>
            <x-tables.td>{{ $fechaYmd }}</x-tables.td>
            <x-tables.td align="right">{{ number_format($montoMostrar, 2) }}</x-tables.td>
            <x-tables.td class="max-w-72 truncate" title="{{ $p->nota }}">{{ $p->nota ?: '-' }}</x-tables.td>
            <x-tables.td align="right">
              <div class="flex flex-wrap justify-end gap-2">
                <x-ui.button
                  type="button"
                  variant="secondary"
                  size="sm"
                  class="js-ver-ficha"
                  data-modal-open="#modalFicha"
                  data-tipo="{{ $p->tipo }}"
                  data-dni="{{ $p->dni }}"
                  data-operacion="{{ $p->operacion ?? '' }}"
                  data-fecha="{{ $fechaDmy }}"
                  data-asesor="{{ $p->asesor_nombre ?: $p->creador_nombre ?: '-' }}"
                  data-titular="{{ $p->titular ?? '-' }}"
                  data-deuda="{{ number_format((float) ($p->deuda_total ?? 0), 2) }}"
                  data-capital-raw="{{ (float) ($p->saldo_capital ?? 0) }}"
                  data-negociado="{{ number_format($montoMostrar, 2) }}"
                  data-totalconvenio-raw="{{ (float) ($p->monto_convenio ?? 0) }}"
                  data-detalle="{{ $p->nota ?? '' }}"
                  data-nota-sup="{{ $p->nota_preaprobacion ?? '' }}"
                  data-nota-gen="{{ $p->nota ?? '' }}"
                  data-crono='@json($crono)'
                  data-hasbalon="{{ $hasBalon ? 1 : 0 }}"
                  data-cuentas='@json($p->cuentas_cliente_json ?? [])'
                >
                  Ver ficha
                </x-ui.button>

                @if($isSupervisor)
                  <x-ui.button
                    type="button"
                    size="sm"
                    class="js-open-nota"
                    data-modal-open="#modalNotaEstado"
                    data-title="Pre-aprobar"
                    data-action="{{ route('autorizacion.preaprobar', $p) }}"
                  >
                    Pre-aprobar
                  </x-ui.button>
                  <x-ui.button
                    type="button"
                    variant="danger"
                    size="sm"
                    class="js-open-rechazo"
                    data-modal-open="#modalRechazo"
                    data-action="{{ route('autorizacion.rechazar.sup', $p) }}"
                  >
                    Rechazar
                  </x-ui.button>
                @else
                  <x-ui.button
                    type="button"
                    size="sm"
                    class="js-open-nota"
                    data-modal-open="#modalNotaEstado"
                    data-title="Aprobar"
                    data-action="{{ route('autorizacion.aprobar', $p) }}"
                  >
                    Aprobar
                  </x-ui.button>
                  <x-ui.button
                    type="button"
                    variant="danger"
                    size="sm"
                    class="js-open-rechazo"
                    data-modal-open="#modalRechazo"
                    data-action="{{ route('autorizacion.rechazar.admin', $p) }}"
                  >
                    Rechazar
                  </x-ui.button>
                @endif
              </div>
            </x-tables.td>
          </tr>
        @empty
          <x-tables.empty-row colspan="6" message="Sin pendientes." />
        @endforelse
      </tbody>
    </x-tables.table>
  </x-ui.card>

  <x-ui.card title="Solicitudes de CNA">
    <x-tables.table>
      <thead>
        <tr>
          <x-tables.th>DNI</x-tables.th>
          <x-tables.th>Producto</x-tables.th>
          <x-tables.th>Operacion</x-tables.th>
          <x-tables.th>Fecha</x-tables.th>
          <x-tables.th align="right">Acciones</x-tables.th>
        </tr>
      </thead>
      <tbody>
        @forelse($cnaRows as $cna)
          @php
            $ops = collect((array) ($cna->operaciones ?? []))
                ->map(fn ($x) => trim((string) $x))
                ->filter()
                ->values();

            $productos = $ops->map(fn ($op) => $prodByOp[(string) $op] ?? null)
                ->filter()
                ->unique()
                ->values();

            $productoTxt = $productos->isEmpty()
                ? '-'
                : ($productos->count() === 1 ? $productos->first() : $productos->implode(' / '));

            $opTxt = $ops->isEmpty() ? '-' : $ops->implode(', ');
          @endphp

          <tr>
            <x-tables.td>{{ $cna->dni }}</x-tables.td>
            <x-tables.td>{{ $productoTxt }}</x-tables.td>
            <x-tables.td class="max-w-64 truncate">{{ $opTxt }}</x-tables.td>
            <x-tables.td>{{ optional($cna->created_at)->format('Y-m-d') }}</x-tables.td>
            <x-tables.td align="right">
              <div class="flex flex-wrap justify-end gap-2">
                <x-ui.button
                  type="button"
                  variant="secondary"
                  size="sm"
                  class="js-ver-cna"
                  data-modal-open="#modalCnaFicha"
                  data-dni="{{ $cna->dni }}"
                  data-nrocarta="{{ $cna->nro_carta }}"
                  data-producto="{{ $productoTxt }}"
                  data-operaciones='@json($ops)'
                  data-fecha="{{ optional($cna->created_at)->format('Y-m-d') }}"
                  data-fecha-pago="{{ $cna->fecha_pago_realizado }}"
                  data-monto-pagado="{{ (float) ($cna->monto_pagado ?? 0) }}"
                  data-observacion="{{ $cna->observacion }}"
                >
                  Ficha
                </x-ui.button>

                @if($isSupervisor)
                  <x-ui.button
                    type="button"
                    size="sm"
                    class="js-open-nota"
                    data-modal-open="#modalNotaEstado"
                    data-title="Pre-aprobar CNA"
                    data-action="{{ route('cna.preaprobar', $cna) }}"
                  >
                    Pre-aprobar
                  </x-ui.button>
                  <x-ui.button
                    type="button"
                    variant="danger"
                    size="sm"
                    class="js-open-rechazo"
                    data-modal-open="#modalRechazo"
                    data-action="{{ route('cna.rechazar.sup', $cna) }}"
                  >
                    Rechazar
                  </x-ui.button>
                @else
                  <x-ui.button
                    type="button"
                    size="sm"
                    class="js-open-nota"
                    data-modal-open="#modalNotaEstado"
                    data-title="Aprobar CNA"
                    data-action="{{ route('cna.aprobar', $cna) }}"
                  >
                    Aprobar
                  </x-ui.button>
                  <x-ui.button
                    type="button"
                    variant="danger"
                    size="sm"
                    class="js-open-rechazo"
                    data-modal-open="#modalRechazo"
                    data-action="{{ route('cna.rechazar.admin', $cna) }}"
                  >
                    Rechazar
                  </x-ui.button>
                @endif
              </div>
            </x-tables.td>
          </tr>
        @empty
          <x-tables.empty-row colspan="5" message="Sin solicitudes de CNA." />
        @endforelse
      </tbody>
    </x-tables.table>
  </x-ui.card>

  <x-autorizacion.decision-modal
    id="modalRechazo"
    form-id="formRechazo"
    title-id="modalRechazoTitulo"
    textarea-id="motivoTxt"
    title="Motivo / Nota de rechazo"
    placeholder="Describe el motivo del rechazo."
    confirm-text="Rechazar"
    variant="danger"
    required
  />

  <x-autorizacion.decision-modal
    id="modalNotaEstado"
    form-id="formNotaEstado"
    title-id="modalNotaEstadoTitulo"
    textarea-id="notaEstadoTxt"
    title="Agregar nota"
    placeholder="(opcional) Escribe una nota para esta decision..."
    confirm-text="Guardar"
  />

  <x-ui.modal id="modalFicha" title="Detalle de Propuesta" max-width="5xl">
    <div class="max-h-[78vh] space-y-5 overflow-y-auto px-5 py-4">
      <div class="grid gap-3 md:grid-cols-3">
        <x-autorizacion.detail-field label="DNI" id="f_dni" />
        <x-autorizacion.detail-field label="Operacion(es)" id="f_op" />
        <x-autorizacion.detail-field label="Fecha" id="t_fecha" />
        <x-autorizacion.detail-field label="Tipo" id="t_tipo" />
        <x-autorizacion.detail-field label="Asesor" id="t_asesor" />
        <x-autorizacion.detail-field label="Cliente" id="t_titular" />
        <x-autorizacion.detail-field label="Deuda Total" id="t_deuda" />
        <x-autorizacion.detail-field label="Monto Negociado" id="t_neg" />
      </div>

      <section>
        <h3 class="text-sm font-bold uppercase tracking-wide text-kp-muted">Notas</h3>
        <div class="mt-2 grid gap-2">
          <div id="nota_general_wrap" class="hidden rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink">
            <x-ui.badge class="mr-2">General</x-ui.badge>
            <span id="nota_general_txt"></span>
          </div>
          <div id="nota_sup_wrap" class="hidden rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink">
            <x-ui.badge variant="info" class="mr-2">Supervisor</x-ui.badge>
            <span id="nota_sup_txt"></span>
          </div>
        </div>
      </section>

      <section>
        <h3 class="text-sm font-bold uppercase tracking-wide text-kp-muted">Cuentas incluidas</h3>
        <div id="acc_cuentas" class="mt-2 space-y-3"></div>
      </section>

      <section id="crono_wrap" class="hidden">
        <h3 id="crono_titulo" class="text-sm font-bold uppercase tracking-wide text-kp-muted">Cronograma de cuotas</h3>
        <div class="mt-2">
          <x-tables.table>
            <thead>
              <tr>
                <x-tables.th>#</x-tables.th>
                <x-tables.th>Fecha</x-tables.th>
                <x-tables.th align="right">Importe (S/)</x-tables.th>
                <x-tables.th>Tipo</x-tables.th>
              </tr>
            </thead>
            <tbody id="crono_body"></tbody>
            <tfoot>
              <tr>
                <x-tables.td colspan="2" align="right" class="font-bold">TOTAL CONVENIO</x-tables.td>
                <x-tables.td align="right" id="crono_total" class="font-bold">0.00</x-tables.td>
                <x-tables.td />
              </tr>
            </tfoot>
          </x-tables.table>
        </div>
      </section>
    </div>

    <div class="flex justify-end border-t border-kp-border px-5 py-4">
      <x-ui.button type="button" variant="secondary" data-modal-close>Cerrar</x-ui.button>
    </div>
  </x-ui.modal>

  <x-ui.modal id="modalCnaFicha" title="Ficha CNA" max-width="5xl">
    <div class="max-h-[78vh] space-y-5 overflow-y-auto px-5 py-4">
      <div class="grid gap-3 md:grid-cols-2">
        <x-autorizacion.detail-field label="DNI" id="cna_dni" />
        <x-autorizacion.detail-field label="Carta" id="cna_carta" />
        <x-autorizacion.detail-field label="Fecha solicitud" id="cna_fecha" />
        <x-autorizacion.detail-field label="Operacion(es)" id="cna_ops" />
        <x-autorizacion.detail-field label="Fecha pago realizado" id="cna_fecha_pago" />
        <x-autorizacion.detail-field label="Monto pagado (S/)" id="cna_monto_pagado" />
      </div>

      <section>
        <h3 class="text-sm font-bold uppercase tracking-wide text-kp-muted">Observacion</h3>
        <div id="cna_obs" class="mt-2 rounded-md border border-kp-border bg-slate-50 px-3 py-2 text-sm text-kp-ink">-</div>
      </section>

      <section class="rounded-lg border border-kp-border bg-kp-green-soft px-4 py-4 text-center">
        <p class="text-sm font-semibold text-kp-green-dark">Total de pagos del cliente</p>
        <p id="cna_total_pagos" class="mt-1 text-2xl font-black text-kp-ink">S/ 0.00</p>
      </section>

      <section>
        <h3 class="text-sm font-bold uppercase tracking-wide text-kp-muted">Pagos realizados</h3>
        <div class="mt-2">
          <x-tables.table>
            <thead>
              <tr>
                <x-tables.th>Operacion</x-tables.th>
                <x-tables.th>Fecha</x-tables.th>
                <x-tables.th align="right">Monto (S/)</x-tables.th>
                <x-tables.th>Gestor</x-tables.th>
                <x-tables.th>Entidad</x-tables.th>
                <x-tables.th>Cosecha</x-tables.th>
                <x-tables.th>Cuenta recaudo</x-tables.th>
              </tr>
            </thead>
            <tbody id="cna_pagos_tbody"></tbody>
          </x-tables.table>
        </div>
      </section>
    </div>

    <div class="flex justify-end border-t border-kp-border px-5 py-4">
      <x-ui.button type="button" variant="secondary" data-modal-close>Cerrar</x-ui.button>
    </div>
  </x-ui.modal>
</x-layout.page-shell>
@endsection
