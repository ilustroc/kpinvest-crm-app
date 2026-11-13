@props([
    'dni',
    'autoOpen' => false,
])

@if($autoOpen)
    <div data-modal-auto-open="#modalSolicitarAsignacion" hidden></div>
@endif

<x-ui.modal
    id="modalSolicitarAsignacion"
    title="Solicitar asignación de cuenta"
    subtitle="Indica el motivo de la solicitud."
    max-width="lg"
>
    <form id="formSolicitarAsignacion" method="POST" action="{{ route('clientes.asignaciones.store', $dni) }}" data-once>
        @csrf

        <div class="px-5 py-4">
            <x-forms.textarea
                id="motivoAsignacion"
                name="motivo"
                label="Motivo de la solicitud"
                rows="2"
                placeholder="Motivo de la asignación"
                required
            >{{ old('motivo') }}</x-forms.textarea>

            <div id="asignacionOpsHidden"></div>
        </div>

        <div class="flex justify-end border-t border-kp-border px-5 py-3.5">
            <div class="flex w-full justify-end gap-2 sm:w-auto">
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
