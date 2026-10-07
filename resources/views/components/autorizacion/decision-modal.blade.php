@props([
    'id',
    'formId',
    'titleId',
    'textareaId',
    'title',
    'textareaName' => 'nota_estado',
    'placeholder' => null,
    'required' => false,
    'confirmText' => 'Guardar',
    'variant' => 'primary',
])

<x-ui.modal :id="$id" :title="$title" max-width="md">
    <form id="{{ $formId }}" method="POST" action="#">
        @csrf

        <div class="space-y-2.5 px-3 py-3">
            <h3 id="{{ $titleId }}" class="text-sm font-bold text-kp-ink">{{ $title }}</h3>
            <x-forms.textarea
                :name="$textareaName"
                :id="$textareaId"
                rows="3"
                maxlength="500"
                :placeholder="$placeholder"
                :required="$required"
            />
        </div>

        <div class="flex justify-end gap-2 border-t border-kp-border px-3 py-2.5">
            <x-ui.button type="button" variant="secondary" size="sm" data-modal-close>Cancelar</x-ui.button>
            <x-ui.button type="submit" :variant="$variant" size="sm">{{ $confirmText }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
