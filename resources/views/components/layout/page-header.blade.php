@props([
    'title',
    'subtitle' => null,
])

<header {{ $attributes->merge(['class' => 'flex flex-col gap-2 border-b border-kp-border bg-white px-4 py-3 sm:rounded-lg sm:border lg:flex-row lg:items-center lg:justify-between']) }}>
    <div>
        <h1 class="text-lg font-bold tracking-tight text-kp-ink">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-0.5 text-xs text-kp-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</header>
