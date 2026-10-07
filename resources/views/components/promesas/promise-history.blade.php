@props([
    'promesas',
])

<x-clientes.action-panel id="promesas" title="Promesas de pago" subtitle="{{ count($promesas ?? []) }} registro(s)" class="promesas kp-section-anchor">
    <div class="max-h-[300px] overflow-auto">
    <x-tables.table id="tblPromesas">
        <thead>
            <tr>
                <x-tables.th>Fecha</x-tables.th>
                <x-tables.th>Operacion(es)</x-tables.th>
                <x-tables.th align="right">Monto negociacion</x-tables.th>
                <x-tables.th>Nota</x-tables.th>
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
                    $notePopoverId = 'promesa-nota-'.$pp->id;
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
                    <x-tables.td>
                        @if($ops)
                            {{ implode(', ', $ops) }}
                        @else
                            <span class="text-kp-muted">-</span>
                        @endif
                    </x-tables.td>
                    <x-tables.td align="right">S/ {{ number_format($montoNeg, 2) }}</x-tables.td>
                    <x-tables.td>
                        @if($nota !== '')
                            <button type="button"
                                    popovertarget="{{ $notePopoverId }}"
                                    onclick="event.stopPropagation()"
                                    class="inline-flex items-center gap-1.5 rounded-sm border border-kp-border bg-white px-2 py-1 text-[10px] font-semibold text-kp-blue transition hover:border-kp-blue hover:bg-kp-blue-soft kp-focus"
                                    aria-label="Ver nota de la promesa">
                                <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                Ver nota
                            </button>

                            <div id="{{ $notePopoverId }}"
                                 popover
                                 onclick="event.stopPropagation()"
                                 class="m-auto w-[min(92vw,460px)] rounded-sm border border-slate-300 bg-white p-0 text-left text-kp-ink shadow-lg backdrop:bg-slate-950/35">
                                <div class="flex items-center justify-between border-b border-kp-border bg-slate-50 px-3 py-2.5">
                                    <div>
                                        <div class="text-xs font-bold">Nota de la promesa</div>
                                        <div class="mt-0.5 text-[10px] text-kp-muted">{{ optional($pp->created_at)->format('d/m/Y') ?? '-' }} · {{ $tipoLabel }}</div>
                                    </div>
                                    <button type="button"
                                            popovertarget="{{ $notePopoverId }}"
                                            popovertargetaction="hide"
                                            class="inline-flex size-7 items-center justify-center rounded-sm text-kp-muted hover:bg-slate-200 hover:text-kp-ink kp-focus"
                                            aria-label="Cerrar nota">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                                    </button>
                                </div>
                                <p class="whitespace-pre-wrap break-words px-3 py-3 text-xs leading-5">{{ $nota }}</p>
                            </div>
                        @else
                            <span class="text-kp-muted">-</span>
                        @endif
                    </x-tables.td>

                    <x-tables.td align="center">
                        @if(strtolower($pp->workflow_estado ?? '') === 'aprobada')
                            <a href="{{ route('promesas.acuerdo', $pp) }}" target="_blank" onclick="event.stopPropagation()" title="Descargar acuerdo en PDF" aria-label="Descargar acuerdo en PDF" class="inline-flex items-center gap-1 rounded-sm border border-red-200 bg-red-50 px-1.5 py-1 text-[10px] font-bold text-red-700 transition-colors hover:border-red-300 hover:bg-red-100 kp-focus">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 2.25H6.75A1.5 1.5 0 0 0 5.25 3.75v16.5a1.5 1.5 0 0 0 1.5 1.5h10.5a1.5 1.5 0 0 0 1.5-1.5V6.75l-4.5-4.5Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 2.25v4.5h4.5M8.25 15.75h7.5M8.25 12.75h7.5" />
                                </svg>
                                PDF
                            </a>
                        @else
                            <span class="text-kp-muted">-</span>
                        @endif
                    </x-tables.td>
                    <x-tables.td>{{ $pp->user->name ?? '-' }}</x-tables.td>
                    <x-tables.td>{{ ucfirst(strtolower($pp->workflow_estado ?? 'Pendiente')) }}</x-tables.td>
                </tr>
            @empty
                <x-tables.empty-row colspan="7" message="Sin promesas" />
            @endforelse
        </tbody>
    </x-tables.table>
    </div>
</x-clientes.action-panel>
