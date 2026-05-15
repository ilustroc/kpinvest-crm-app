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

<th scope="col" {{ $attributes->merge(['class' => 'whitespace-nowrap bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wide text-kp-muted '.$alignClass]) }}>
    {{ $slot }}
</th>
