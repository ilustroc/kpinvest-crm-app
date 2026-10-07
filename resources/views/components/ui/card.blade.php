@props([
    'title' => null,
    'subtitle' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-kp-border bg-white kp-surface-shadow']) }}>
    @if($title || $subtitle)
        <header class="flex min-h-12 flex-col justify-center border-b border-kp-border px-3 py-2.5 sm:px-4">
            @if($title)
                <h2 class="text-sm font-bold leading-5 text-kp-ink">{{ $title }}</h2>
            @endif
            @if($subtitle)
                <p class="mt-0.5 text-xs text-kp-muted">{{ $subtitle }}</p>
            @endif
        </header>
    @endif
    <div class="p-3 sm:p-4">
        {{ $slot }}
    </div>
</section>
