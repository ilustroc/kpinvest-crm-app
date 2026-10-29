@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
])

<label class="block">
    @if($label)
        <span class="mb-1.5 block text-xs font-semibold text-slate-600">{{ $label }}</span>
    @endif
    <input
        type="{{ $type }}"
        @if($name) name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'h-9 w-full rounded-lg border border-kp-border bg-white px-3 text-[13px] text-kp-ink shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition placeholder:text-slate-400 hover:border-slate-300 kp-focus disabled:bg-slate-50 disabled:text-slate-500']) }}
    >
</label>
