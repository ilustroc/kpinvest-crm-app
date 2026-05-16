@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md font-semibold transition kp-focus disabled:pointer-events-none disabled:opacity-50';
    $sizes = [
        'none' => '',
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'icon' => 'size-9 text-sm',
    ];
    $variants = [
        'primary' => 'bg-kp-green text-white hover:bg-kp-green-dark',
        'secondary' => 'border border-kp-border bg-white text-kp-ink hover:bg-slate-50',
        'danger' => 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100',
        'success' => 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
        'ghost' => 'text-kp-muted hover:bg-slate-100 hover:text-kp-ink',
        'bare' => 'text-kp-ink hover:opacity-70',
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
