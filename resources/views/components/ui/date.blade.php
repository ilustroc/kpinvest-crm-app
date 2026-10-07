@props([
    'label' => null,
    'name' => null,
    'type' => 'date',
])

<label class="block">
    @if($label)
        <span class="mb-1 block text-sm font-semibold text-kp-ink">{{ $label }}</span>
    @endif
    <input
        type="{{ $type }}"
        @if($name) name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'h-8 w-full rounded-sm border border-kp-border bg-white px-2.5 py-1.5 text-xs text-kp-ink kp-focus']) }}
    >
</label>
