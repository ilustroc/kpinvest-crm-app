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
        {{ $attributes->merge(['class' => 'w-full rounded-sm border border-kp-border bg-white px-2.5 py-1.5 text-xs text-kp-ink transition placeholder:text-slate-400 hover:border-slate-400 focus:border-kp-blue kp-focus']) }}
    >{{ $slot }}</textarea>
</x-forms.field>
