@props([
    'title' => 'Sin datos',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-[10px] border border-dashed border-kp-border bg-white px-5 py-8 text-center']) }}>
    <div class="text-[13px] font-semibold text-kp-ink">{{ $title }}</div>
    @if($message)
        <div class="mt-1 text-xs text-kp-muted">{{ $message }}</div>
    @endif
    @if(trim($slot) !== '')
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
