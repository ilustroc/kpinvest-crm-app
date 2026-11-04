{{-- resources/views/autorizacion/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Autorizaciones')
@section('crumb', 'Autorizaciones')
@section('tailwind_only', true)

@section('content')
@php
    $promesaCount = $rows->count();
    $cnaCount = $cnaRows->total();
    $asignacionCount = isset($asignacionRows) && $asignacionRows ? $asignacionRows->total() : 0;
    $roleLabel = $roleLabel ?? ($isSupervisor ? 'Supervisor' : 'Administrador');
    $statusLabel = $isSupervisor ? 'Pendientes' : 'Preaprobadas';
    $statusDescription = $isSupervisor
        ? 'Solicitudes pendientes de preaprobación.'
        : 'Solicitudes preaprobadas listas para aprobación.';
    $asigFilters = $asignacionFilters ?? [];
    $asigEstadoActual = $asigFilters['estado'] ?? 'pendiente';
@endphp

<x-layout.page-shell
  data-module="autorizacion-index"
  data-pagos-url-template="{{ route('autorizacion.pagos', '__DNI__') }}"
>
  <x-layout.page-header
    title="Autorizaciones"
    subtitle="Revisa, aprueba o rechaza promesas de pago, solicitudes CNA y asignaciones de clientes."
  >
    <x-slot:actions>
      <x-ui.badge :variant="$isSupervisor ? 'warning' : 'info'">{{ $statusLabel }}</x-ui.badge>
      <span class="inline-flex items-center rounded-md border border-kp-border bg-white px-2 py-1 text-[10.5px] font-semibold text-slate-600">
        Perfil: {{ $roleLabel }}
      </span>
    </x-slot:actions>
  </x-layout.page-header>

  @if(session('ok') || session('status'))
    <x-feedback.alert variant="success">{{ session('ok') ?: session('status') }}</x-feedback.alert>
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
            $cuentas = $p->cuentas_json ?? $p->cuentas_cliente_json ?? [];
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
              <div class="mt-0.5 font-mono text-[11px] tabular-nums text-kp-muted">Doc. {{ $p->numdoc ?: $p->dni ?: '—' }}</div>
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
                  data-numdoc="{{ $p->numdoc ?? $p->dni }}"
                  data-dni="{{ $p->numdoc ?? $p->dni }}"
                  data-operacion="{{ $p->operacion ?? '' }}"
                  data-fecha="{{ $fechaDmy }}"
                  data-asesor="{{ $solicitante }}"
                  data-titular="{{ $p->titular ?? '-' }}"
                  data-deuda="{{ number_format((float) ($p->deuda_total ?? 0), 2) }}"
                  data-capital-raw="{{ (float) ($p->saldo_capital ?? 0) }}"
                  data-capital="{{ number_format((float) ($p->saldo_capital ?? 0), 2) }}"
                  data-negociado="{{ number_format($montoMostrar, 2) }}"
                  data-totalconvenio-raw="{{ (float) ($p->monto_convenio ?? 0) }}"
                  data-detalle="{{ $p->nota ?? '' }}"
                  data-nota-sup="{{ $p->nota_preaprobacion ?? '' }}"
                  data-nota-gen="{{ $p->nota ?? '' }}"
                  data-estado="{{ $estadoPromesa }}"
                  data-crono="{{ json_encode($crono) }}"
                  data-hasbalon="{{ $hasBalon ? 1 : 0 }}"
                  data-cuentas="{{ json_encode($cuentas) }}"
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

            $cnaDoc = (string) ($cna->numdoc ?? $cna->dni ?? '');
            $productos = $ops->map(fn ($op) => $prodByOp["{$cnaDoc}_{$op}"] ?? $prodByOp[(string) $op] ?? null)
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
                <span class="font-mono tabular-nums">Doc. {{ $cnaDoc ?: '—' }}</span>
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
                  data-numdoc="{{ $cnaDoc }}"
                  data-dni="{{ $cnaDoc }}"
                  data-titular="{{ $cna->titular ?? '' }}"
                  data-estado="{{ $estadoCna }}"
                  data-solicitante="{{ $cnaSolicitante }}"
                  data-nrocarta="{{ $cna->nro_carta }}"
                  data-producto="{{ $productoTxt }}"
                  data-operaciones="{{ $opsJson }}"
                  data-fecha="{{ optional($cna->created_at)->format('Y-m-d') }}"
                  data-fecha-pago="{{ optional($cna->fecha_pago_realizado)->format('Y-m-d') }}"
                  data-monto-pagado="{{ $montoPagado }}"
                  data-observacion="{{ $cna->observacion }}"
                  data-cliente-url="{{ route('clientes.show', $cnaDoc) }}"
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

  <section id="panel-asignaciones" class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3">
      <div class="flex min-w-0 items-center gap-2.5">
        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-[15px] font-semibold text-kp-ink">ASIGNAR CLIENTE</h2>
            <x-ui.badge :variant="$asigEstadoActual === 'pendiente' ? 'warning' : ($asigEstadoActual === 'aprobada' ? 'success' : ($asigEstadoActual === 'rechazada' ? 'danger' : 'info'))">
              {{ match($asigEstadoActual) { 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas', 'todos' => 'Historial completo', default => 'Pendientes' } }}
            </x-ui.badge>
          </div>
          <p class="mt-0.5 text-[11px] text-kp-muted">Solicitudes de asignación de clientes e historial lineal por operación.</p>
        </div>
      </div>
      <span class="inline-flex items-center rounded-md border border-kp-border bg-kp-surface-muted px-2 py-1 text-[10.5px] font-semibold text-slate-600">
        {{ number_format($asignacionCount) }} {{ $asignacionCount === 1 ? 'registro' : 'registros' }}
      </span>
    </header>

    <form method="GET" action="{{ route('autorizacion') }}#panel-asignaciones" class="border-b border-kp-border bg-slate-50/60 px-4 py-3">
      <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-6">
        <div>
          <label for="asig_estado" class="block text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Estado</label>
          <select id="asig_estado" name="asig_estado" class="mt-1 h-8 w-full rounded-md border border-kp-border bg-white px-2.5 text-[12px] text-kp-ink kp-focus">
            <option value="pendiente" @selected($asigEstadoActual === 'pendiente')>Pendientes</option>
            <option value="aprobada" @selected($asigEstadoActual === 'aprobada')>Aprobadas</option>
            <option value="rechazada" @selected($asigEstadoActual === 'rechazada')>Rechazadas</option>
            <option value="todos" @selected($asigEstadoActual === 'todos')>Todos los estados</option>
          </select>
        </div>

        <div>
          <label for="asig_q" class="block text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Documento / Operación</label>
          <input id="asig_q" type="text" name="asig_q" value="{{ $asigFilters['q'] ?? ($asigFilters['numdoc'] ?? '') }}" placeholder="Buscar numdoc u operación..." class="mt-1 h-8 w-full rounded-md border border-kp-border bg-white px-2.5 text-[12px] text-kp-ink kp-focus">
        </div>

        <div>
          <label for="asig_solicitante" class="block text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Solicitante</label>
          <select id="asig_solicitante" name="asig_solicitante" class="mt-1 h-8 w-full rounded-md border border-kp-border bg-white px-2.5 text-[12px] text-kp-ink kp-focus">
            <option value="">Todos</option>
            @foreach($asignacionUsers ?? [] as $u)
              <option value="{{ $u->id }}" @selected((int) ($asigFilters['solicitante_user_id'] ?? 0) === (int) $u->id)>{{ $u->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label for="asig_asesor" class="block text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Asesor destino</label>
          <select id="asig_asesor" name="asig_asesor" class="mt-1 h-8 w-full rounded-md border border-kp-border bg-white px-2.5 text-[12px] text-kp-ink kp-focus">
            <option value="">Todos</option>
            @foreach($asignacionUsers ?? [] as $u)
              <option value="{{ $u->id }}" @selected((int) ($asigFilters['asesor_destino_user_id'] ?? 0) === (int) $u->id)>{{ $u->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label for="asig_desde" class="block text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Desde</label>
          <input id="asig_desde" type="date" name="asig_desde" value="{{ $asigFilters['desde'] ?? '' }}" class="mt-1 h-8 w-full rounded-md border border-kp-border bg-white px-2.5 text-[12px] text-kp-ink kp-focus">
        </div>

        <div class="flex items-end gap-1.5">
          <div class="min-w-0 flex-1">
            <label for="asig_hasta" class="block text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Hasta</label>
            <input id="asig_hasta" type="date" name="asig_hasta" value="{{ $asigFilters['hasta'] ?? '' }}" class="mt-1 h-8 w-full rounded-md border border-kp-border bg-white px-2.5 text-[12px] text-kp-ink kp-focus">
          </div>
          <x-ui.button type="submit" size="sm" class="h-8">Filtrar</x-ui.button>
          <a href="{{ route('autorizacion') }}#panel-asignaciones" class="inline-flex h-8 items-center rounded-md border border-kp-border bg-white px-2.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50">Limpiar</a>
        </div>
      </div>
    </form>

    <x-tables.table :flush="true" :contained="true" min-width="min-w-[1160px]">
      <thead>
        <tr>
          <x-tables.th>Cliente</x-tables.th>
          <x-tables.th>Operación / Cuenta</x-tables.th>
          <x-tables.th>Entidad / Producto</x-tables.th>
          <x-tables.th>Solicitante</x-tables.th>
          <x-tables.th>Asesor destino</x-tables.th>
          <x-tables.th>Fecha solicitud</x-tables.th>
          <x-tables.th>Motivo</x-tables.th>
          <x-tables.th align="center">Estado</x-tables.th>
          <x-tables.th align="right" class="xl:sticky xl:right-0 xl:z-10 xl:min-w-[240px] xl:bg-kp-surface-muted xl:shadow-[-1px_0_0_0_#e5e7eb]">Acciones</x-tables.th>
        </tr>
      </thead>
      <tbody class="divide-y divide-kp-border bg-white">
        @forelse($asignacionRows ?? [] as $asig)
          @php
            $cuenta = $asig->clienteCuenta;
            $titularAsig = $cuenta?->nombre ?: 'Cliente registrado';
            $entidadAsig = $cuenta?->entidad ?: '—';
            $productoAsig = $cuenta?->producto ?: '—';
            $cosechaAsig = $cuenta?->cosecha ?: '—';
            $cuentaNro = $cuenta?->cuenta ?: $asig->operacion;
            $deudaCapAsig = (float) ($cuenta?->deuda_capital ?? 0);
            $deudaTotAsig = (float) ($cuenta?->deuda_total ?? 0);
            $solicitanteAsig = $asig->solicitante_nombre ?: 'Sistema / Histórico';
            $asesorDestinoAsig = $asig->asesor_nombre ?: 'Sin asignar';
            $asesorVigenteActual = $asig->asesor_vigente_actual ?? 'Sin asignar';
            $fechaSolTxt = $asig->fecha_solicitud ? $asig->fecha_solicitud->format('d/m/Y H:i') : 'Histórico';
            $estadoAsig = strtolower((string) ($asig->estado ?? 'pendiente'));
            $estadoBadgeVariant = match ($estadoAsig) {
                'aprobada' => 'success',
                'rechazada' => 'danger',
                default => 'warning',
            };
            $estadoBadgeLabel = match ($estadoAsig) {
                'aprobada' => 'Aprobada',
                'rechazada' => 'Rechazada',
                default => 'Pendiente',
            };
            $historialJson = json_encode($asig->historial_operacion_json ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
          @endphp
          <tr class="group transition-colors hover:bg-slate-50/80">
            <x-tables.td class="min-w-44">
              <div class="font-semibold text-kp-ink">{{ $titularAsig }}</div>
              <div class="mt-0.5 font-mono text-[11px] tabular-nums text-kp-muted">
                <a href="{{ route('clientes.show', $asig->numdoc) }}" class="hover:underline">Doc. {{ $asig->numdoc }}</a>
              </div>
            </x-tables.td>
            <x-tables.td>
              <div class="font-mono text-[12px] font-semibold text-kp-ink">{{ $asig->operacion }}</div>
              <div class="mt-0.5 text-[10.5px] text-kp-muted">Cuenta: {{ $cuentaNro }}</div>
            </x-tables.td>
            <x-tables.td>
              <div class="max-w-44 truncate font-medium text-kp-ink" title="{{ $entidadAsig }}">{{ $entidadAsig }}</div>
              <div class="mt-0.5 max-w-44 truncate text-[10.5px] text-kp-muted" title="{{ $productoAsig }}">{{ $productoAsig }}</div>
            </x-tables.td>
            <x-tables.td class="max-w-36 truncate text-slate-600" :title="$solicitanteAsig">{{ $solicitanteAsig }}</x-tables.td>
            <x-tables.td>
              <div class="font-semibold text-kp-ink">{{ $asesorDestinoAsig }}</div>
              <div class="mt-0.5 text-[10px] text-kp-muted">Vigente: {{ $asesorVigenteActual }}</div>
            </x-tables.td>
            <x-tables.td>
              <span class="font-medium tabular-nums text-slate-600">{{ $fechaSolTxt }}</span>
            </x-tables.td>
            <x-tables.td>
              <div class="max-w-48 truncate text-[11.5px] text-slate-700" title="{{ $asig->motivo }}">{{ $asig->motivo ?: 'Asignación histórica' }}</div>
              <div class="mt-0.5 text-[10px] text-kp-muted">{{ ($asig->origen ?? '') === 'manual' ? 'Solicitud manual' : 'Carga masiva' }}</div>
            </x-tables.td>
            <x-tables.td align="center">
              <x-ui.badge :variant="$estadoBadgeVariant">{{ $estadoBadgeLabel }}</x-ui.badge>
              @if($asig->aprobador_nombre)
                <div class="mt-0.5 text-[9.5px] text-kp-muted" title="Resuelto por {{ $asig->aprobador_nombre }}">
                  por {{ $asig->aprobador_nombre }}
                </div>
              @endif
            </x-tables.td>
            <x-tables.td align="right" class="xl:sticky xl:right-0 xl:z-[1] xl:min-w-[240px] xl:bg-white xl:shadow-[-1px_0_0_0_#e5e7eb] group-hover:bg-slate-50/80">
              <div class="flex flex-nowrap justify-end gap-1">
                <x-ui.button
                  type="button"
                  variant="secondary"
                  size="sm"
                  class="js-ver-asignacion"
                  data-modal-open="#modalAsignacionFicha"
                  data-id="{{ $asig->id }}"
                  data-numdoc="{{ $asig->numdoc }}"
                  data-titular="{{ $titularAsig }}"
                  data-operacion="{{ $asig->operacion }}"
                  data-cuenta="{{ $cuentaNro }}"
                  data-entidad="{{ $entidadAsig }}"
                  data-producto="{{ $productoAsig }}"
                  data-cosecha="{{ $cosechaAsig }}"
                  data-capital="{{ $deudaCapAsig }}"
                  data-deuda="{{ $deudaTotAsig }}"
                  data-solicitante="{{ $solicitanteAsig }}"
                  data-asesor-destino="{{ $asesorDestinoAsig }}"
                  data-asesor-vigente="{{ $asesorVigenteActual }}"
                  data-fecha-solicitud="{{ $fechaSolTxt }}"
                  data-motivo="{{ $asig->motivo ?: 'Asignación histórica' }}"
                  data-estado="{{ $estadoBadgeLabel }}"
                  data-origen="{{ ($asig->origen ?? '') === 'manual' ? 'Solicitud manual' : 'Carga masiva' }}"
                  data-resuelto-por="{{ $asig->aprobador_nombre ?? '' }}"
                  data-fecha-resolucion="{{ $asig->fecha_aprobacion ? $asig->fecha_aprobacion->format('d/m/Y H:i') : '' }}"
                  data-observacion-resolucion="{{ $asig->observacion_resolucion ?? '' }}"
                  data-cliente-url="{{ route('clientes.show', $asig->numdoc) }}"
                  data-historial="{{ $historialJson }}"
                >
                  Detalle
                </x-ui.button>

                @if($estadoAsig === 'pendiente' && ($canReviewAsignaciones ?? true))
                  <x-ui.button
                    type="button"
                    variant="success"
                    size="sm"
                    class="js-open-nota"
                    data-modal-open="#modalNotaEstado"
                    data-title="Aprobar asignación de cliente"
                    data-confirm-text="Aprobar"
                    data-action="{{ route('autorizacion.asignaciones.aprobar', $asig) }}"
                  >
                    Aprobar
                  </x-ui.button>
                  <x-ui.button
                    type="button"
                    variant="danger"
                    size="sm"
                    class="js-open-rechazo"
                    data-modal-open="#modalRechazo"
                    data-title="Rechazar asignación de cliente"
                    data-action="{{ route('autorizacion.asignaciones.rechazar', $asig) }}"
                  >
                    Rechazar
                  </x-ui.button>
                @endif
              </div>
            </x-tables.td>
          </tr>
        @empty
          <x-tables.empty-row colspan="9">
            <div class="flex flex-col items-center gap-1.5 py-2">
              <span class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                </svg>
              </span>
              <span class="text-[13px] font-semibold text-kp-ink">No se encontraron registros de asignación para los filtros seleccionados</span>
              <span class="text-[11px] text-kp-muted">Las solicitudes manuales y cargas masivas aparecerán en esta bandeja.</span>
            </div>
          </x-tables.empty-row>
        @endforelse
      </tbody>
    </x-tables.table>

    @if(isset($asignacionRows) && $asignacionRows)
      <x-tables.pagination :paginator="$asignacionRows" />
    @endif
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

          <div class="grid grid-cols-3 gap-3 sm:gap-5">
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Monto negociado</p>
              <p id="t_neg" class="mt-1 text-base font-semibold tabular-nums text-kp-ink sm:text-lg">—</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Deuda capital</p>
              <p id="t_capital" class="mt-1 text-base font-semibold tabular-nums text-kp-ink sm:text-lg">—</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Deuda total</p>
              <p id="t_deuda" class="mt-1 text-base font-semibold tabular-nums text-kp-ink sm:text-lg">—</p>
            </div>
          </div>
        </div>

        <div class="mt-3.5 grid grid-cols-2 gap-3 border-t border-kp-border/60 pt-3 sm:grid-cols-3">
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Fecha de la promesa</p>
            <p id="t_fecha" class="mt-0.5 text-[13px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Tipo</p>
            <p id="t_tipo" class="mt-0.5 text-[13px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Solicitante</p>
            <p id="t_asesor" class="mt-0.5 text-[13px] font-semibold text-kp-ink">—</p>
          </div>
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

          <div class="sm:w-[220px] sm:text-right">
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Pago informado</p>
            <p class="mt-1 text-xl font-semibold tabular-nums text-kp-ink"><span id="cna_monto_pagado">S/ 0.00</span></p>
          </div>
        </div>

        <div class="mt-3.5 grid grid-cols-2 gap-3 border-t border-kp-border/60 pt-3 sm:grid-cols-3">
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Fecha de solicitud</p>
            <p id="cna_fecha" class="mt-0.5 text-[13px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Fecha de pago</p>
            <p id="cna_fecha_pago" class="mt-0.5 text-[13px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Solicitante</p>
            <p id="cna_solicitante" class="mt-0.5 text-[13px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Producto</p>
            <p id="cna_producto" class="mt-0.5 text-[13px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Entidad / cosecha (según pagos)</p>
            <p id="cna_entidad_cosecha" class="mt-0.5 text-[13px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Operación(es)</p>
            <p id="cna_ops" class="mt-0.5 text-[13px] font-semibold text-kp-ink">—</p>
          </div>
        </div>
      </div>

      <div class="space-y-5 p-5">
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
          <p id="cna_pagos_context" class="mt-1 text-[11px] text-kp-muted">Histórico de pagos asociado al DNI.</p>
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

  <x-ui.modal id="modalAsignacionFicha" title="Detalle de asignación y trazabilidad" max-width="4xl">
    <div class="max-h-[80vh] overflow-y-auto">
      <div class="border-b border-kp-border bg-slate-50/70 px-5 py-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <x-ui.badge variant="neutral">Asignación de cliente</x-ui.badge>
              <x-ui.badge id="asig_det_estado" variant="warning">Pendiente</x-ui.badge>
            </div>
            <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Cliente</p>
            <h3 id="asig_det_titular" class="mt-0.5 truncate text-lg font-semibold text-kp-ink">—</h3>
            <p class="mt-1 text-[12px] text-slate-500">
              Documento <span id="asig_det_numdoc" class="font-mono font-medium text-slate-700">—</span>
              <span class="mx-1.5 text-slate-300">·</span>
              Operación <span id="asig_det_operacion" class="font-mono font-medium text-slate-700">—</span>
            </p>
            <a id="asig_det_ficha_link" href="#" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-kp-secondary-dark hover:underline">
              Abrir Ficha 360° del cliente
              <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
              </svg>
            </a>
          </div>

          <div class="grid grid-cols-2 gap-4 sm:text-right">
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Deuda capital</p>
              <p id="asig_det_capital" class="mt-1 text-base font-semibold tabular-nums text-kp-ink">S/ 0.00</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Deuda total</p>
              <p id="asig_det_deuda" class="mt-1 text-base font-semibold tabular-nums text-kp-ink">S/ 0.00</p>
            </div>
          </div>
        </div>

        <div class="mt-3.5 grid grid-cols-2 gap-3 border-t border-kp-border/60 pt-3 sm:grid-cols-4">
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Solicitante</p>
            <p id="asig_det_solicitante" class="mt-0.5 text-[12.5px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Asesor destino</p>
            <p id="asig_det_asesor_destino" class="mt-0.5 text-[12.5px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Asesor vigente actual</p>
            <p id="asig_det_asesor_vigente" class="mt-0.5 text-[12.5px] font-semibold text-kp-primary-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Fecha solicitud</p>
            <p id="asig_det_fecha" class="mt-0.5 text-[12.5px] font-semibold tabular-nums text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Entidad / Producto</p>
            <p id="asig_det_entidad_prod" class="mt-0.5 text-[12.5px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Cosecha / Cuenta</p>
            <p id="asig_det_cosecha_cuenta" class="mt-0.5 text-[12.5px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Origen</p>
            <p id="asig_det_origen" class="mt-0.5 text-[12.5px] font-semibold text-kp-ink">—</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Resolución</p>
            <p id="asig_det_resolucion" class="mt-0.5 text-[12.5px] font-semibold text-kp-ink">—</p>
          </div>
        </div>
      </div>

      <div class="space-y-5 p-5">
        <section>
          <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Motivo de la solicitud</h4>
          <div id="asig_det_motivo" class="mt-2 whitespace-pre-line rounded-lg border border-kp-border bg-white px-3.5 py-3 text-[13px] leading-6 text-kp-ink">—</div>
        </section>

        <section id="asig_det_obs_wrap" class="hidden">
          <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Observación de resolución</h4>
          <div id="asig_det_obs" class="mt-2 whitespace-pre-line rounded-lg border border-kp-border bg-white px-3.5 py-3 text-[13px] leading-6 text-kp-ink">—</div>
        </section>

        <section>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-[12px] font-semibold uppercase tracking-[0.05em] text-slate-500">Historial lineal de la operación</h4>
            <span id="asig_det_hist_count" class="inline-flex items-center rounded-md border border-kp-border bg-kp-surface-muted px-2 py-0.5 text-[10.5px] font-semibold text-slate-600">0 registros</span>
          </div>
          <div class="mt-2">
            <x-tables.table :flush="true" min-width="min-w-[680px]">
              <thead>
                <tr>
                  <x-tables.th>Fecha solicitud</x-tables.th>
                  <x-tables.th>Solicitante</x-tables.th>
                  <x-tables.th>Asesor destino</x-tables.th>
                  <x-tables.th>Motivo / Origen</x-tables.th>
                  <x-tables.th>Estado</x-tables.th>
                  <x-tables.th>Resuelto por</x-tables.th>
                </tr>
              </thead>
              <tbody id="asig_det_hist_tbody"></tbody>
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
