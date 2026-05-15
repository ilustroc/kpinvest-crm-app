@props([
    'label' => null,
    'name' => null,
])

<label class="block">
    @if($label)
        <span class="mb-1 block text-sm font-semibold text-kp-ink">{{ $label }}</span>
    @endif
    <select
        @if($name) name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus']) }}
    >
        {{ $slot }}
    </select>
</label>
