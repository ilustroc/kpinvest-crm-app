@props([
    'message' => 'Cargando...',
])

<div {{ $attributes->merge(['class' => 'rounded-md border border-dashed border-kp-border bg-slate-50 px-4 py-8 text-center text-sm font-semibold text-kp-muted']) }}>
    {{ $message }}
</div>
