@props([
    'align' => 'left',
])

@php
    $alignClass = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ][$align] ?? 'text-left';
@endphp

<td {{ $attributes->merge(['class' => 'whitespace-nowrap border-t border-kp-border px-3 py-2.5 text-xs text-kp-ink '.$alignClass]) }}>
    {{ $slot }}
</td>
