@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
])

@php
    $fieldId = $id ?: $name;
@endphp

<x-forms.field :label="$label" :name="$fieldId">
    <input
        type="{{ $type }}"
        @if($fieldId) id="{{ $fieldId }}" @endif
        @if($name) name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-kp-green kp-focus']) }}
    >
</x-forms.field>
