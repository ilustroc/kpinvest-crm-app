@props([
    'promesas',
])

<x-clientes.action-panel title="Promesas de pago" subtitle="{{ count($promesas ?? []) }} registro(s)" class="promesas">
    <x-tables.table id="tblPromesas">
        <thead>
            <tr>
                <x-tables.th>Fecha</x-tables.th>
                <x-tables.th>Tipo</x-tables.th>
                <x-tables.th>Operacion(es)</x-tables.th>
                <x-tables.th align="right">Monto negociacion</x-tables.th>
                <x-tables.th align="center">Nota</x-tables.th>
                <x-tables.th align="center">Campaña</x-tables.th>
                <x-tables.th>Usuario creacion</x-tables.th>
                <x-tables.th>Estado</x-tables.th>
            </tr>
        </thead>
        <tbody>
            @forelse($promesas as $pp)
                @php
                    $tipoLabel = match($pp->tipo) {
                        'convenio_balon' => 'Convenio [Cuota Balon]',
                        'convenio' => 'Convenio',
                        'cancelacion' => 'Cancelacion',
                        default => strtoupper((string) $pp->tipo),
                    };

                    $ops = $pp->relationLoaded('operaciones') && $pp->operaciones->count()
                        ? $pp->operaciones->pluck('operacion')->all()
                        : array_filter(array_map('trim', explode(',', (string) ($pp->operacion ?? ''))));

                    $montoNeg = (float) ($pp->monto ?? 0);
                    if ($montoNeg <= 0) {
                        $montoNeg = (float) ($pp->cuotas->first()->monto ?? 0);
                    }

                    $nota = trim((string) ($pp->nota ?? ''));
                    $ds = [
                        'tipo' => (string) $pp->tipo,
                        'cuotas' => $pp->tipo === 'cancelacion'
                            ? [[
                                'nro' => 1,
                                'fecha' => optional($pp->fecha_pago)->format('Y-m-d'),
                                'monto' => (float) ($pp->monto ?? 0),
                                'es_balon' => 0,
                            ]]
                            : $pp->cuotas->map(fn($c) => [
                                'nro' => (int) $c->nro,
                                'fecha' => optional($c->fecha)->format('Y-m-d'),
                                'monto' => (float) $c->monto,
                                'es_balon' => (int) ($c->es_balon ?? 0),
                            ])->values(),
                    ];
                @endphp
                <tr class="cursor-pointer hover:bg-slate-50"
                    data-open-cronograma="1"
                    data-promesa-id="{{ $pp->id }}"
                    data-dataset='@json($ds)'>
                    <x-tables.td>{{ optional($pp->created_at)->format('d/m/Y') ?? '-' }}</x-tables.td>
                    <x-tables.td>{{ $tipoLabel }}</x-tables.td>
                    <x-tables.td>
                        @if($ops)
                            {{ implode(', ', $ops) }}
                        @else
                            <span class="text-kp-muted">-</span>
                        @endif
                    </x-tables.td>
                    <x-tables.td align="right">S/ {{ number_format($montoNeg, 2) }}</x-tables.td>
                    <x-tables.td align="center">
                        @if($nota !== '')
                            <button type="button" title="{{ $nota }}" class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">
                                Ver
                            </button>
                        @else
                            <span class="text-kp-muted">-</span>
                        @endif
                    </x-tables.td>
                    <x-tables.td align="center">
                        @if(strtolower($pp->workflow_estado ?? '') === 'aprobada')
                            <x-ui.button :href="route('promesas.acuerdo', $pp)" target="_blank" variant="secondary" size="sm" title="Descargar acuerdo en PDF">
                                PDF
                            </x-ui.button>
                        @else
                            <span class="text-kp-muted">-</span>
                        @endif
                    </x-tables.td>
                    <x-tables.td>{{ $pp->user->name ?? '-' }}</x-tables.td>
                    <x-tables.td>
                        <x-clientes.status-badge :status="$pp->workflow_estado ?? 'pendiente'" />
                    </x-tables.td>
                </tr>
            @empty
                <x-tables.empty-row colspan="8" message="Sin promesas" />
            @endforelse
        </tbody>
    </x-tables.table>
</x-clientes.action-panel>
