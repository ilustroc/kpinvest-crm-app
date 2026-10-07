@props([
    'title',
    'subtitle' => null,
])

<header {{ $attributes->merge(['class' => 'flex flex-col gap-2 border border-kp-border bg-white px-3 py-2.5 sm:rounded-sm lg:flex-row lg:items-center lg:justify-between']) }}>
    <div>
        <h1 class="text-base font-bold tracking-tight text-kp-ink">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-[11px] text-kp-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</header>
