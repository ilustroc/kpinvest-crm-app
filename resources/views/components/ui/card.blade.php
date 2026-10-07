@props([
    'title' => null,
    'subtitle' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-sm border border-kp-border bg-white']) }}>
    @if($title || $subtitle)
        <header class="flex min-h-10 flex-col justify-center border-b border-kp-border bg-slate-50/70 px-3 py-2">
            @if($title)
                <h2 class="text-xs font-bold leading-5 text-kp-ink">{{ $title }}</h2>
            @endif
            @if($subtitle)
                <p class="text-[11px] text-kp-muted">{{ $subtitle }}</p>
            @endif
        </header>
    @endif
    <div class="p-3">
        {{ $slot }}
    </div>
</section>
