@props([
    'variant' => 'neutral',
])

@php
    $variants = [
        'neutral' => 'border-slate-200 bg-slate-50 text-slate-600',
        'primary' => 'border-kp-primary-border bg-kp-primary-soft text-kp-primary-ink',
        'success' => 'border-kp-primary-border bg-kp-success-soft text-kp-success-ink',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'info' => 'border-kp-secondary-border bg-kp-secondary-soft text-kp-secondary-dark',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.04em] '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
