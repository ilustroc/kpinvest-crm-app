@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'primary',
])

@php
    $tones = [
        'primary' => 'bg-kp-primary-soft text-kp-primary-ink',
        'success' => 'bg-kp-success-soft text-kp-success-ink',
        'warning' => 'bg-amber-50 text-amber-700',
        'neutral' => 'bg-slate-100 text-slate-600',
    ];
@endphp

<article {{ $attributes->merge(['class' => 'flex min-w-0 items-start justify-between gap-3 rounded-[10px] border border-kp-border bg-white px-4 py-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)]']) }}>
    <div class="min-w-0">
        <p class="truncate text-xs font-medium text-kp-muted">{{ $label }}</p>
        <p class="mt-2 text-[25px] font-semibold leading-none tracking-tight text-kp-ink">{{ $value }}</p>
        @if($hint)
            <p class="mt-2 truncate text-[11px] text-slate-500">{{ $hint }}</p>
        @endif
    </div>

    @isset($icon)
        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['primary'] }}">
            {{ $icon }}
        </div>
    @endisset
</article>
