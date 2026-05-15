@props([
    'variant' => 'info',
])

@php
    $variants = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'danger' => 'border-red-200 bg-red-50 text-red-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 text-sm font-medium '.($variants[$variant] ?? $variants['info'])]) }}>
    {{ $slot }}
</div>
