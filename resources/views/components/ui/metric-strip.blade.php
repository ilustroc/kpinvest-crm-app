@props([
    'items' => [],
])

<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-px overflow-hidden rounded-[10px] border border-kp-border bg-kp-border shadow-[0_1px_2px_rgba(16,24,40,0.04)] sm:grid-cols-4']) }}>
    @foreach ($items as $item)
        <div class="min-w-0 bg-white px-4 py-3">
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">{{ $item['label'] ?? '' }}</p>
            <p class="mt-1.5 truncate text-[19px] font-semibold leading-none tracking-tight text-kp-ink tabular-nums" @if(!empty($item['data'])) data-summary="{{ $item['data'] }}" @endif>
                {{ $item['value'] ?? '-' }}
            </p>
            @if (!empty($item['hint']))
                <p class="mt-1 truncate text-[10.5px] text-slate-500">{{ $item['hint'] }}</p>
            @endif
        </div>
    @endforeach
</div>
