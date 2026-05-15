@props([
    'title',
    'subtitle' => null,
])

<x-ui.card {{ $attributes }}>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-bold text-kp-ink">{{ $title }}</h2>
            @if($subtitle)
                <p class="mt-1 text-sm text-kp-muted">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>

    {{ $slot }}
</x-ui.card>
