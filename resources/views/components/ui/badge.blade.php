@props([
    'variant' => 'neutral',
])

@php
    $variants = [
        'neutral' => 'border-slate-200 bg-slate-50 text-slate-700',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold uppercase tracking-wide '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
