@props([
    'dni',
    'titular' => null,
    'autoOpen' => false,
])

@if($autoOpen)
    <div data-modal-auto-open="#modalSolicitarAsignacion" hidden></div>
@endif

<x-ui.modal
    id="modalSolicitarAsignacion"
    title="Solicitar asignación de cuenta"
    subtitle="Revisa las operaciones seleccionadas e ingresa el motivo de tu solicitud antes de enviarla a revisión."
    max-width="2xl"
>
    <form id="formSolicitarAsignacion" method="POST" action="{{ route('clientes.asignaciones.store', $dni) }}" data-once>
        @csrf

        <div class="space-y-4 px-5 py-5">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-kp-border bg-slate-50/60 px-3.5 py-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Cliente</p>
                    <p class="mt-0.5 truncate text-[13px] font-semibold text-kp-ink">{{ $titular ?: 'Titular del documento' }}</p>
                    <p class="mt-0.5 text-[11px] text-kp-muted">Documento <span class="font-mono">{{ $dni }}</span></p>
                </div>
                <div class="text-right">
                    <x-ui.badge variant="warning">Pendiente de aprobación</x-ui.badge>
                    <p class="mt-1 text-[10.5px] text-kp-muted">
                        Solicitante / Destino: <span class="font-semibold text-kp-ink">{{ auth()->user()?->name }}</span>
                    </p>
                </div>
            </div>

            <section class="overflow-hidden rounded-lg border border-kp-border bg-white" aria-labelledby="asignacionOpsTitle">
                <div class="flex items-center justify-between gap-3 border-b border-kp-border bg-kp-surface-muted/70 px-3.5 py-2.5">
                    <div>
                        <h4 id="asignacionOpsTitle" class="text-[12px] font-semibold text-kp-ink">Operaciones seleccionadas</h4>
                        <p class="text-[10.5px] text-kp-muted">Se creará una solicitud individual por cada operación seleccionada.</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full border border-kp-border bg-white px-2.5 py-0.5 text-[10.5px] font-semibold text-kp-ink">
                        <span id="asignacionOpsCount">0</span>
                        <span id="asignacionOpsCountLabel">operaciones</span>
                    </span>
                </div>
                <div id="asignacionOpsResumen" class="grid gap-2 p-3.5 sm:grid-cols-2">
                    <p class="rounded-md border border-dashed border-kp-border bg-kp-surface-muted px-3 py-2 text-[11px] text-kp-muted sm:col-span-2">
                        Ninguna operación seleccionada.
                    </p>
                </div>
            </section>

            <x-forms.textarea
                id="motivoAsignacion"
                name="motivo"
                label="Motivo de la solicitud"
                rows="3"
                placeholder="Describe el motivo por el cual solicitas la asignación de esta(s) operación(es)..."
                required
            >{{ old('motivo') }}</x-forms.textarea>

            <div id="asignacionOpsHidden"></div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-kp-border px-5 py-4">
            <p id="asignacionFooterOps" class="text-[10.5px] text-kp-muted">0 operaciones seleccionadas</p>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" data-modal-close>
                    Cancelar
                </x-ui.button>
                <x-ui.button id="btnSubmitAsignacion" type="submit" variant="primary">
                    Solicitar asignación
                </x-ui.button>
            </div>
        </div>
    </form>
</x-ui.modal>
