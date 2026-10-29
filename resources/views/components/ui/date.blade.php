@props([
    'label' => null,
    'name' => null,
    'type' => 'date',
])

<label class="block">
    @if($label)
        <span class="mb-1.5 block text-xs font-semibold text-slate-600">{{ $label }}</span>
    @endif
    <input
        type="{{ $type }}"
        @if($name) name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'h-9 w-full rounded-lg border border-kp-border bg-white px-3 text-[13px] text-kp-ink shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition hover:border-slate-300 kp-focus']) }}
    >
</label>
