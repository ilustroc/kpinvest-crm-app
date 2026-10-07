@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
])

<label class="block">
    @if($label)
        <span class="mb-1 block text-[11px] font-semibold text-slate-700">{{ $label }}</span>
    @endif
    <input
        type="{{ $type }}"
        @if($name) name="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'h-8 w-full rounded-sm border border-kp-border bg-white px-2.5 py-1.5 text-xs text-kp-ink transition placeholder:text-slate-400 hover:border-slate-400 focus:border-kp-blue kp-focus']) }}
    >
</label>
