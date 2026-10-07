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

<th scope="col" {{ $attributes->merge(['class' => 'whitespace-nowrap bg-slate-100/80 px-3 py-2.5 text-[10px] font-bold uppercase tracking-[0.07em] text-slate-600 '.$alignClass]) }}>
    {{ $slot }}
</th>
