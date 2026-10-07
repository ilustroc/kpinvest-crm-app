@props([
    'for' => null,
])

<label @if($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'mb-1 block text-xs font-semibold text-slate-700']) }}>
    {{ $slot }}
</label>
