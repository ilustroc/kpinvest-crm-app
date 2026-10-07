@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $base = 'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-md border font-semibold leading-none transition kp-focus disabled:pointer-events-none disabled:opacity-50';
    $sizes = [
        'none' => '',
        'sm' => 'min-h-8 px-2.5 py-1.5 text-xs',
        'md' => 'min-h-9 px-3.5 py-2 text-sm',
        'icon' => 'size-9 p-0 text-sm',
        'icon-sm' => 'size-8 p-0 text-xs',
    ];
    $variants = [
        'primary' => 'border-kp-green bg-kp-green text-white shadow-sm hover:border-kp-green-dark hover:bg-kp-green-dark',
        'secondary' => 'border-kp-border bg-white text-kp-ink shadow-sm hover:border-slate-300 hover:bg-slate-50',
        'danger' => 'border-red-200 bg-red-50 text-red-700 hover:border-red-300 hover:bg-red-100',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100',
        'info' => 'border-blue-200 bg-blue-50 text-blue-700 hover:border-blue-300 hover:bg-blue-100',
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
