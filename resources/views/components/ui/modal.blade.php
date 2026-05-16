@props([
    'id',
    'title' => null,
    'subtitle' => null,
    'maxWidth' => 'md',
])

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
        '5xl' => 'sm:max-w-5xl',
    ];
@endphp

<div id="{{ $id }}"
     class="relative z-50 hidden"
     data-modal
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true">
     
    <div class="fixed inset-0 bg-slate-950/50 transition-opacity" aria-hidden="true"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            
            <div class="relative w-full transform rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 {{ $widths[$maxWidth] ?? $widths['md'] }}">
                
                @if($title || $subtitle)
                    <div class="border-b border-kp-border px-5 py-4">
                        @if($title)
                            <h3 class="text-base font-bold text-kp-ink" id="modal-title">{{ $title }}</h3>
                        @endif
                        @if($subtitle)
                            <p class="mt-1 text-sm text-kp-muted">{{ $subtitle }}</p>
                        @endif
                    </div>
                @endif

                {{ $slot }}
                
            </div>
        </div>
    </div>
</div>