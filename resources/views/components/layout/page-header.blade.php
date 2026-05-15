@props([
    'title',
    'subtitle' => null,
])

<header {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-lg border border-kp-border bg-white px-4 py-4 shadow-sm lg:flex-row lg:items-center lg:justify-between']) }}>
    <div>
        <h1 class="text-xl font-bold text-kp-ink">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-sm text-kp-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</header>
