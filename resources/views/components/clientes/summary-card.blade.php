@props([
    'label',
    'value',
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col justify-center rounded-md border border-kp-border bg-white px-3 py-2.5']) }}>
    <div class="text-[10px] font-bold uppercase tracking-wide text-kp-muted">{{ $label }}</div>
    <div class="mt-0.5 text-base font-bold tabular-nums text-kp-ink">{{ $value }}</div>
    @if($hint)
        <div class="mt-1 text-xs font-semibold text-kp-muted">{{ $hint }}</div>
    @endif
</div>
