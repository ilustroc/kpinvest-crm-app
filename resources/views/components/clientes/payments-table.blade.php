@props([
    'pagos',
    'canDelete' => false,
    'dni',
])

@php
    $paymentTotal = (float) collect($pagos ?? [])->sum(fn ($payment) => (float) ($payment->monto_pagado ?? 0));
@endphp

<x-clientes.action-panel
    title="Historial de pagos"
    subtitle="{{ count($pagos ?? []) }} {{ count($pagos ?? []) === 1 ? 'registro' : 'registros' }} · {{ \App\Support\Money::format($paymentTotal, 'PEN') }}"
    accent="success"
>
    <x-slot:actions>
        @if($canDelete)
            <label class="inline-flex items-center gap-2 rounded-lg border border-kp-border bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600">
                <input type="checkbox" id="toggleDeletePagos" class="size-4 rounded border-kp-border accent-kp-primary kp-focus">
                Eliminar pagos
            </label>
            <form id="frmDeletePagos"
                  method="POST"
                  action="{{ route('clientes.pagos.delete', $dni) }}"
                  data-confirm="Eliminar los pagos seleccionados?"
                  class="inline-flex">
                @csrf
                <x-ui.button type="submit" id="btnDeletePagos" variant="danger" size="sm" disabled>
                    Eliminar seleccionados
                </x-ui.button>
            </form>
        @endif

        <x-ui.button type="button" variant="secondary" size="sm" data-toggle-target="#pagosCollapse">
            Ver/ocultar
        </x-ui.button>
    </x-slot:actions>

    <div id="pagosCollapse" class="min-w-0">
        <x-tables.table id="tblPagos" :flush="true" :contained="true" min-width="min-w-[580px]" class="[&_th]:px-3 [&_td]:px-3">
            <thead>
                <tr>
                    @if($canDelete)
                        <x-tables.th class="col-del hidden w-10">
                            <input type="checkbox" id="chkAllPagos" class="size-4 rounded border-kp-border accent-kp-primary kp-focus" aria-label="Seleccionar todos los pagos">
                        </x-tables.th>
                    @endif
                    <x-tables.th>Fecha</x-tables.th>
                    <x-tables.th>Operación</x-tables.th>
                    <x-tables.th align="right">Monto</x-tables.th>
                    <x-tables.th>Agente</x-tables.th>
                    <x-tables.th>Cuenta de recaudo</x-tables.th>
                </tr>
            </thead>
            <tbody>
                @forelse($pagos as $p)
                    @php
                        $id = $p->id ?? $p['id'] ?? null;
                        $oper = $p->operacion ?? $p['operacion'] ?? '-';
                        $monto = $p->monto_pagado ?? $p['monto_pagado'] ?? 0;
                        $fecha = $p->fecha ?? $p['fecha'] ?? null;
                        $gestor = $p->gestor ?? $p['gestor'] ?? '-';
                        $cuenta = $p->cuenta_recaudo ?? $p['cuenta_recaudo'] ?? '-';
                    @endphp
                    <tr class="transition-colors hover:bg-slate-50/80">
                        @if($canDelete)
                            <x-tables.td class="col-del hidden">
                                @if($id)
                                    <input type="checkbox"
                                           name="ids[]"
                                           form="frmDeletePagos"
                                           class="chkPago size-4 rounded border-kp-border accent-kp-primary kp-focus"
                                           value="{{ $id }}"
                                           aria-label="Seleccionar pago del {{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '' }}">
                                @endif
                            </x-tables.td>
                        @endif
                        <x-tables.td>
                            <span class="font-medium tabular-nums">{{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '-' }}</span>
                        </x-tables.td>
                        <x-tables.td>
                            <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 text-[10.5px] font-semibold text-slate-600">{{ $oper }}</span>
                        </x-tables.td>
                        <x-tables.td align="right">
                            <span class="font-semibold tabular-nums text-kp-ink">{{ \App\Support\Money::format($monto, 'PEN') }}</span>
                        </x-tables.td>
                        <x-tables.td>{{ $gestor }}</x-tables.td>
                        <x-tables.td>
                            <span class="text-[12px] text-slate-600">{{ $cuenta }}</span>
                        </x-tables.td>
                    </tr>
                @empty
                    <x-tables.empty-row :colspan="$canDelete ? 6 : 5" message="Sin pagos registrados para este cliente." />
                @endforelse
            </tbody>
        </x-tables.table>

        <div class="border-t border-kp-border px-4 py-2 text-right text-[10.5px] text-kp-muted sm:hidden">
            Desliza para ver más →
        </div>
    </div>
</x-clientes.action-panel>
