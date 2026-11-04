@props([
    'asignaciones',
    'dni',
])

@php
    $items = collect($asignaciones ?? []);
    $canReviewAsignaciones = \Illuminate\Support\Facades\Gate::allows('review-asignaciones');
@endphp

<x-clientes.action-panel
    id="historial-asignaciones"
    title="Historial de asignaciones"
    subtitle="{{ $items->count() }} {{ $items->count() === 1 ? 'registro' : 'registros' }} · Trazabilidad lineal por operación (solicitudes manuales y cargas masivas)"
>
    @if($canReviewAsignaciones)
        <x-slot:actions>
            <a
                href="{{ route('autorizacion.index', ['asig_numdoc' => $dni, 'asig_estado' => 'todos']) }}#panel-asignaciones"
                class="inline-flex items-center gap-1.5 rounded-md border border-kp-border bg-white px-2.5 py-1.5 text-[11px] font-medium text-kp-ink shadow-2xs transition hover:bg-slate-50 kp-focus"
            >
                <svg class="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                Ver en Autorizaciones
            </a>
        </x-slot:actions>
    @endif

    <x-tables.table id="tblAsignacionesCliente" :flush="true" :contained="true" min-width="min-w-[820px]" class="[&_th]:px-3 [&_td]:px-3">
        <thead>
            <tr>
                <x-tables.th>Fecha solicitud</x-tables.th>
                <x-tables.th>Operación / Entidad</x-tables.th>
                <x-tables.th>Solicitante</x-tables.th>
                <x-tables.th>Asesor destino</x-tables.th>
                <x-tables.th>Motivo / Origen</x-tables.th>
                <x-tables.th align="center">Estado</x-tables.th>
                <x-tables.th>Resolución</x-tables.th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $asig)
                @php
                    $estado = strtolower((string) ($asig->estado ?? 'aprobada'));
                    $badgeVariant = match ($estado) {
                        'aprobada' => 'success',
                        'rechazada' => 'danger',
                        default => 'warning',
                    };
                    $badgeLabel = match ($estado) {
                        'aprobada' => 'Aprobada',
                        'rechazada' => 'Rechazada',
                        default => 'Pendiente',
                    };
                    $cuenta = $asig->clienteCuenta;
                    $origenLabel = ($asig->origen ?? '') === 'manual' ? 'Solicitud manual' : 'Carga masiva';
                @endphp
                <tr class="transition-colors hover:bg-slate-50/80">
                    <x-tables.td>
                        <div class="font-medium tabular-nums text-kp-ink">
                            {{ $asig->fecha_solicitud ? $asig->fecha_solicitud->format('d/m/Y H:i') : 'Histórico' }}
                        </div>
                        <div class="text-[10px] text-kp-muted">ID #{{ $asig->id }}</div>
                    </x-tables.td>
                    <x-tables.td>
                        <div class="font-semibold text-kp-ink">{{ $asig->operacion }}</div>
                        <div class="mt-0.5 max-w-[180px] truncate text-[10.5px] text-kp-muted">
                            {{ $cuenta?->entidad ?? '—' }} · {{ $cuenta?->producto ?? '—' }}
                        </div>
                    </x-tables.td>
                    <x-tables.td>
                        <span class="text-[11.5px] text-slate-600">{{ $asig->solicitante_nombre ?? 'Sistema / Histórico' }}</span>
                    </x-tables.td>
                    <x-tables.td>
                        <span class="font-semibold text-kp-ink">{{ $asig->asesor_nombre ?? 'Sin asignar' }}</span>
                    </x-tables.td>
                    <x-tables.td>
                        <div class="max-w-[220px] truncate text-[11.5px] text-kp-ink" title="{{ $asig->motivo }}">
                            {{ $asig->motivo ?: 'Asignación histórica' }}
                        </div>
                        <div class="mt-0.5 text-[10px] text-kp-muted">{{ $origenLabel }}</div>
                    </x-tables.td>
                    <x-tables.td align="center">
                        <x-ui.badge :variant="$badgeVariant">{{ $badgeLabel }}</x-ui.badge>
                    </x-tables.td>
                    <x-tables.td>
                        @if($asig->aprobador_nombre || $asig->fecha_aprobacion)
                            <div class="text-[11px] font-medium text-kp-ink">{{ $asig->aprobador_nombre ?? 'Sistema' }}</div>
                            <div class="text-[10px] tabular-nums text-kp-muted">
                                {{ $asig->fecha_aprobacion ? $asig->fecha_aprobacion->format('d/m/Y H:i') : '' }}
                            </div>
                            @if($asig->observacion_resolucion)
                                <div class="mt-0.5 max-w-[180px] truncate text-[10px] text-slate-500" title="{{ $asig->observacion_resolucion }}">
                                    {{ $asig->observacion_resolucion }}
                                </div>
                            @endif
                        @else
                            <span class="text-[11px] text-slate-400">En espera de revisión</span>
                        @endif
                    </x-tables.td>
                </tr>
            @empty
                <x-tables.empty-row colspan="7" message="Sin historial de asignaciones registrado para este cliente." />
            @endforelse
        </tbody>
    </x-tables.table>
</x-clientes.action-panel>
