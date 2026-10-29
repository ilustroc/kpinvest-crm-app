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
        $canDeletePagos = \Illuminate\Support\Facades\Gate::allows('delete-client-payments');

        $accountCount = $cuentas->pluck('cuenta')->filter(fn ($account) => trim((string) $account) !== '')->unique()->count();
        $operationCount = $cuentas->count();
        $cnaHasErrors = $errors->has('fecha_pago_realizado')
            || $errors->has('monto_pagado')
            || $errors->has('operaciones')
            || $errors->has('cuenta')
            || $errors->has('observacion');

        // La CNA se persiste por operación. Se normaliza la clave para no depender
        // de espacios accidentales en el valor almacenado.
        $cnasByOperationKey = collect($cnasByOperacion ?? [])->mapWithKeys(
            fn ($items, $operation) => [trim((string) $operation) => collect($items)]
        );
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

        <div class="min-w-0 space-y-5">
            <x-clientes.action-panel
                id="cuentas"
                title="Cuentas y operaciones"
                subtitle="{{ $accountCount }} {{ $accountCount === 1 ? 'cuenta' : 'cuentas' }} · {{ $operationCount }} {{ $operationCount === 1 ? 'operación' : 'operaciones' }} · Selecciona una operación para generar una propuesta"
            >
                <x-slot:actions>
                    <x-ui.button
                        id="btnPropuesta"
                        type="button"
                        data-modal-open="#modalPropuesta"
                        disabled
                        class="w-full justify-center sm:w-auto"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Generar propuesta
                        <span id="selCount" class="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-bold leading-none">0</span>
                    </x-ui.button>
                </x-slot:actions>

                <x-tables.table id="tblCuentas" :flush="true" :contained="true" min-width="min-w-[1080px]" class="[&_th]:px-3 [&_td]:px-3">
                    <thead>
                        <tr>
                            <x-tables.th align="center" class="w-9">
                                <input type="checkbox" id="chkAll" class="size-4 rounded border-kp-border accent-kp-primary kp-focus" aria-label="Seleccionar todas las operaciones">
                            </x-tables.th>
                            <x-tables.th>Operación</x-tables.th>
                            <x-tables.th>Cuenta de deuda</x-tables.th>
                            <x-tables.th>Entidad / producto</x-tables.th>
                            <x-tables.th>Cosecha / lote</x-tables.th>
                            <x-tables.th>Asesor</x-tables.th>
                            <x-tables.th align="right">Capital</x-tables.th>
                            <x-tables.th align="right">Total</x-tables.th>
                            <x-tables.th align="center">CNA</x-tables.th>
                            <x-tables.th>Pagos</x-tables.th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cuentas as $c)
                            @php
                                $cnt = (int) ($c->pagos_count ?? 0);
                                $sum = (float) ($c->pagos_sum ?? 0);
                                $cnas = $cnasByOperationKey->get(trim((string) $c->operacion), collect());
                                $last = $cnas->sortByDesc(fn($x) => (string) ($x->created_at ?? ''))->first();
                                $estado = strtolower((string) ($last->workflow_estado ?? ''));
                                $capital = (float) ($c->deuda_capital ?? $c->saldo_capital ?? 0);
                                $total = (float) ($c->deuda_total ?? 0);
                                $currency = strtoupper(trim((string) ($c->moneda ?? 'PEN'))) ?: 'PEN';
                                $asesor = trim((string) ($c->asesor ?? ''));
                                $cnaIds = $cnas->pluck('id')->filter()->unique()->implode(',');
                            @endphp

                            <tr data-cuenta="{{ $c->cuenta }}"
                                data-oper="{{ $c->operacion }}"
                                data-cosecha="{{ $c->cosecha }}"
                                data-entidad="{{ $c->entidad }}"
                                data-producto="{{ $c->producto }}"
                                data-moneda="{{ $currency }}"
                                data-asesor="{{ $asesor }}"
                                data-capital="{{ $capital }}"
                                data-total="{{ $total }}"
                                data-pagos-count="{{ $cnt }}"
                                data-pagos-sum="{{ $sum }}"
                                data-cna-count="{{ $cnas->count() }}"
                                data-cna-ids="{{ $cnaIds }}"
                                data-cna-status="{{ $last->workflow_estado ?? '' }}"
                                data-cna-nro="{{ $last->nro_carta ?? '' }}"
                                data-cna-reason="{{ $last->motivo_rechazo ?? '' }}"
                                class="group cursor-pointer transition-colors hover:bg-slate-50/80 has-[:checked]:bg-kp-primary-soft/50">
                                <x-tables.td align="center">
                                    <input type="checkbox"
                                           class="chkOp size-4 rounded border-kp-border accent-kp-primary kp-focus"
                                           value="{{ $c->operacion }}"
                                           aria-label="Seleccionar operación {{ $c->operacion }}"
                                           {{ empty($c->operacion) ? 'disabled' : '' }}>
                                </x-tables.td>
                                <x-tables.td>
                                    <div class="font-semibold text-kp-ink">{{ $c->operacion ?? '-' }}</div>
                                    <div class="mt-0.5 max-w-[190px] truncate text-[10.5px] text-kp-muted" title="{{ $c->producto }}">
                                        {{ $c->producto ?? 'Sin producto' }}
                                    </div>
                                </x-tables.td>
                                <x-tables.td>
                                    <div class="font-mono text-[11.5px] font-medium text-slate-600" title="{{ $c->cuenta }}">
                                        {{ $c->cuenta ?: 'Sin cuenta' }}
                                    </div>
                                </x-tables.td>
                                <x-tables.td>
                                    <div class="max-w-[180px] truncate font-medium text-slate-700" title="{{ $c->entidad }}">{{ $c->entidad ?? 'Sin entidad' }}</div>
                                </x-tables.td>
                                <x-tables.td>
                                    <div class="font-medium text-slate-700">{{ $c->cosecha ?? 'Sin cosecha' }}</div>
                                    <div class="mt-0.5 text-[10px] text-kp-muted">Lote de cartera</div>
                                </x-tables.td>
                                <x-tables.td>
                                    @if(str_contains(mb_strtoupper($asesor), 'CANCEL'))
                                        <div class="flex max-w-[150px] items-center" title="Operación en CANCELADOS; no implica que la deuda esté pagada">
                                            <x-ui.badge variant="warning">CANCELADOS</x-ui.badge>
                                        </div>
                                    @else
                                        <div class="flex max-w-[150px] items-center gap-1.5 text-[11px] text-slate-600" title="Asesor asignado a esta operación">
                                            <svg class="size-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                            <span class="truncate font-medium">{{ $asesor !== '' ? $asesor : 'Sin asignar' }}</span>
                                        </div>
                                    @endif
                                </x-tables.td>
                                <x-tables.td align="right">
                                    <span class="tabular-nums text-slate-600">{{ \App\Support\Money::format($capital, $currency) }}</span>
                                </x-tables.td>
                                <x-tables.td align="right">
                                    <span class="font-semibold tabular-nums text-kp-ink">{{ \App\Support\Money::format($total, $currency) }}</span>
                                </x-tables.td>
                                <x-tables.td align="center">
                                    @if($last)
                                        @php
                                            $cnaRejected = str_contains($estado, 'rechaz');
                                        @endphp
                                        <div class="flex flex-col items-center gap-1">
                                            <x-clientes.status-badge :status="$last->workflow_estado">
                                                @if($cnaRejected) Rechazada @endif
                                            </x-clientes.status-badge>
                                            <div class="flex flex-wrap items-center justify-center gap-1" title="Última CNA de la operación">
                                                <span class="font-mono text-[10px] text-kp-muted">{{ $last->nro_carta ?: 'CNA' }}</span>
                                                @if($cnas->count() > 1)
                                                    <span class="rounded bg-slate-100 px-1 py-0.5 text-[9px] font-semibold text-slate-500" title="{{ $cnas->count() }} solicitudes registradas">x{{ $cnas->count() }}</span>
                                                @endif
                                            </div>
                                            @if($cnaRejected)
                                                <x-cna.request-button
                                                    :dni="$dni"
                                                    :cuenta="$c->cuenta"
                                                    :operacion="$c->operacion"
                                                    :cosecha="$c->cosecha"
                                                    :entidad="$c->entidad"
                                                    :producto="$c->producto"
                                                    :pagos-count="$cnt"
                                                    :pagos-sum="$sum"
                                                    :moneda="$currency"
                                                    label="Solicitar nueva"
                                                    title="Solicitar una nueva CNA para esta cuenta de deuda"
                                                />
                                            @else
                                                <x-cna.document-state :cna="$last" mode="compact" />
                                            @endif
                                        </div>
                                    @else
                                        <x-cna.request-button
                                            :dni="$dni"
                                            :cuenta="$c->cuenta"
                                            :operacion="$c->operacion"
                                            :cosecha="$c->cosecha"
                                            :entidad="$c->entidad"
                                            :producto="$c->producto"
                                            :pagos-count="$cnt"
                                            :pagos-sum="$sum"
                                            :moneda="$currency"
                                            label="Solicitar"
                                            title="Solicitar CNA para las operaciones de esta cuenta de deuda"
                                        />
                                    @endif
                                </x-tables.td>
                                <x-tables.td>
                                    <div class="flex flex-col items-start gap-1">
                                        <x-ui.badge :variant="$cnt > 0 ? 'success' : 'neutral'">{{ $cnt }} {{ $cnt === 1 ? 'pago' : 'pagos' }}</x-ui.badge>
                                        @if($sum > 0)
                                            <span class="text-[10.5px] font-medium tabular-nums text-kp-muted">{{ \App\Support\Money::format($sum, $currency) }}</span>
                                        @endif
                                    </div>
                                </x-tables.td>
                            </tr>
                        @empty
                            <x-tables.empty-row colspan="10" message="Sin cuentas u operaciones registradas." />
                        @endforelse
                    </tbody>
                </x-tables.table>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-kp-border px-4 py-2.5 text-[10.5px] text-kp-muted">
                    <span>Importes según la moneda de cada operación. Asignación, pagos y CNA se muestran por operación.</span>
                    <span class="sm:hidden">Desliza para ver más →</span>
                </div>
            </x-clientes.action-panel>

            <x-clientes.payments-table :pagos="$pagos" :can-delete="$canDeletePagos" :dni="$dni" />

            <x-promesas.promise-history :promesas="$promesas" />

            <x-clientes.documents-panel :ccd-docs="$ccdDocs" />
        </div>

        <x-promesas.payment-schedule />
        <x-promesas.create-modal :dni="$dni" :titular="$titular" />
        <x-cna.create-modal :dni="$dni" :titular="$titular" :auto-open="$cnaHasErrors" />
    </x-layout.page-shell>
@endsection
