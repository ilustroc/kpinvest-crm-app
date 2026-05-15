@props([
    'title' => 'Sin datos',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-md border border-dashed border-kp-border bg-slate-50 px-4 py-8 text-center']) }}>
    <div class="text-sm font-bold text-kp-ink">{{ $title }}</div>
    @if($message)
        <div class="mt-1 text-sm text-kp-muted">{{ $message }}</div>
    @endif
    @if(trim($slot) !== '')
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
