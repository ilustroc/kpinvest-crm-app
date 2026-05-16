@props([
    'dni',
])

<x-ui.modal id="modalCna" title="Solicitar Carta de No Adeudo (CNA)" max-width="2xl">
    <form method="POST" action="{{ route('clientes.cna.store', $dni) }}" data-once>
        @csrf

        <div class="space-y-4 px-5 py-5">
            <x-cna.debt-selector />

            <div class="grid gap-3 md:grid-cols-2">
                <x-forms.date label="Fecha de pago realizado" name="fecha_pago_realizado" required />
                <x-forms.input label="Monto pagado (S/.)" name="monto_pagado" type="number" step="0.01" min="0.01" required />
            </div>

            <x-forms.textarea name="observacion" label="Observacion" rows="3" placeholder="Comentario contextual" />

            <input type="hidden" name="cuenta" id="cnaCuentaInput">
            <div id="cnaOpsHidden"></div>
        </div>

        <div class="flex justify-end gap-2 border-t border-kp-border px-5 py-4">
            <x-ui.button type="button" variant="secondary" data-modal-close>
                Cancelar
            </x-ui.button>
            <x-ui.button type="submit" variant="success">
                Enviar solicitud
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
