@props([
    'label' => null,
    'name' => null,
])

<x-forms.field :label="$label" :name="$name">
    <select
        @if($name) id="{{ $name }}" name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus']) }}
    >
        {{ $slot }}
    </select>
</x-forms.field>
