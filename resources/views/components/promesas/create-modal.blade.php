@props([
    'dni',
])

<x-ui.modal
    id="modalPropuesta"
    title="Generar propuesta"
    subtitle="Completa los datos de la propuesta."
    max-width="3xl"
>
    <form
        method="POST"
        action="{{ route('clientes.promesas.store', $dni) }}"
        id="formPropuesta"
        data-once
        class="flex max-h-[calc(100dvh-10rem)] flex-col"
    >
        @csrf

        <div class="kp-scrollbar min-h-0 flex-1 overflow-y-auto">
            <div class="space-y-4 px-5 py-4">
                <x-promesas.promise-type-fields />
                <div id="opsHidden"></div>
            </div>
        </div>

        <div class="flex shrink-0 justify-end border-t border-kp-border bg-white px-5 py-3.5">
            <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
                <x-ui.button type="button" variant="secondary" data-modal-close class="flex-1 sm:flex-none">
                    Cancelar
                </x-ui.button>
                <x-ui.button type="submit" class="flex-1 sm:flex-none">
                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span class="sm:hidden">Guardar</span>
                    <span class="hidden sm:inline">Guardar propuesta</span>
                </x-ui.button>
            </div>
        </div>
    </form>
</x-ui.modal>
