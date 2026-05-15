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

<x-ui.modal :id="$id" :title="$title" max-width="lg">
    <form id="{{ $formId }}" method="POST" action="#">
        @csrf

        <div class="space-y-4 px-5 py-4">
            <h3 id="{{ $titleId }}" class="text-base font-bold text-kp-ink">{{ $title }}</h3>
            <x-forms.textarea
                :name="$textareaName"
                :id="$textareaId"
                rows="5"
                maxlength="500"
                :placeholder="$placeholder"
                :required="$required"
            />
        </div>

        <div class="flex justify-end gap-2 border-t border-kp-border px-5 py-4">
            <x-ui.button type="button" variant="secondary" data-modal-close>Cancelar</x-ui.button>
            <x-ui.button type="submit" :variant="$variant">{{ $confirmText }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
