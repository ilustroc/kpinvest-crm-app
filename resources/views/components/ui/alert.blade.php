@props([
    'variant' => 'info',
    'timeout' => 5000,
])

@php
    $id = 'toast-' . uniqid();
    
    $variants = [
        'success' => 'border-emerald-200 border-l-emerald-500 bg-emerald-50 text-emerald-900',
        'danger' => 'border-red-200 border-l-red-500 bg-red-50 text-red-900',
        'warning' => 'border-amber-200 border-l-amber-500 bg-amber-50 text-amber-900',
        'info' => 'border-blue-200 border-l-blue-500 bg-blue-50 text-blue-900',
    ];
@endphp

<div id="{{ $id }}"
     role="alert"
     data-toast
     data-toast-timeout="{{ (int) $timeout }}"
     class="fixed right-3 top-3 z-[100] flex w-[calc(100%-1.5rem)] max-w-sm items-center gap-3 border border-l-4 p-3 shadow-lg transition-all duration-300 sm:right-4 sm:top-4 {{ $variants[$variant] ?? $variants['info'] }}">
    
    <img src="{{ asset('assets/img/logo-superior.png') }}" alt="KP Invest" class="h-7 w-auto shrink-0">

    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold leading-snug">
            {{ $slot }}
        </p>
    </div>

    <button type="button" 
            data-toast-close
            class="shrink-0 rounded-md p-1 opacity-70 transition-colors hover:bg-white/70 hover:opacity-100 kp-focus">
        <span class="sr-only">Cerrar</span>
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>
