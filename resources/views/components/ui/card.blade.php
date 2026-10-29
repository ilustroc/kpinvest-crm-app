@props([
    'title' => null,
    'subtitle' => null,
])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]']) }}>
    @if($title || $subtitle)
        <header class="border-b border-kp-border px-4 py-3.5">
            @if($title)
                <h2 class="text-[15px] font-semibold text-kp-ink">{{ $title }}</h2>
            @endif
            @if($subtitle)
                <p class="mt-0.5 text-xs text-kp-muted">{{ $subtitle }}</p>
            @endif
        </header>
    @endif
    <div class="p-4">
        {{ $slot }}
    </div>
</section>
