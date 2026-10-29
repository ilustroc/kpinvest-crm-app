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

@php
    $tones = [
        'primary' => [
            'panel' => 'border-kp-primary-border bg-kp-primary-soft',
            'icon' => 'bg-white text-kp-primary-ink',
            'hint' => 'La solicitud avanzará a la siguiente etapa del flujo de autorización.',
            'path' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        ],
        'success' => [
            'panel' => 'border-kp-primary-border bg-kp-success-soft',
            'icon' => 'bg-white text-kp-success-ink',
            'hint' => 'La aprobación quedará registrada con tu usuario y la fecha de la operación.',
            'path' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        ],
        'danger' => [
            'panel' => 'border-red-200 bg-red-50',
            'icon' => 'bg-white text-red-700',
            'hint' => 'El rechazo quedará registrado con tu usuario. El motivo es obligatorio.',
            'path' => 'M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.29 3.86 2.82 17.5A1.5 1.5 0 0 0 4.12 19.75h15.76a1.5 1.5 0 0 0 1.3-2.25L13.71 3.86a1.5 1.5 0 0 0-2.62 0Z',
        ],
    ];

    $tone = $tones[$variant] ?? $tones['primary'];
@endphp

<x-ui.modal :id="$id" :title="$title" :title-id="$titleId" max-width="lg">
    <form id="{{ $formId }}" method="POST" action="#">
        @csrf

        <div class="space-y-4 px-5 py-4">
            <div class="flex gap-3 rounded-lg border px-3.5 py-3 {{ $tone['panel'] }}">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $tone['icon'] }}">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tone['path'] }}" />
                    </svg>
                </span>
                <p class="self-center text-[12px] font-medium leading-5 text-slate-700">{{ $tone['hint'] }}</p>
            </div>

            <x-forms.textarea
                :name="$textareaName"
                :id="$textareaId"
                :label="$required ? 'Motivo del rechazo' : 'Nota (opcional)'"
                rows="5"
                maxlength="500"
                :placeholder="$placeholder"
                :required="$required"
            />
        </div>

        <div class="flex justify-end gap-2 border-t border-kp-border px-5 py-4">
            <x-ui.button type="button" variant="secondary" data-modal-close>Cancelar</x-ui.button>
            <x-ui.button type="submit" :variant="$variant" data-decision-confirm>{{ $confirmText }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
