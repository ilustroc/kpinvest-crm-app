@props([
    'promesas',
])

<x-clientes.action-panel
    title="Promesas y propuestas"
    subtitle="{{ count($promesas ?? []) }} {{ count($promesas ?? []) === 1 ? 'registro' : 'registros' }} · Haz clic en una fila para revisar su cronograma"
    class="promesas"
    accent="warning"
>
    <x-slot:actions>
        <span class="inline-flex items-center gap-1.5 text-[10.5px] font-medium text-kp-muted">
            <svg class="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            Cronograma disponible
        </span>
    </x-slot:actions>

    <x-tables.table id="tblPromesas" :flush="true" :contained="true" min-width="min-w-[680px]" class="[&_th]:px-3 [&_td]:px-3">
        <thead>
            <tr>
                <x-tables.th>Fecha</x-tables.th>
                <x-tables.th>Propuesta</x-tables.th>
                <x-tables.th align="right">Monto negociación</x-tables.th>
                <x-tables.th>Usuario creación</x-tables.th>
                <x-tables.th>Estado</x-tables.th>
                <x-tables.th align="center">Acciones</x-tables.th>
            </tr>
        </thead>
        <tbody>
            @forelse($promesas as $pp)
                @php
                    $tipoLabel = match($pp->tipo) {
                        'convenio_balon' => 'Convenio [Cuota Balón]',
                        'convenio' => 'Convenio',
                        'cancelacion' => 'Cancelación',
                        'parcial' => 'Pago parcial',
                        default => ucfirst(str_replace('_', ' ', (string) $pp->tipo)),
                    };

                    $quotaLabel = match($pp->tipo) {
                        'cancelacion' => 'Pago único',
                        default => ($pp->nro_cuotas ?? 0) > 0
                            ? $pp->nro_cuotas.' cuotas'
                            : 'Pago único',
                    };

                    $ops = $pp->relationLoaded('operaciones') && $pp->operaciones->count()
                        ? $pp->operaciones->pluck('operacion')->all()
                        : array_filter(array_map('trim', explode(',', (string) ($pp->operacion ?? ''))));

                    $montoNeg = (float) ($pp->monto ?? 0);
                    if ($montoNeg <= 0) {
                        $montoNeg = (float) ($pp->cuotas->first()->monto ?? 0);
                    }

                    $nota = trim((string) ($pp->nota ?? ''));
                    $status = strtolower((string) ($pp->workflow_estado ?? 'pendiente'));
                    $statusLabel = match ($status) {
                        'preaprobada' => 'Pre-aprobada',
                        'rechazada_sup' => 'Rechazada (Sup)',
                        default => ucfirst(str_replace('_', ' ', $status)),
                    };
                    $statusVariant = match (true) {
                        str_contains($status, 'aprob') && !str_contains($status, 'pre') => 'success',
                        str_contains($status, 'rechaz') => 'danger',
                        str_contains($status, 'pre') || str_contains($status, 'pend') => 'warning',
                        default => 'neutral',
                    };
                    $ds = [
                        'tipo' => (string) $pp->tipo,
                        'cuotas' => in_array($pp->tipo, ['cancelacion', 'parcial'], true)
                            ? [[
                                'nro' => 1,
                                'fecha' => $pp->fecha_promesa || $pp->fecha_pago
                                    ? \Carbon\Carbon::parse($pp->fecha_promesa ?: $pp->fecha_pago)->format('Y-m-d')
                                    : null,
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
                <tr class="cursor-pointer transition-colors hover:bg-slate-50/80"
                    data-open-cronograma="1"
                    data-promesa-id="{{ $pp->id }}"
                    data-dataset='@json($ds)'
                    title="Abrir cronograma">
                    <x-tables.td>
                        <span class="font-medium tabular-nums">{{ optional($pp->created_at)->format('d/m/Y') ?? '-' }}</span>
                    </x-tables.td>
                    <x-tables.td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="font-semibold text-kp-ink">{{ $tipoLabel }}</span>
                            @if($pp->tipo === 'convenio_balon')
                                <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[9.5px] font-semibold text-amber-700">Balón</span>
                            @endif
                        </div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1 text-[10.5px] text-kp-muted">
                            <span>{{ $quotaLabel }}</span>
                            <span class="text-slate-300" aria-hidden="true">·</span>
                            <span class="max-w-[280px] truncate">
                                @if($ops)
                                    {{ implode(', ', $ops) }}
                                @else
                                    Sin operación
                                @endif
                            </span>
                        </div>
                    </x-tables.td>
                    <x-tables.td align="right">
                        <span class="font-semibold tabular-nums text-kp-ink">{{ \App\Support\Money::format($montoNeg, 'PEN') }}</span>
                    </x-tables.td>
                    <x-tables.td>
                        <span class="text-[12px] text-slate-600">{{ $pp->user->name ?? '-' }}</span>
                    </x-tables.td>
                    <x-tables.td>
                        <x-ui.badge :variant="$statusVariant">{{ $statusLabel }}</x-ui.badge>
                    </x-tables.td>
                    <x-tables.td align="center">
                        <div class="inline-flex items-center gap-1">
                            @if($nota !== '')
                                <button type="button" title="{{ $nota }}" onclick="event.stopPropagation()" class="inline-flex size-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-kp-ink kp-focus">
                                    <span class="sr-only">Ver nota</span>
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                </button>
                            @endif

                            @if(strtolower($pp->workflow_estado ?? '') === 'aprobada')
                                <a href="{{ route('promesas.acuerdo', $pp) }}" target="_blank" onclick="event.stopPropagation()" title="Descargar acuerdo en PDF" class="inline-flex size-7 items-center justify-center rounded-md text-red-500 transition-colors hover:bg-red-50 hover:text-red-700 kp-focus">
                                    <span class="sr-only">PDF</span>
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                </a>
                            @endif

                            @if($nota === '' && strtolower($pp->workflow_estado ?? '') !== 'aprobada')
                                <span class="text-slate-300">-</span>
                            @endif
                        </div>
                    </x-tables.td>
                </tr>
            @empty
                <x-tables.empty-row colspan="6" message="Sin promesas ni propuestas registradas." />
            @endforelse
        </tbody>
    </x-tables.table>

    <div class="border-t border-kp-border px-4 py-2 text-right text-[10.5px] text-kp-muted sm:hidden">
        Desliza para ver más →
    </div>
</x-clientes.action-panel>
