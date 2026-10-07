@props([
    'message' => 'Cargando...',
])

<div {{ $attributes->merge(['class' => 'rounded-sm border border-dashed border-kp-border bg-slate-50 px-3 py-5 text-center text-xs font-semibold text-kp-muted']) }}>
    {{ $message }}
</div>
