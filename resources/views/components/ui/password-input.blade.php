@props([
    'label' => null,
    'name' => 'password',
    'placeholder' => null,
    'required' => false,
    'minlength' => 6,
])

<div data-password-field class="block">
    <label class="block">
        @if($label)
            <span class="mb-1.5 block text-xs font-semibold text-slate-600">{{ $label }}</span>
        @endif
        <div class="relative">
            <input
                type="password"
                name="{{ $name }}"
                @if($placeholder) placeholder="{{ $placeholder }}" @endif
                @if($minlength) minlength="{{ $minlength }}" @endif
                @if($required) required @endif
                {{ $attributes->merge(['class' => 'h-9 w-full rounded-lg border border-kp-border bg-white pl-3 pr-10 text-[13px] text-kp-ink shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition placeholder:text-slate-400 hover:border-slate-300 kp-focus disabled:bg-slate-50 disabled:text-slate-500']) }}
            >
            <button
                type="button"
                data-password-toggle
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 transition kp-focus"
                aria-label="Mostrar contraseña"
                title="Mostrar contraseña"
            >
                <svg class="size-4 eye-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <svg class="size-4 eye-off-icon hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
            </button>
        </div>
    </label>
</div>
