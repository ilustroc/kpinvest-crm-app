@props([
    'label' => null,
    'name' => null,
])

<label class="block space-y-1">
    @if($label)
        <span class="block text-sm font-semibold text-kp-ink">{{ $label }}</span>
    @endif
    
    <div class="relative">
        <select
            @if($name) name="{{ $name }}" @endif
            {{ $attributes->merge(['class' => 'w-full appearance-none rounded-md border border-kp-border bg-white py-2 pl-3 pr-10 text-sm font-semibold text-kp-ink shadow-sm transition hover:bg-slate-50 kp-focus']) }}
        >
            {{ $slot }}
        </select>
        
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-kp-muted">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </div>
    </div>
</label>
