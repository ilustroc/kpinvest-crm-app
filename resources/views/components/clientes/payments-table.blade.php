@props([
    'pagos',
    'canDelete' => false,
    'dni',
])

<x-clientes.action-panel title="Pagos" subtitle="{{ count($pagos ?? []) }} registro(s)">
    <x-slot:actions>
        @if($canDelete)
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-kp-ink">
                <input type="checkbox" id="toggleDeletePagos" class="size-4 rounded border-kp-border text-kp-green kp-focus">
                Eliminar pagos
            </label>
            <form id="frmDeletePagos"
                  method="POST"
                  action="{{ route('clientes.pagos.delete', $dni) }}"
                  data-confirm="Eliminar los pagos seleccionados?">
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

    <div id="pagosCollapse">
        <x-tables.table id="tblPagos">
            <thead>
                <tr>
                    @if($canDelete)
                        <x-tables.th class="col-del hidden">
                            <input type="checkbox" id="chkAllPagos" class="size-4 rounded border-kp-border text-kp-green kp-focus">
                        </x-tables.th>
                    @endif
                    <x-tables.th>Fecha</x-tables.th>
                    <x-tables.th>Operacion</x-tables.th>
                    <x-tables.th align="right">Monto (S/)</x-tables.th>
                    <x-tables.th>Agente</x-tables.th>
                    <x-tables.th>Cuenta recaudo</x-tables.th>
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
                    <tr>
                        @if($canDelete)
                            <x-tables.td class="col-del hidden">
                                @if($id)
                                    <input type="checkbox"
                                           name="ids[]"
                                           form="frmDeletePagos"
                                           class="chkPago size-4 rounded border-kp-border text-kp-green kp-focus"
                                           value="{{ $id }}">
                                @endif
                            </x-tables.td>
                        @endif
                        <x-tables.td>{{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '' }}</x-tables.td>
                        <x-tables.td>{{ $oper }}</x-tables.td>
                        <x-tables.td align="right">{{ number_format((float) $monto, 2, '.', ',') }}</x-tables.td>
                        <x-tables.td>{{ $gestor }}</x-tables.td>
                        <x-tables.td>{{ $cuenta }}</x-tables.td>
                    </tr>
                @empty
                    <x-tables.empty-row :colspan="$canDelete ? 6 : 5" message="Sin pagos" />
                @endforelse
            </tbody>
        </x-tables.table>
    </div>
</x-clientes.action-panel>
