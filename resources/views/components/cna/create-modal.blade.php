@props([
    'dni',
    'titular' => null,
    'autoOpen' => false,
])

@if($autoOpen)
    <div data-modal-auto-open="#modalCna" hidden></div>
@endif

<x-ui.modal
    id="modalCna"
    title="Solicitar Carta de No Adeudo (CNA)"
    subtitle="Registra la fecha de pago y el comentario de la solicitud."
    max-width="md"
>
    <form method="POST" action="{{ route('clientes.cna.store', $dni) }}" data-once>
        @csrf

        <div class="space-y-3 px-5 py-5">
            @if($errors->has('operaciones') || $errors->has('cuenta'))
                <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[11px] font-medium text-red-700">
                    {{ $errors->first('operaciones') ?: $errors->first('cuenta') }}
                </p>
            @endif

            <x-forms.date label="Fecha de pago" name="fecha_pago_realizado" value="{{ old('fecha_pago_realizado') }}" required />

            <x-forms.textarea name="observacion" label="Comentario" rows="3" placeholder="Añade un comentario si es necesario">{{ old('observacion') }}</x-forms.textarea>

            <input type="hidden" name="cuenta" id="cnaCuentaInput">
            <div id="cnaOpsHidden"></div>

            @if(old('operaciones'))
                <div id="cnaOldSelection" data-operaciones='@json(old('operaciones'))' hidden></div>
            @endif
        </div>

        <div class="flex justify-end gap-2 border-t border-kp-border px-5 py-4">
            <div class="flex gap-2">
                <x-ui.button type="button" variant="secondary" data-modal-close>
                    Cancelar
                </x-ui.button>
                <x-ui.button type="submit" variant="success">
                    Enviar solicitud
                </x-ui.button>
            </div>
        </div>
    </form>
</x-ui.modal>
