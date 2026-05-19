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
                            <button type="button" title="{{ $nota }}" onclick="event.stopPropagation()" class="text-kp-muted transition-colors hover:text-kp-ink focus:outline-none">
                                <span class="sr-only">Ver nota</span>
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </button>
                        @else
                            <span class="text-kp-muted">-</span>
                        @endif
                    </x-tables.td>

                    <x-tables.td align="center">
                        @if(strtolower($pp->workflow_estado ?? '') === 'aprobada')
                            <a href="{{ route('promesas.acuerdo', $pp) }}" target="_blank" onclick="event.stopPropagation()" title="Descargar acuerdo en PDF" class="inline-block text-red-600 transition-colors hover:text-red-800 focus:outline-none">
                                <span class="sr-only">PDF</span>
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </a>
                        @else
                            <span class="text-kp-muted">-</span>
                        @endif
                    </x-tables.td>
                    <x-tables.td>{{ $pp->user->name ?? '-' }}</x-tables.td>
                    <x-tables.td>{{ ucfirst(strtolower($pp->workflow_estado ?? 'Pendiente')) }}</x-tables.td>
                </tr>
            @empty
                <x-tables.empty-row colspan="8" message="Sin promesas" />
            @endforelse
        </tbody>
    </x-tables.table>
</x-clientes.action-panel>
