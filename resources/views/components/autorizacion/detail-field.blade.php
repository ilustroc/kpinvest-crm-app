@props([
    'label',
    'id' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-kp-border bg-white px-3 py-2.5']) }}>
    <dt class="text-[10px] font-semibold uppercase tracking-[0.05em] text-kp-muted">{{ $label }}</dt>
    <dd @if($id) id="{{ $id }}" @endif class="mt-1 break-words text-[13px] font-semibold leading-5 text-kp-ink">
        {{ $slot->isEmpty() ? '—' : $slot }}
    </dd>
</div>
