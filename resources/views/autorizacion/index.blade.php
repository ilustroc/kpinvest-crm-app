{{-- resources/views/autorizacion/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Autorizaciones')
@section('crumb', 'Autorizaciones')
@section('tailwind_only', true)

@section('content')
@php
    $promesaCount = $rows->count();
    $cnaCount = $cnaRows->total();
    $roleLabel = $isSupervisor ? 'Supervisor' : 'Administrador';
    $statusLabel = $isSupervisor ? 'Pendientes' : 'Preaprobadas';
    $statusDescription = $isSupervisor
        ? 'Solicitudes pendientes de preaprobación.'
        : 'Solicitudes preaprobadas listas para aprobación.';
@endphp

<x-layout.page-shell
  data-module="autorizacion-index"
  data-pagos-url-template="{{ route('autorizacion.pagos', '__DNI__') }}"
>
  <x-layout.page-header
    title="Autorizaciones"
    subtitle="Revisa, aprueba o rechaza promesas de pago y solicitudes CNA."
  >
    <x-slot:actions>
      <x-ui.badge :variant="$isSupervisor ? 'warning' : 'info'">{{ $statusLabel }}</x-ui.badge>
      <span class="inline-flex items-center rounded-md border border-kp-border bg-white px-2 py-1 text-[10.5px] font-semibold text-slate-600">
        Perfil: {{ $roleLabel }}
      </span>
    </x-slot:actions>
  </x-layout.page-header>

  @if(session('ok'))
    <x-feedback.alert variant="success">{{ session('ok') }}</x-feedback.alert>
  @endif

  @if($errors->any())
    <x-feedback.alert variant="danger">{{ $errors->first() }}</x-feedback.alert>
  @endif

  <section class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3">
      <div class="flex min-w-0 items-center gap-2.5">
        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9M7.5 12h6M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v12a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 18V6a2.25 2.25 0 0 1 2.25-2.25Z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-[15px] font-semibold text-kp-ink">Promesas de pago</h2>
            <x-ui.badge :variant="$isSupervisor ? 'warning' : 'info'">{{ $statusLabel }}</x-ui.badge>
          </div>
          <p class="mt-0.5 text-[11px] text-kp-muted">{{ $statusDescription }}</p>
        </div>
      </div>
      <span class="inline-flex items-center rounded-md border border-kp-border bg-kp-surface-muted px-2 py-1 text-[10.5px] font-semibold text-slate-600">
        {{ number_format($promesaCount) }} {{ $promesaCount === 1 ? 'solicitud' : 'solicitudes' }}
      </span>
    </header>

    <x-tables.table :flush="true" :contained="true" min-width="min-w-[1080px]">
      <thead>
        <tr>
          <x-tables.th>Cliente</x-tables.th>
          <x-tables.th>Operación(es)</x-tables.th>
          <x-tables.th>Tipo</x-tables.th>
          <x-tables.th align="right">Monto</x-tables.th>
          <x-tables.th>Fecha</x-tables.th>
          <x-tables.th>Solicitante</x-tables.th>
          <x-tables.th align="right" class="xl:sticky xl:right-0 xl:z-10 xl:min-w-[240px] xl:bg-kp-surface-muted xl:shadow-[-1px_0_0_0_#e5e7eb]">Acciones</x-tables.th>
        </tr>
      </thead>
      <tbody class="divide-y divide-kp-border bg-white">
        @forelse($rows as $p)
          @php
            $fechaYmd = $p->fecha_promesa ? substr((string) $p->fecha_promesa, 0, 10) : '-';
            $fechaDmy = $p->fecha_promesa ? \Carbon\Carbon::parse($p->fecha_promesa)->format('d/m/Y') : '-';
            $crono = $p->cuotas_json ?? [];
            $cronoJson = json_encode($crono, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
            $cuentas = $p->cuentas_json ?? $p->cuentas_cliente_json ?? [];
            $cuentasJson = json_encode($cuentas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
            $hasBalon = (bool) ($p->has_balon ?? false);
            $montoMostrar = (float) ($p->monto > 0 ? $p->monto : $p->monto_convenio);
            $tipoKey = strtolower((string) $p->tipo);
            $tipoLabel = match ($tipoKey) {
                'cancelacion' => 'Cancelación',
                'convenio' => 'Convenio',
                default => $p->tipo ? ucfirst((string) $p->tipo) : '—',
            };
            $tipoVariant = match ($tipoKey) {
                'cancelacion' => 'warning',
                'convenio' => 'info',
                default => 'neutral',
            };
            $estadoPromesa = $p->workflow_estado ?: $p->estado ?: ($isSupervisor ? 'pendiente' : 'preaprobada');
            $solicitante = $p->creador_nombre ?: '—';
          @endphp
          <tr class="group transition-colors hover:bg-slate-50/80">
            <x-tables.td class="min-w-44">
              <div class="font-semibold text-kp-ink">{{ $p->titular ?: '—' }}</div>
              <div class="mt-0.5 font-mono text-[11px] tabular-nums text-kp-muted">DNI {{ $p->dni ?: '—' }}</div>
            </x-tables.td>
            <x-tables.td class="max-w-56 truncate" :title="$p->operacion"><span class="font-mono text-[12px] text-slate-600">{{ $p->operacion ?: '—' }}</span></x-tables.td>
            <x-tables.td><x-ui.badge :variant="$tipoVariant">{{ $tipoLabel }}</x-ui.badge></x-tables.td>
            <x-tables.td align="right"><span class="font-semibold tabular-nums text-kp-ink">{{ \App\Support\Money::format($montoMostrar, 'PEN') }}</span></x-tables.td>
            <x-tables.td><span class="font-medium tabular-nums text-slate-600">{{ $fechaDmy }}</span></x-tables.td>
            <x-tables.td class="max-w-40 truncate text-slate-600" :title="$solicitante">{{ $solicitante }}</x-tables.td>
            <x-tables.td align="right" class="xl:sticky xl:right-0 xl:z-[1] xl:min-w-[240px] xl:bg-white xl:shadow-[-1px_0_0_0_#e5e7eb] group-hover:bg-slate-50/80">
              <div class="flex flex-nowrap justify-end gap-1">
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
                  data-asesor="{{ $solicitante }}"
                  data-titular="{{ $p->titular ?? '-' }}"
                  data-deuda="{{ number_format((float) ($p->deuda_total ?? 0), 2) }}"
                  data-capital-raw="{{ (float) ($p->saldo_capital ?? 0) }}"
                  data-negociado="{{ number_format($montoMostrar, 2) }}"
                  data-totalconvenio-raw="{{ (float) ($p->monto_convenio ?? 0) }}"
                  data-detalle="{{ $p->nota ?? '' }}"
                  data-nota-sup="{{ $p->nota_preaprobacion ?? '' }}"
                  data-nota-gen="{{ $p->nota ?? '' }}"
                  data-estado="{{ $estadoPromesa }}"
                  data-crono='@json($crono)'
                  data-crono-b64="{{ base64_encode($cronoJson) }}"
                  data-hasbalon="{{ $hasBalon ? 1 : 0 }}"
                  data-cuentas='@json($cuentas)'
                  data-cuentas-b64="{{ base64_encode($cuentasJson) }}"
                >
                  Detalle
                </x-ui.button>

                @if($isSupervisor)
                  <x-ui.button
                    type="button"
                    size="sm"
                    class="js-open-nota"
                    data-modal-open="#modalNotaEstado"
                    data-title="Preaprobar promesa"
                    data-action="{{ route('autorizacion.preaprobar', $p) }}"
                  >
                    Preaprobar
                  </x-ui.button>
                  <x-ui.button
                    type="button"
                    variant="danger"
                    size="sm"
                    class="js-open-rechazo"
                    data-modal-open="#modalRechazo"
                    data-title="Rechazar promesa"
                    data-action="{{ route('autorizacion.rechazar.sup', $p) }}"
                  >
                    Rechazar
                  </x-ui.button>
                @else
                  <x-ui.button
                    type="button"
                    variant="success"
                    size="sm"
                    class="js-open-nota"
                    data-modal-open="#modalNotaEstado"
                    data-title="Aprobar promesa"
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
                    data-title="Rechazar promesa"
                    data-action="{{ route('autorizacion.rechazar.admin', $p) }}"
                  >
                    Rechazar
                  </x-ui.button>
                @endif
              </div>
            </x-tables.td>
          </tr>
        @empty
          <x-tables.empty-row colspan="7">
            <div class="flex flex-col items-center gap-1.5 py-2">
              <span class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
              </span>
              <span class="text-[13px] font-semibold text-kp-ink">No hay promesas {{ $isSupervisor ? 'pendientes' : 'preaprobadas' }}</span>
              <span class="text-[11px] text-kp-muted">La bandeja se actualizará cuando existan nuevas solicitudes.</span>
            </div>
          </x-tables.empty-row>
        @endforelse
      </tbody>
    </x-tables.table>
  </section>

  <section class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3">
      <div class="flex min-w-0 items-center gap-2.5">
        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kp-green-soft text-kp-primary-ink">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v12a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 18V6a2.25 2.25 0 0 1 2.25-2.25ZM8.25 8.25h7.5m-7.5 4h4.5" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-[15px] font-semibold text-kp-ink">Solicitudes CNA</h2>
            <x-ui.badge :variant="$isSupervisor ? 'warning' : 'info'">{{ $statusLabel }}</x-ui.badge>
          </div>
          <p class="mt-0.5 text-[11px] text-kp-muted">{{ $statusDescription }}</p>
        </div>
      </div>
      <span class="inline-flex items-center rounded-md border border-kp-border bg-kp-surface-muted px-2 py-1 text-[10.5px] font-semibold text-slate-600">
        {{ number_format($cnaCount) }} {{ $cnaCount === 1 ? 'solicitud' : 'solicitudes' }}
      </span>
    </header>

    <x-tables.table :flush="true" :contained="true" min-width="min-w-[1120px]">
      <thead>
        <tr>
          <x-tables.th>Cliente</x-tables.th>
          <x-tables.th>Producto</x-tables.th>
          <x-tables.th>Operación(es)</x-tables.th>
          <x-tables.th align="right">Pago informado</x-tables.th>
          <x-tables.th>Fecha</x-tables.th>
          <x-tables.th align="right" class="xl:sticky xl:right-0 xl:z-10 xl:min-w-[240px] xl:bg-kp-surface-muted xl:shadow-[-1px_0_0_0_#e5e7eb]">Acciones</x-tables.th>
        </tr>
      </thead>
      <tbody class="divide-y divide-kp-border bg-white">
        @forelse($cnaRows as $cna)
          @php
            $ops = collect((array) ($cna->operaciones ?? []))
                ->map(fn ($x) => trim((string) $x))
                ->filter()
                ->values();
            $opsArray = $ops->all();
            $opsJson = json_encode($opsArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

            $productos = $ops->map(fn ($op) => $prodByOp[(string) $op] ?? null)
                ->filter()
                ->unique()
                ->values();

            $productoTxt = $productos->isEmpty()
                ? '-'
                : ($productos->count() === 1 ? $productos->first() : $productos->implode(' / '));

            $opTxt = $ops->isEmpty() ? '-' : $ops->implode(', ');
            $cnaSolicitante = $cna->solicitante?->name ?: '—';
            $estadoCna = $cna->workflow_estado ?: ($isSupervisor ? 'pendiente' : 'preaprobada');
            $montoPagado = (float) ($cna->monto_pagado ?? 0);
          @endphp

          <tr class="group transition-colors hover:bg-slate-50/80">
            <x-tables.td class="min-w-44">
              <div class="font-semibold text-kp-ink">{{ $cna->titular ?: '—' }}</div>
              <div class="mt-0.5 text-[10.5px] text-kp-muted">
                <span class="font-mono tabular-nums">DNI {{ $cna->dni ?: '—' }}</span>
                <span class="mx-1 text-slate-300">·</span>
                Carta {{ $cna->nro_carta ?: '—' }}
              </div>
              <div class="mt-0.5 truncate text-[10.5px] text-kp-muted" title="{{ $cnaSolicitante }}">Solicitante: {{ $cnaSolicitante }}</div>
            </x-tables.td>
            <x-tables.td class="max-w-64 truncate text-slate-600" :title="$productoTxt">{{ $productoTxt }}</x-tables.td>
            <x-tables.td class="max-w-64 truncate" :title="$opTxt"><span class="font-mono text-[12px] text-slate-600">{{ $opTxt }}</span></x-tables.td>
            <x-tables.td align="right"><span class="font-semibold tabular-nums text-kp-ink">{{ \App\Support\Money::format($montoPagado, 'PEN') }}</span></x-tables.td>
            <x-tables.td><span class="font-medium tabular-nums text-slate-600">{{ optional($cna->created_at)->format('d/m/Y') ?: '—' }}</span></x-tables.td>
            <x-tables.td align="right" class="xl:sticky xl:right-0 xl:z-[1] xl:min-w-[240px] xl:bg-white xl:shadow-[-1px_0_0_0_#e5e7eb] group-hover:bg-slate-50/80">
              <div class="flex flex-nowrap justify-end gap-1">
                <x-ui.button
                  type="button"
                  variant="secondary"
                  size="sm"
                  class="js-ver-cna"
                  data-modal-open="#modalCnaFicha"
                  data-dni="{{ $cna->dni }}"
                  data-titular="{{ $cna->titular ?? '' }}"
                  data-estado="{{ $estadoCna }}"
                  data-solicitante="{{ $cnaSolicitante }}"
                  data-nrocarta="{{ $cna->nro_carta }}"
                  data-producto="{{ $productoTxt }}"
                  data-operaciones='@json($opsArray)'
                  data-operaciones-b64="{{ base64_encode($opsJson) }}"
                  data-fecha="{{ optional($cna->created_at)->format('Y-m-d') }}"
                  data-fecha-pago="{{ optional($cna->fecha_pago_realizado)->format('Y-m-d') }}"
                  data-monto-pagado="{{ $montoPagado }}"
                  data-observacion="{{ $cna->observacion }}"
                  data-cliente-url="{{ route('clientes.show', $cna->dni) }}"
                  data-nota="{{ $cna->nota }}"
                  data-preaprobado-por="{{ $cna->preAprobadoPor?->name }}"
                  data-preaprobado-at="{{ optional($cna->pre_aprobado_at)->format('Y-m-d H:i') }}"
                  data-aprobado-por="{{ $cna->aprobadoPor?->name }}"
                  data-aprobado-at="{{ optional($cna->aprobado_at)->format('Y-m-d H:i') }}"
                  data-rechazado-por="{{ $cna->rechazadoPor?->name }}"
                  data-rechazado-at="{{ optional($cna->rechazado_at)->format('Y-m-d H:i') }}"
                  data-motivo-rechazo="{{ $cna->motivo_rechazo }}"
                  data-doc-template="#cna-doc-template-{{ $cna->id }}"
                >
                  Detalle
                </x-ui.button>

                @if($isSupervisor)
                  <x-ui.button
                    type="button"
                    size="sm"
                    class="js-open-nota"
                    data-modal-open="#modalNotaEstado"
                    data-title="Preaprobar CNA"
                    data-action="{{ route('cna.preaprobar', $cna) }}"
                  >
                    Preaprobar
                  </x-ui.button>
                  <x-ui.button
                    type="button"
                    variant="danger"
                    size="sm"
                    class="js-open-rechazo"
                    data-modal-open="#modalRechazo"
                    data-title="Rechazar CNA"
                    data-action="{{ route('cna.rechazar.sup', $cna) }}"
                  >
                    Rechazar
                  </x-ui.button>
                @else
                  <x-ui.button
                    type="button"
                    variant="success"
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
                    data-title="Rechazar CNA"
                    data-action="{{ route('cna.rechazar.admin', $cna) }}"
                  >
                    Rechazar
                  </x-ui.button>
                @endif
              </div>

              <template id="cna-doc-template-{{ $cna->id }}">
                <x-cna.document-state :cna="$cna" mode="panel" />
              </template>
            </x-tables.td>
          </tr>
        @empty
          <x-tables.empty-row colspan="6">
            <div class="flex flex-col items-center gap-1.5 py-2">
              <span class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v12a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 18V6a2.25 2.25 0 0 1 2.25-2.25Z" />
                </svg>
              </span>
              <span class="text-[13px] font-semibold text-kp-ink">No hay solicitudes CNA {{ $isSupervisor ? 'pendientes' : 'preaprobadas' }}</span>
              <span class="text-[11px] text-kp-muted">La bandeja se actualizará cuando existan nuevas solicitudes.</span>
            </div>
          </x-tables.empty-row>
        @endforelse
      </tbody>
    </x-tables.table>

    <x-tables.pagination :paginator="$cnaRows" />
  </section>

  <x-autorizacion.decision-modal
    id="modalRechazo"
    form-id="formRechazo"
    title-id="modalRechazoTitulo"
    textarea-id="motivoTxt"
    title="Rechazar solicitud"
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
    :title="$isSupervisor ? 'Preaprobar solicitud' : 'Aprobar solicitud'"
    placeholder="Agrega una nota opcional para esta decisión."
    :confirm-text="$isSupervisor ? 'Preaprobar' : 'Aprobar'"
    :variant="$isSupervisor ? 'primary' : 'success'"
  />

  <x-ui.modal id="modalFicha" title="Revisión de promesa" max-width="5xl">
    <div class="max-h-[80vh] overflow-y-auto">
      <div class="border-b border-kp-border bg-slate-50/70 px-5 py-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <x-ui.badge variant="neutral">Promesa de pago</x-ui.badge>
              <x-ui.badge id="t_estado" :variant="$isSupervisor ? 'warning' : 'info'">{{ $statusLabel }}</x-ui.badge>
            </div>
            <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Cliente</p>
            <h3 id="t_titular" class="mt-0.5 truncate text-lg font-semibold text-kp-ink">—</h3>
            <p class="mt-1 text-[12px] text-slate-500">
              DNI <span id="f_dni" class="font-medium text-slate-700">—</span>
              <span class="mx-1.5 text-slate-300">·</span>
              Operación(es) <span id="f_op" class="font-medium text-slate-700">—</span>
            </p>
          </div>

          <div class="grid grid-cols-2 gap-4 sm:w-[320px]">
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Monto negociado</p>
              <p id="t_neg" class="mt-1 text-lg font-semibold tabular-nums text-kp-ink">—</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Deuda total</p>
              <p id="t_deuda" class="mt-1 text-lg font-semibold tabular-nums text-kp-ink">—</p>
            </div>
          </div>
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-3">
          <x-autorizacion.detail-field label="Fecha de la promesa" id="t_fecha" />
          <x-autorizacion.detail-field label="Tipo" id="t_tipo" />
          <x-autorizacion.detail-field label="Solicitante" id="t_asesor" />
        </div>
      </div>

      <div class="space-y-5 p-5">
        <section>
          <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Notas de la solicitud</h4>
          <div class="mt-2 space-y-2">
            <div id="nota_general_wrap" class="hidden rounded-lg border border-kp-border bg-white px-3.5 py-3">
              <x-ui.badge variant="neutral">General</x-ui.badge>
              <p id="nota_general_txt" class="mt-2 whitespace-pre-line text-[13px] leading-6 text-kp-ink"></p>
            </div>
            <div id="nota_sup_wrap" class="hidden rounded-lg border border-kp-border bg-white px-3.5 py-3">
              <x-ui.badge variant="info">Supervisor</x-ui.badge>
              <p id="nota_sup_txt" class="mt-2 whitespace-pre-line text-[13px] leading-6 text-kp-ink"></p>
            </div>
            <div id="nota_empty" class="rounded-lg border border-dashed border-kp-border bg-slate-50 px-3.5 py-3 text-[12px] text-kp-muted">
              Sin notas registradas para esta solicitud.
            </div>
          </div>
        </section>

        <section>
          <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Cuentas incluidas</h4>
          <div id="acc_cuentas" class="mt-2 space-y-2"></div>
        </section>

        <section id="crono_wrap" class="hidden">
          <h4 id="crono_titulo" class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Cronograma de cuotas</h4>
          <div class="mt-2">
            <x-tables.table :flush="true" min-width="min-w-[620px]">
              <thead>
                <tr>
                  <x-tables.th>#</x-tables.th>
                  <x-tables.th>Fecha</x-tables.th>
                  <x-tables.th align="right">Importe</x-tables.th>
                  <x-tables.th>Tipo</x-tables.th>
                </tr>
              </thead>
              <tbody id="crono_body"></tbody>
              <tfoot>
                <tr>
                  <x-tables.td colspan="2" align="right" class="font-semibold text-slate-500">TOTAL CONVENIO</x-tables.td>
                  <x-tables.td align="right" id="crono_total" class="font-semibold tabular-nums text-kp-ink">S/ 0.00</x-tables.td>
                  <x-tables.td />
                </tr>
              </tfoot>
            </x-tables.table>
          </div>
        </section>
      </div>
    </div>

    <div class="flex justify-end border-t border-kp-border px-5 py-4">
      <x-ui.button type="button" variant="secondary" data-modal-close>Cerrar</x-ui.button>
    </div>
  </x-ui.modal>

  <x-ui.modal id="modalCnaFicha" title="Revisión de solicitud CNA" max-width="5xl">
    <div class="max-h-[80vh] overflow-y-auto">
      <div class="border-b border-kp-border bg-slate-50/70 px-5 py-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <x-ui.badge variant="neutral">Solicitud CNA</x-ui.badge>
              <x-ui.badge id="cna_estado" :variant="$isSupervisor ? 'warning' : 'info'">{{ $statusLabel }}</x-ui.badge>
            </div>
            <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Cliente</p>
            <h3 id="cna_titular" class="mt-0.5 truncate text-lg font-semibold text-kp-ink">—</h3>
            <p class="mt-1 text-[12px] text-slate-500">
              DNI <span id="cna_dni" class="font-medium text-slate-700">—</span>
              <span class="mx-1.5 text-slate-300">·</span>
              Carta <span id="cna_carta" class="font-medium text-slate-700">—</span>
            </p>
            <a id="cna_ver_ficha" href="#" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-kp-secondary-dark hover:underline">
              Ver cuenta y cosecha en la ficha del cliente
              <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
              </svg>
            </a>
          </div>

          <div class="rounded-lg border border-kp-border bg-white px-4 py-3 sm:w-[220px]">
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Pago informado</p>
            <p class="mt-1 text-xl font-semibold tabular-nums text-kp-ink"><span id="cna_monto_pagado">S/ 0.00</span></p>
          </div>
        </div>
      </div>

      <div class="space-y-5 p-5">
        <div class="grid gap-2 sm:grid-cols-3">
          <x-autorizacion.detail-field label="Fecha de solicitud" id="cna_fecha" />
          <x-autorizacion.detail-field label="Fecha de pago" id="cna_fecha_pago" />
          <x-autorizacion.detail-field label="Solicitante" id="cna_solicitante" />
          <x-autorizacion.detail-field label="Producto" id="cna_producto" />
          <x-autorizacion.detail-field label="Entidad / cosecha (según pagos)" id="cna_entidad_cosecha" />
          <x-autorizacion.detail-field label="Operación(es)" id="cna_ops" />
        </div>

        <section>
          <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Flujo de autorización</h4>
          <ol id="cna_flujo" class="mt-2 space-y-0"></ol>
        </section>

        <section>
          <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Documento de la CNA</h4>
          <div id="cna_document_panel" class="mt-2"></div>
          <p class="mt-1.5 text-[10.5px] text-kp-muted">La plantilla se determina por la cosecha/entidad de las operaciones al generar el documento.</p>
        </section>

        <section>
          <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Observación</h4>
          <div id="cna_obs" class="mt-2 whitespace-pre-line rounded-lg border border-kp-border bg-white px-3.5 py-3 text-[13px] leading-6 text-kp-ink">—</div>
        </section>

        <section id="cna_nota_wrap" class="hidden">
          <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Notas de autorización</h4>
          <div id="cna_nota" class="mt-2 whitespace-pre-line rounded-lg border border-kp-border bg-white px-3.5 py-3 text-[13px] leading-6 text-kp-ink">—</div>
        </section>

        <section class="flex flex-col gap-2 rounded-lg border border-kp-primary-border bg-kp-success-soft px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[12px] font-semibold text-kp-success-ink">Total histórico de pagos del DNI</p>
            <p class="mt-0.5 text-[10.5px] text-slate-500">Incluye pagos de todas las operaciones del DNI; no es el importe de esta CNA.</p>
          </div>
          <p id="cna_total_pagos" class="text-xl font-semibold tabular-nums text-kp-ink">S/ 0.00</p>
        </section>

        <section>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Pagos realizados</h4>
            <span id="cna_pagos_count" class="inline-flex items-center rounded-md border border-kp-border bg-kp-surface-muted px-2 py-1 text-[10.5px] font-semibold text-slate-600">—</span>
          </div>
          <p id="cna_pagos_context" class="mt-1 text-[11px] text-kp-muted">Histórico de pagos asociado al DNI. Las operaciones marcadas con CNA pertenecen a esta solicitud.</p>
          <div class="mt-2">
            <x-tables.table :flush="true" min-width="min-w-[900px]">
              <thead>
                <tr>
                  <x-tables.th>Operación</x-tables.th>
                  <x-tables.th>Fecha</x-tables.th>
                  <x-tables.th align="right">Monto</x-tables.th>
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
    </div>

    <div class="flex justify-end border-t border-kp-border px-5 py-4">
      <x-ui.button type="button" variant="secondary" data-modal-close>Cerrar</x-ui.button>
    </div>
  </x-ui.modal>
</x-layout.page-shell>
@endsection
