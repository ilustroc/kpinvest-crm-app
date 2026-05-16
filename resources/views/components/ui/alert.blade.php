@props([
    'variant' => 'info',
    'timeout' => 5000,
])

@php
    $id = 'toast-' . uniqid();
    
    $variants = [
        'success' => 'border-l-emerald-500',
        'danger' => 'border-l-red-500',
        'warning' => 'border-l-amber-500',
        'info' => 'border-l-sky-500',
    ];
@endphp

<div id="{{ $id }}"
     role="alert"
     class="fixed right-4 top-4 z-[100] flex w-full max-w-sm items-center gap-3.5 border border-kp-border border-l-4 bg-white p-4 transition-all duration-300 {{ $variants[$variant] ?? $variants['info'] }}">
    
    <img src="{{ asset('assets/img/logo-superior.png') }}" alt="KP Invest" class="h-7 w-auto shrink-0">

    <div class="min-w-0 flex-1">
        <p class="text-sm font-bold leading-snug text-kp-ink">
            {{ $slot }}
        </p>
    </div>

    <button type="button" 
            onclick="closeToast('{{ $id }}')"
            class="shrink-0 rounded-md p-1 text-kp-muted transition-colors hover:bg-slate-100 hover:text-kp-ink focus:outline-none">
        <span class="sr-only">Cerrar</span>
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<script>
    if (typeof window.closeToast !== 'function') {
        window.closeToast = function(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                setTimeout(() => el.remove(), 300);
            }
        }
    }
    
    setTimeout(() => {
        closeToast('{{ $id }}');
    }, {{ $timeout }});
</script>