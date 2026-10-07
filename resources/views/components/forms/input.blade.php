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
        {{ $attributes->merge(['class' => 'h-8 w-full rounded-sm border border-kp-border bg-white px-2.5 py-1.5 text-xs text-kp-ink transition placeholder:text-slate-400 hover:border-slate-400 focus:border-kp-blue kp-focus']) }}
    >
</x-forms.field>
