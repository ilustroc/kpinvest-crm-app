@props([
    'id',
    'title' => 'Confirmar accion',
    'message' => 'Esta accion requiere confirmacion.',
    'action',
    'method' => 'POST',
    'confirmText' => 'Confirmar',
    'cancelText' => 'Cancelar',
    'variant' => 'danger',
])

<x-ui.modal :id="$id" :title="$title" :subtitle="$message">
    <form method="POST" action="{{ $action }}">
        @csrf
        @if(!in_array(strtoupper($method), ['GET', 'POST'], true))
            @method($method)
        @endif

        <div class="flex justify-between gap-2 px-5 py-4">
            <x-ui.button type="button" variant="secondary" data-modal-close>{{ $cancelText }}</x-ui.button>
            <x-ui.button type="submit" :variant="$variant">{{ $confirmText }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
