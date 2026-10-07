@props([
    'label' => 'Opciones',
])

<details {{ $attributes->merge(['class' => 'group relative inline-block text-left']) }}>
    <summary class="inline-flex cursor-pointer list-none items-center justify-center gap-2 rounded-sm border border-kp-border bg-white px-2.5 py-1.5 text-xs font-semibold text-kp-ink kp-focus hover:bg-slate-50">
        {{ $label }}
        <span class="text-kp-muted transition group-open:rotate-180">v</span>
    </summary>
    <div class="absolute right-0 z-30 mt-1 min-w-48 overflow-hidden rounded-sm border border-kp-border bg-white p-1 shadow-md">
        {{ $slot }}
    </div>
</details>
