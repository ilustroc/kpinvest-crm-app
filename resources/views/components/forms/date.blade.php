@props([
    'label' => null,
    'name' => null,
    'id' => null,
])

@php
    $fieldId = $id ?: $name;
@endphp

<x-forms.field :label="$label" :name="$fieldId">
    <input
        type="date"
        @if($fieldId) id="{{ $fieldId }}" @endif
        @if($name) name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus']) }}
    >
</x-forms.field>
