@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'rows' => 4,
])

<x-forms.field :label="$label" :name="$name">
    <textarea
        @if($id || $name) id="{{ $id ?? $name }}" @endif
        @if($name) name="{{ $name }}" @endif
        rows="{{ $rows }}"
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus placeholder:text-kp-muted']) }}
    >{{ $slot }}</textarea>
</x-forms.field>
