{{-- resources/views/clientes/show.blade.php --}}
@extends('layouts.app')
@section('title', 'Cliente '.$dni)
@section('crumb', 'Clientes / Ficha de cliente')
@section('tailwind_only', true)

@section('content')
    @php
        $totCapital = (float) $cuentas->sum(fn($x) => (float) ($x->deuda_capital ?? $x->saldo_capital ?? 0));
        $totDeuda = (float) $cuentas->sum(fn($x) => (float) ($x->deuda_total ?? 0));
        $totPagos = (float) $pagos->sum(fn($p) => (float) ($p->monto_pagado ?? $p->monto ?? 0));
        $canDeletePagos = \Illuminate\Support\Facades\Gate::allows('delete-client-payments');
    @endphp

    <x-layout.page-shell data-module="clientes-show">
        @if(session('ok'))
            <x-feedback.alert variant="success">{{ session('ok') }}</x-feedback.alert>
        @endif

        @if($errors->any())
            <x-feedback.alert variant="danger">{{ $errors->first() }}</x-feedback.alert>
        @endif

        <x-clientes.header
            :dni="$dni"
            :titular="$titular"
            :cuentas="$cuentas"
            :pagos="$pagos"
            :promesas="$promesas"
            :tot-capital="$totCapital"
            :tot-deuda="$totDeuda"
            :tot-pagos="$totPagos"
        />

        <x-clientes.action-panel title="Cuentas" subtitle="Unidad operativa principal · {{ count($cuentas ?? []) }} registro(s)">
            <x-slot:actions>
                <x-ui.button id="btnPropuesta" type="button" data-modal-open="#modalPropuesta" disabled>
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Generar propuesta
                    <span id="selCount" class="rounded bg-white/20 px-1.5 py-0.5 text-[10px]">0</span>
                </x-ui.button>
            </x-slot:actions>

            <x-tables.table id="tblCuentas">
                <thead>
                    <tr>
                        <x-tables.th align="center">
                            <input type="checkbox" id="chkAll" class="size-4 rounded border-kp-border text-kp-green kp-focus" aria-label="Seleccionar todas las cuentas">
                        </x-tables.th>
                        <x-tables.th>Operación</x-tables.th>
                        <x-tables.th>Asesor</x-tables.th>
                        <x-tables.th>Entidad</x-tables.th>
                        <x-tables.th>Producto</x-tables.th>
                        <x-tables.th>Cosecha</x-tables.th>
                        <x-tables.th align="right">Capital</x-tables.th>
                        <x-tables.th align="right">Deuda total</x-tables.th>
                        <x-tables.th align="center">CCD</x-tables.th>
                        <x-tables.th align="center">CNA</x-tables.th>
                        <x-tables.th>Pagos</x-tables.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cuentas as $c)
                        @php
                            $cnt = (int) ($c->pagos_count ?? 0);
                            $sum = (float) ($c->pagos_sum ?? 0);
                            $hasList = isset($c->pagos_list) && (
                                ($c->pagos_list instanceof \Illuminate\Support\Collection && $c->pagos_list->count()) ||
                                (is_array($c->pagos_list) && count($c->pagos_list))
                            );
                            $cnas = collect($cnasByCuenta[$c->cuenta] ?? []);
                            $last = $cnas->sortByDesc(fn($x) => $x->created_at)->first();
                            $estado = strtolower((string) ($last->workflow_estado ?? ''));

                            $cosechaSource = (string) ($c->cosecha ?? '');
                            $ccdKey = $ccdCosechaKeys[$cosechaSource] ?? null;
                            $accountCcdDocs = $ccdKey
                                ? collect($ccdByCosecha->get($ccdKey, []))
                                : collect();
                        @endphp

                        <tr data-cuenta="{{ $c->cuenta }}"
                            data-oper="{{ $c->operacion }}"
                            data-cosecha="{{ $c->cosecha }}"
                            data-entidad="{{ $c->entidad }}">
                            <x-tables.td align="center">
                                <input type="checkbox"
                                       class="chkOp size-4 rounded border-kp-border text-kp-green kp-focus"
                                       value="{{ $c->operacion }}"
                                       aria-label="Seleccionar operación {{ $c->operacion }}"
                                       {{ empty($c->operacion) ? 'disabled' : '' }}>
                            </x-tables.td>
                            <x-tables.td>
                                <div class="font-mono text-xs font-bold text-kp-ink">{{ $c->operacion ?? '-' }}</div>
                                <div class="mt-0.5 text-[10px] text-kp-muted">Cuenta {{ $c->cuenta ?? '-' }}</div>
                            </x-tables.td>
                            <x-tables.td>
                                @if($c->asesor)
                                    <span class="font-medium">{{ $c->asesor }}</span>
                                @else
                                    <x-ui.badge variant="warning">Sin asignar</x-ui.badge>
                                @endif
                            </x-tables.td>
                            <x-tables.td><span class="font-medium">{{ $c->entidad ?? '-' }}</span></x-tables.td>
                            <x-tables.td>{{ $c->producto ?? '-' }}</x-tables.td>
                            <x-tables.td><x-ui.badge>{{ $c->cosecha ?? '-' }}</x-ui.badge></x-tables.td>
                            <x-tables.td align="right" class="font-medium tabular-nums">S/ {{ number_format((float) ($c->deuda_capital ?? $c->saldo_capital ?? 0), 2) }}</x-tables.td>
                            <x-tables.td align="right" class="font-bold tabular-nums">S/ {{ number_format((float) ($c->deuda_total ?? 0), 2) }}</x-tables.td>
                            <x-tables.td align="center">
                                @if($accountCcdDocs->isEmpty())
                                    <span class="text-[11px] text-kp-muted" title="Sin documento CCD asociado a esta cuenta">—</span>
                                @else
                                    <div class="flex flex-wrap items-center justify-center gap-1">
                                        @foreach($accountCcdDocs as $document)
                                            @php
                                                $ccdHref = $document->link
                                                    ?: (preg_match('#^https?://#', (string) $document->pdf)
                                                        ? $document->pdf
                                                        : asset('storage/ccd/'.ltrim((string) $document->pdf, '/')));
                                                $ccdLabel = $document->pdf
                                                    ? pathinfo((string) $document->pdf, PATHINFO_FILENAME)
                                                    : 'CCD '.($document->cosecha ?: '#'.$document->id);
                                            @endphp
                                            @if($ccdHref)
                                                <x-ui.button :href="$ccdHref"
                                                             target="_blank"
                                                             rel="noopener"
                                                             variant="info"
                                                             size="icon-sm"
                                                             title="Abrir {{ $ccdLabel }}"
                                                             aria-label="Abrir documento CCD {{ $ccdLabel }}">
                                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V14.25Z" />
                                                    </svg>
                                                    <span class="sr-only">Abrir CCD</span>
                                                </x-ui.button>
                                            @endif
                                        @endforeach
                                        @if($accountCcdDocs->count() > 1)
                                            <span class="text-[10px] font-bold text-blue-700">{{ $accountCcdDocs->count() }}</span>
                                        @endif
                                    </div>
                                @endif
                            </x-tables.td>
                            <x-tables.td align="center">
                                @if($last && (str_contains($estado, 'pend') || str_contains($estado, 'pre')))
                                    <x-clientes.status-badge :status="$last->workflow_estado">En proceso</x-clientes.status-badge>
                                @elseif($last && str_contains($estado, 'aprob') && !str_contains($estado, 'pre'))
                                    <x-ui.button :href="route('cna.pdf', $last->id)"
                                                 target="_blank"
                                                 rel="noopener"
                                                 size="icon-sm"
                                                 variant="danger"
                                                 title="Descargar CNA aprobada"
                                                 aria-label="Descargar CNA aprobada">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V14.25ZM9 15h6m-3-3v6" />
                                        </svg>
                                        <span class="sr-only">Descargar CNA</span>
                                    </x-ui.button>
                                @else
                                    <x-ui.button type="button"
                                                 variant="secondary"
                                                 size="icon-sm"
                                                 class="genCnaBtn"
                                                 title="Generar CNA para esta cuenta"
                                                 aria-label="Generar CNA para la operación {{ $c->operacion }}"
                                                 data-modal-open="#modalCna"
                                                 data-dni="{{ $dni }}"
                                                 data-cuenta="{{ $c->cuenta }}"
                                                 data-oper="{{ $c->operacion }}"
                                                 data-cosecha="{{ $c->cosecha }}"
                                                 data-entidad="{{ $c->entidad }}">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V14.25ZM12 11.25v6m3-3H9" />
                                        </svg>
                                        <span class="sr-only">Generar CNA</span>
                                    </x-ui.button>
                                @endif
                            </x-tables.td>
                            <x-tables.td>
                                <div class="flex items-center gap-1.5">
                                    <x-ui.badge :variant="$cnt > 0 ? 'success' : 'neutral'">{{ $cnt }} pago(s)</x-ui.badge>
                                    @if($sum > 0)
                                        <span class="font-semibold tabular-nums text-kp-ink">S/ {{ number_format($sum, 2) }}</span>
                                    @endif

                                    @if($hasList)
                                        @php $collapseId = 'pagos-'.$loop->index; @endphp
                                        <x-ui.button type="button"
                                                     variant="ghost"
                                                     size="icon-sm"
                                                     data-toggle-target="#{{ $collapseId }}"
                                                     title="Ver detalle de pagos"
                                                     aria-label="Ver detalle de pagos de la operación {{ $c->operacion }}">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </x-ui.button>
                                    @endif
                                </div>

                                @if($hasList)
                                    <div id="{{ $collapseId }}" class="mt-2 hidden min-w-56 rounded-md border border-kp-border bg-slate-50 p-2">
                                        <ul class="space-y-1 text-[11px] text-kp-ink">
                                            @foreach($c->pagos_list as $p)
                                                @php
                                                    $f = !empty($p->fecha) ? \Carbon\Carbon::parse($p->fecha)->format('d/m/Y') : '-';
                                                    $m = number_format((float) ($p->monto ?? 0), 2);
                                                    $src = $p->fuente ?? '-';
                                                @endphp
                                                <li class="grid grid-cols-[auto_auto_1fr] gap-2 border-b border-kp-border pb-1 last:border-0 last:pb-0">
                                                    <span>{{ $f }}</span>
                                                    <span class="font-semibold tabular-nums">S/ {{ $m }}</span>
                                                    <span class="truncate text-right text-kp-muted">{{ $src }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </x-tables.td>
                        </tr>
                    @empty
                        <x-tables.empty-row colspan="11" message="Sin cuentas registradas" />
                    @endforelse
                </tbody>
            </x-tables.table>
        </x-clientes.action-panel>

        <x-clientes.payments-table :pagos="$pagos" :can-delete="$canDeletePagos" :dni="$dni" />

        <x-promesas.promise-history :promesas="$promesas" />
        <x-promesas.payment-schedule />
        <x-promesas.create-modal :dni="$dni" />
        <x-cna.create-modal :dni="$dni" />
    </x-layout.page-shell>
@endsection
