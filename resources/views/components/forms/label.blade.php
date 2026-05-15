@props([
    'for' => null,
])

<label @if($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'mb-1 block text-sm font-semibold text-kp-ink']) }}>
    {{ $slot }}
</label>
