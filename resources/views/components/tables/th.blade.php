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

<th scope="col" {{ $attributes->merge(['class' => 'whitespace-nowrap bg-kp-surface-muted px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.04em] text-slate-500 '.$alignClass]) }}>
    {{ $slot }}
</th>
