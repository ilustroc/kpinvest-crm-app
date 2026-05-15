@props([
    'name' => null,
])

@php
    $message = $name ? $errors->first($name) : trim($slot);
@endphp

@if($message)
    <p {{ $attributes->merge(['class' => 'mt-1 text-xs font-medium text-red-600']) }}>
        {{ $message }}
    </p>
@endif
