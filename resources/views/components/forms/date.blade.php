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
        {{ $attributes->merge(['class' => 'h-8 w-full rounded-sm border border-kp-border bg-white px-2.5 py-1.5 text-xs text-kp-ink kp-focus']) }}
    >
</x-forms.field>
