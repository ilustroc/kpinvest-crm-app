@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
])

<x-forms.field :label="$label" :name="$name">
    <input
        type="{{ $type }}"
        @if($name) id="{{ $name }}" name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus placeholder:text-kp-muted']) }}
    >
</x-forms.field>
