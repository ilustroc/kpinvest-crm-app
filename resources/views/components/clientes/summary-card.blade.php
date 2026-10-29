@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'neutral',
])

@php
    $dots = [
        'primary' => 'bg-kp-primary',
        'success' => 'bg-kp-success',
        'warning' => 'bg-amber-500',
        'neutral' => 'bg-slate-300',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <dt class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-kp-muted">
        <span class="size-1.5 shrink-0 rounded-full {{ $dots[$tone] ?? $dots['neutral'] }}" aria-hidden="true"></span>
        {{ $label }}
    </dt>
    <dd class="mt-1.5 truncate text-[19px] font-semibold leading-none tracking-tight text-kp-ink sm:text-[20px]">
        {{ $value }}
    </dd>
    @if($hint)
        <p class="mt-1.5 truncate text-[11px] text-slate-500">{{ $hint }}</p>
    @endif
</div>
