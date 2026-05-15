@props([
    'status' => null,
])

@php
    $normalized = strtolower((string) $status);
    $variant = match (true) {
        str_contains($normalized, 'aprob') && !str_contains($normalized, 'pre') => 'success',
        str_contains($normalized, 'pre') => 'info',
        str_contains($normalized, 'rechaz') => 'danger',
        str_contains($normalized, 'pend') => 'warning',
        default => 'neutral',
    };
    $label = $status ? ucfirst(str_replace('_', ' ', (string) $status)) : 'Pendiente';
@endphp

<x-ui.badge :variant="$variant" {{ $attributes }}>
    {{ trim((string) $slot) === '' ? $label : $slot }}
</x-ui.badge>
