@props([
    'title',
    'count' => 0,
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

<section class="space-y-2">
    <div class="flex items-center gap-2.5">
        <div class="flex size-7 shrink-0 items-center justify-center rounded-md {{ $tones[$tone] ?? $tones['primary'] }}">
            @isset($icon)
                {{ $icon }}
            @endisset
        </div>
        <h3 class="text-[11px] font-semibold uppercase tracking-[0.05em] text-slate-500">{{ $title }}</h3>
        <span class="ml-auto inline-flex min-w-5 items-center justify-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ $count }}</span>
    </div>

    <div class="space-y-1">
        {{ $slot }}
    </div>
</section>
