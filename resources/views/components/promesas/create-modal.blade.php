@props([
    'dni',
    'titular' => null,
])

<x-ui.modal
    id="modalPropuesta"
    title="Generar propuesta"
    subtitle="Completa las condiciones y revisa el cronograma antes de guardar."
    max-width="4xl"
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
            <div class="space-y-5 px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-kp-border bg-kp-surface-muted px-3.5 py-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink" aria-hidden="true">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-semibold text-kp-ink">{{ $titular ?: 'Cliente' }}</p>
                            <p class="mt-0.5 text-[11px] text-kp-muted">DNI {{ $dni }} · Propuesta de pago</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">Tipo actual</span>
                        <x-ui.badge id="modalTipoTag" variant="info">Convenio</x-ui.badge>
                    </div>
                </div>

                <x-promesas.promise-summary />
                <x-promesas.promise-type-fields />
            </div>
        </div>

        <div class="flex shrink-0 flex-col gap-3 border-t border-kp-border bg-white px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[11px] text-kp-muted">
                Los campos con <span class="font-semibold text-kp-ink">*</span> son obligatorios. Importes en soles (PEN).
            </p>
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
