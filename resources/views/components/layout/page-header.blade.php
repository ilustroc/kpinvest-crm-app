@props([
    'title',
    'subtitle' => null,
])

<header {{ $attributes->merge(['class' => 'flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between']) }}>
    <div>
        <h1 class="text-[22px] font-semibold tracking-tight text-kp-ink">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-[13px] text-kp-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</header>
