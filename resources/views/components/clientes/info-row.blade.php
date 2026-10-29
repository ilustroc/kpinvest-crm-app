@props([
    'label',
    'value' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-4 py-2.5']) }}>
    <dt class="shrink-0 text-[11px] font-medium text-kp-muted">{{ $label }}</dt>
    <dd class="min-w-0 text-right text-[12px] font-semibold leading-5 text-kp-ink [overflow-wrap:anywhere]">
        {{ trim((string) $slot) === '' ? ($value ?: '-') : $slot }}
    </dd>
</div>
