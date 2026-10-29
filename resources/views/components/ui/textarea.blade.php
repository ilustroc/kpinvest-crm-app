@props([
    'label' => null,
    'name' => null,
    'rows' => 4,
])

<label class="block">
    @if($label)
        <span class="mb-1.5 block text-xs font-semibold text-slate-600">{{ $label }}</span>
    @endif
    <textarea
        @if($name) name="{{ $name }}" @endif
        rows="{{ $rows }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-kp-border bg-white px-3 py-2 text-[13px] text-kp-ink shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition placeholder:text-slate-400 hover:border-slate-300 kp-focus']) }}
    >{{ $slot }}</textarea>
</label>
