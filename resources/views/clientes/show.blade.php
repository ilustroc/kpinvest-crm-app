{{-- resources/views/clientes/show.blade.php --}}
@extends('layouts.app')
@section('title', 'Cliente '.$dni)
@section('crumb', 'Cliente')
@section('tailwind_only', true)

@section('content')
    @php
        $totCapital = (float) $cuentas->sum(fn($x) => (float) ($x->deuda_capital ?? $x->saldo_capital ?? 0));
        $totDeuda = (float) $cuentas->sum(fn($x) => (float) ($x->deuda_total ?? 0));
        $totPagos = (float) $pagos->sum(fn($p) => (float) ($p->monto_pagado ?? $p->monto ?? 0));
        $ccdDocs = collect($ccdByDni[$dni] ?? []);
        $canDeletePagos = in_array(strtolower((string) optional(Auth::user())->role), ['administrador', 'supervisor', 'soporte'], true);
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
            :ccd-docs="$ccdDocs"
            :tot-capital="$totCapital"
            :tot-deuda="$totDeuda"
            :tot-pagos="$totPagos"
        />

        <x-clientes.action-panel title="Cuentas">
            <x-slot:actions>
                <x-ui.button id="btnPropuesta" type="button" data-modal-open="#modalPropuesta" disabled>
                    Generar propuesta
                    <span id="selCount" class="rounded-full bg-white/20 px-2 py-0.5 text-xs">0</span>
                </x-ui.button>
            </x-slot:actions>

            <x-tables.table id="tblCuentas">
                <thead>
                    <tr>
                        <x-tables.th align="center">
                            <input type="checkbox" id="chkAll" class="size-4 rounded border-kp-border text-kp-green kp-focus">
                        </x-tables.th>
                        <x-tables.th>Operacion</x-tables.th>
                        <x-tables.th>Asesor asignado</x-tables.th>
                        <x-tables.th>Entidad</x-tables.th>
                        <x-tables.th>Producto</x-tables.th>
                        <x-tables.th>Cosecha</x-tables.th>
                        <x-tables.th align="right">Deuda capital</x-tables.th>
                        <x-tables.th align="right">Deuda total</x-tables.th>
                        <x-tables.th>CNA(s)</x-tables.th>
                        <x-tables.th>Pagos</x-tables.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cuentas as $c)
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
                        @endphp

                        <tr data-cuenta="{{ $c->cuenta }}"
                            data-oper="{{ $c->operacion }}"
                            data-cosecha="{{ $c->cosecha }}"
                            data-entidad="{{ $c->entidad }}">
                            <x-tables.td align="center">
                                <input type="checkbox"
                                       class="chkOp size-4 rounded border-kp-border text-kp-green kp-focus"
                                       value="{{ $c->operacion }}"
                                       {{ empty($c->operacion) ? 'disabled' : '' }}>
                            </x-tables.td>
                            <x-tables.td>{{ $c->operacion ?? '-' }}</x-tables.td>
                            <x-tables.td>
                                {{ $c->asesor ?: 'Sin asignar' }}
                            </x-tables.td>
                            <x-tables.td>{{ $c->entidad ?? '-' }}</x-tables.td>
                            <x-tables.td>{{ $c->producto ?? '-' }}</x-tables.td>
                            <x-tables.td>{{ $c->cosecha ?? '-' }}</x-tables.td>
                            <x-tables.td align="right">{{ number_format((float) ($c->deuda_capital ?? $c->saldo_capital ?? 0), 2) }}</x-tables.td>
                            <x-tables.td align="right">{{ number_format((float) ($c->deuda_total ?? 0), 2) }}</x-tables.td>
                            <x-tables.td>
                                @if($last && (str_contains($estado, 'pend') || str_contains($estado, 'pre')))
                                    <x-clientes.status-badge :status="$last->workflow_estado">
                                        En proceso
                                    </x-clientes.status-badge>
                                @elseif($last && str_contains($estado, 'aprob') && !str_contains($estado, 'pre'))
                                    <x-ui.button :href="route('cna.pdf', $last->id)" target="_blank" size="sm" variant="danger" title="Descargar CNA aprobada">
                                        PDF
                                    </x-ui.button>
                                @else
                                    <x-ui.button type="button"
                                                variant="bare"
                                                size="none"
                                                class="genCnaBtn"
                                                title="Generar CNA para esta cuenta"
                                                data-modal-open="#modalCna"
                                                data-dni="{{ $dni }}"
                                                data-cuenta="{{ $c->cuenta }}"
                                                data-oper="{{ $c->operacion }}"
                                                data-cosecha="{{ $c->cosecha }}"
                                                data-entidad="{{ $c->entidad }}">
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12" />
                                        </svg>
                                    </x-ui.button>                                
                                @endif
                            </x-tables.td>
                            <x-tables.td>
                                <div class="flex flex-col gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-ui.badge>{{ $cnt }} pago(s)</x-ui.badge>
                                        @if($sum > 0)
                                            <span class="text-xs text-kp-muted">S/ {{ number_format($sum, 2) }}</span>
                                        @endif
                                    </div>

                                    @if($hasList)
                                        @php
                                            $collapseId = 'pagos-'.$loop->index;
                                        @endphp
                                        <x-ui.button type="button" variant="secondary" size="sm" data-toggle-target="#{{ $collapseId }}">
                                            Ver detalle
                                        </x-ui.button>
                                        <div id="{{ $collapseId }}" class="hidden rounded-md border border-kp-border bg-slate-50 p-3">
                                            <ul class="space-y-1 text-xs text-kp-ink">
                                                @foreach($c->pagos_list as $p)
                                                    @php
                                                        $f = !empty($p->fecha) ? \Carbon\Carbon::parse($p->fecha)->format('d/m/Y') : '-';
                                                        $m = number_format((float) ($p->monto ?? 0), 2);
                                                        $src = $p->fuente ?? '-';
                                                    @endphp
                                                    <li class="flex flex-wrap items-center gap-2">
                                                        <span>{{ $f }}</span>
                                                        <span>S/ {{ $m }}</span>
                                                        <span class="text-kp-muted">{{ $src }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </x-tables.td>
                        </tr>
                    @endforeach
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
