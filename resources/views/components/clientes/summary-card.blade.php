@props([
    'label',
    'value',
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-kp-border bg-white px-4 py-3 shadow-sm']) }}>
    <div class="text-xs font-semibold uppercase tracking-wide text-kp-muted">{{ $label }}</div>
    <div class="mt-1 text-lg font-extrabold text-kp-ink">{{ $value }}</div>
    @if($hint)
        <div class="mt-1 text-xs text-kp-muted">{{ $hint }}</div>
    @endif
</div>
