@props([
    'id',
    'title' => null,
    'subtitle' => null,
    'maxWidth' => 'md',
])

@php
    $widths = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
    ];
@endphp

<div id="{{ $id }}"
     class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/50 p-4"
     data-modal
     aria-hidden="true">
    <div class="mx-auto mt-16 {{ $widths[$maxWidth] ?? $widths['md'] }} rounded-lg bg-white shadow-xl">
        @if($title || $subtitle)
            <div class="border-b border-kp-border px-5 py-4">
                @if($title)
                    <h3 class="text-base font-bold text-kp-ink">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="mt-1 text-sm text-kp-muted">{{ $subtitle }}</p>
                @endif
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
