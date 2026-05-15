@props([
    'label' => null,
    'name' => null,
    'rows' => 4,
])

<label class="block">
    @if($label)
        <span class="mb-1 block text-sm font-semibold text-kp-ink">{{ $label }}</span>
    @endif
    <textarea
        @if($name) name="{{ $name }}" @endif
        rows="{{ $rows }}"
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus placeholder:text-kp-muted']) }}
    >{{ $slot }}</textarea>
</label>
