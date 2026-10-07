@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $base = 'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-sm border font-semibold leading-none transition kp-focus disabled:pointer-events-none disabled:opacity-50';
    $sizes = [
        'none' => '',
        'sm' => 'min-h-7 px-2 py-1 text-[11px]',
        'md' => 'min-h-8 px-3 py-1.5 text-xs',
        'icon' => 'size-8 p-0 text-xs',
        'icon-sm' => 'size-7 p-0 text-[11px]',
    ];
    $variants = [
        'primary' => 'border-kp-blue bg-kp-blue text-white hover:border-kp-blue-dark hover:bg-kp-blue-dark',
        'secondary' => 'border-kp-border bg-white text-kp-ink hover:border-slate-400 hover:bg-slate-50',
        'danger' => 'border-red-200 bg-red-50 text-red-700 hover:border-red-300 hover:bg-red-100',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100',
        'info' => 'border-blue-200 bg-kp-blue-soft text-kp-blue hover:border-kp-blue hover:bg-blue-100',
        'ghost' => 'border-transparent bg-transparent text-kp-muted hover:bg-slate-100 hover:text-kp-ink',
        'bare' => 'border-transparent bg-transparent text-kp-ink hover:bg-slate-100',
    ];
    $classes = trim($base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']));
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
