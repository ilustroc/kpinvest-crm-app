@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-colors kp-focus disabled:pointer-events-none disabled:opacity-50';
    $sizes = [
        'none' => '',
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-[13px]',
        'icon' => 'size-9 text-sm',
    ];
    $variants = [
        'primary' => 'bg-kp-primary-dark text-white shadow-[0_1px_2px_rgba(0,133,23,0.20)] hover:bg-kp-primary-darker',
        'secondary' => 'border border-kp-border bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50',
        'danger' => 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100',
        'success' => 'border border-kp-primary-border bg-kp-success-soft text-kp-success-ink hover:bg-kp-primary-soft',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        'bare' => 'text-slate-600 hover:text-kp-primary-dark',
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
