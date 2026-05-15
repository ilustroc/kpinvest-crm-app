@props([
    'label',
    'id' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-md border border-kp-border bg-slate-50 px-3 py-2']) }}>
    <dt class="text-xs font-bold uppercase tracking-wide text-kp-muted">{{ $label }}</dt>
    <dd @if($id) id="{{ $id }}" @endif class="mt-1 text-sm font-semibold text-kp-ink">
        {{ $slot->isEmpty() ? '-' : $slot }}
    </dd>
</div>
