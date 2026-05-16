@props([
    'label',
    'value',
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col justify-center rounded-lg border border-kp-border bg-white px-4 py-3']) }}>
    <div class="text-[10px] font-bold uppercase tracking-wide text-kp-muted">{{ $label }}</div>
    <div class="mt-1 text-lg font-black text-kp-ink">{{ $value }}</div>
    @if($hint)
        <div class="mt-1 text-xs font-semibold text-kp-muted">{{ $hint }}</div>
    @endif
</div>