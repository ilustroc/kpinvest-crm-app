@props([
    'title',
    'subtitle' => null,
])

<x-ui.card {{ $attributes }}>
    <div class="-mx-3 -mt-3 mb-2.5 flex min-h-10 flex-col gap-2 border-b border-kp-border bg-slate-50/70 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xs font-bold text-kp-ink">{{ $title }}</h2>
            @if($subtitle)
                <p class="text-[10px] text-kp-muted">{{ $subtitle }}</p>
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
