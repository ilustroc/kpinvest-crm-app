@props([
    'label' => null,
    'name' => null,
    'for' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if($label)
        <x-forms.label :for="$for ?: $name">{{ $label }}</x-forms.label>
    @endif

    {{ $slot }}

    @if($name)
        <x-forms.error :name="$name" />
    @endif
</div>
