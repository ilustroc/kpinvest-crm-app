@props([
    'for' => null,
])

<label @if($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'mb-1 block text-[11px] font-semibold text-slate-700']) }}>
    {{ $slot }}
</label>
