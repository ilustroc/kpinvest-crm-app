@props([
    'title',
    'subtitle' => null,
    'accent' => 'primary',
])

@php
    $accents = [
        'primary' => 'bg-kp-primary',
        'success' => 'bg-kp-success',
        'warning' => 'bg-amber-500',
        'neutral' => 'bg-slate-300',
    ];
@endphp

<section {{ $attributes->merge(['class' => 'min-w-0 max-w-full overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]']) }}>
    <div class="flex flex-col gap-3 border-b border-kp-border px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="h-4 w-1 shrink-0 rounded-full {{ $accents[$accent] ?? $accents['primary'] }}" aria-hidden="true"></span>
            <div class="min-w-0">
                <h2 class="truncate text-[15px] font-semibold text-kp-ink">{{ $title }}</h2>
                @if($subtitle)
                    <p class="mt-0.5 text-[11px] text-kp-muted">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                {{ $actions }}
            </div>
        @endisset
    </div>

    <div class="min-w-0 max-w-full">
        {{ $slot }}
    </div>
</section>
