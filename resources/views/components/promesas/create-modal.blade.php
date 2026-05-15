@props([
    'dni',
])

<x-ui.modal id="modalPropuesta" title="Generar propuesta" max-width="4xl">
    <form method="POST" action="{{ route('clientes.promesas.store', $dni) }}" id="formPropuesta" data-once>
        @csrf

        <div class="space-y-4 px-5 py-5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-kp-muted">Tipo actual:</span>
                <x-ui.badge id="modalTipoTag" variant="info">Convenio</x-ui.badge>
            </div>

            <x-promesas.promise-summary />
            <x-promesas.promise-type-fields />

            <p class="text-xs text-kp-muted">
                La propuesta se asociara al DNI {{ $dni }} y a las operaciones seleccionadas.
            </p>
        </div>

        <div class="flex justify-end gap-2 border-t border-kp-border px-5 py-4">
            <x-ui.button type="button" variant="secondary" data-modal-close>
                Cancelar
            </x-ui.button>
            <x-ui.button type="submit">
                Guardar propuesta
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
