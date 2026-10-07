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

<th scope="col" {{ $attributes->merge(['class' => 'whitespace-nowrap border-b border-kp-border bg-[#eef1f4] px-2.5 py-2 text-[9px] font-bold uppercase tracking-[0.06em] text-slate-600 '.$alignClass]) }}>
    {{ $slot }}
</th>
