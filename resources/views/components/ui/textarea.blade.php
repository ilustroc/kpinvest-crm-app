@props([
    'label' => null,
    'name' => null,
    'rows' => 4,
])

<label class="block">
    @if($label)
        <span class="mb-1 block text-xs font-semibold text-slate-700">{{ $label }}</span>
    @endif
    <textarea
        @if($name) name="{{ $name }}" @endif
        rows="{{ $rows }}"
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-kp-green kp-focus']) }}
    >{{ $slot }}</textarea>
</label>
