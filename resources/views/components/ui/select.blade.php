@props([
    'label' => null,
    'name' => null,
])

<label class="block">
    @if($label)
        <span class="mb-1.5 block text-xs font-semibold text-slate-600">{{ $label }}</span>
    @endif

    <div class="relative">
        <select
            @if($name) name="{{ $name }}" @endif
            {{ $attributes->merge(['class' => 'h-9 w-full appearance-none rounded-lg border border-kp-border bg-white pl-3 pr-9 text-[13px] text-kp-ink shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition hover:border-slate-300 kp-focus']) }}
        >
            {{ $slot }}
        </select>

        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </div>
    </div>
</label>
