@props([
    'label' => null,
    'name' => null,
])

<x-forms.field :label="$label" :name="$name">
    <input
        type="date"
        @if($name) id="{{ $name }}" name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus']) }}
    >
</x-forms.field>
