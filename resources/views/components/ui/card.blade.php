@props([
    'title' => null,
    'subtitle' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-kp-border bg-white shadow-sm']) }}>
    @if($title || $subtitle)
        <header class="border-b border-kp-border px-4 py-3">
            @if($title)
                <h2 class="text-base font-bold text-kp-ink">{{ $title }}</h2>
            @endif
            @if($subtitle)
                <p class="mt-1 text-sm text-kp-muted">{{ $subtitle }}</p>
            @endif
        </header>
    @endif
    <div class="p-4">
        {{ $slot }}
    </div>
</section>
