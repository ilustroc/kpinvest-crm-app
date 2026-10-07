@props([
    'label',
    'id' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-sm border border-kp-border bg-slate-50 px-2.5 py-2']) }}>
    <dt class="text-[9px] font-bold uppercase tracking-wide text-kp-muted">{{ $label }}</dt>
    <dd @if($id) id="{{ $id }}" @endif class="mt-0.5 text-xs font-semibold text-kp-ink">
        {{ $slot->isEmpty() ? '-' : $slot }}
    </dd>
</div>
