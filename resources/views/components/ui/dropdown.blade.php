@props([
    'label' => 'Opciones',
])

<details {{ $attributes->merge(['class' => 'group relative inline-block text-left']) }}>
    <summary class="inline-flex cursor-pointer list-none items-center justify-center gap-2 rounded-md border border-kp-border bg-white px-3 py-2 text-sm font-semibold text-kp-ink shadow-sm kp-focus hover:bg-slate-50">
        {{ $label }}
        <span class="text-kp-muted transition group-open:rotate-180">v</span>
    </summary>
    <div class="absolute right-0 z-30 mt-2 min-w-48 overflow-hidden rounded-md border border-kp-border bg-white p-1 shadow-lg">
        {{ $slot }}
    </div>
</details>
