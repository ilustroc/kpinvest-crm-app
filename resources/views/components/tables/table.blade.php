@props([
    'flush' => false,
    'minWidth' => null,
    'contained' => false,
])

@php
    $surface = $flush
        ? 'border-0 bg-white'
        : 'rounded-[10px] border border-kp-border bg-white';
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0 max-w-full overflow-hidden '.$surface]) }}>
    <div class="kp-scrollbar max-w-full overflow-x-auto {{ $contained ? 'contain-paint' : '' }}">
        <table class="min-w-full divide-y divide-kp-border text-[13px] {{ $minWidth }}">
            {{ $slot }}
        </table>
    </div>
</div>
