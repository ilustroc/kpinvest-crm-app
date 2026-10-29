@props([
    'name',
    'title',
    'empty' => 'Todos',
    'options' => [],
    'selected' => [],
    'placeholder' => 'Buscar...',
])

@php
    $selected = collect($selected)->map(fn ($value) => (string) $value)->all();
    $buttonId = 'ms-'.str_replace(['[', ']', '_'], '-', $name).'-button';
    $panelId = 'ms-'.str_replace(['[', ']', '_'], '-', $name).'-panel';
@endphp

<div class="min-w-0 space-y-1">
    <x-forms.label :for="$buttonId">{{ $title }}</x-forms.label>

    <div class="relative" data-dropdown data-multiselect="{{ $name }}" data-title="{{ $title }}" data-empty="{{ $empty }}">
        <button
            type="button"
            id="{{ $buttonId }}"
            class="flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-lg border border-kp-border bg-white px-3 text-left text-[13px] font-medium text-slate-700 shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition hover:border-slate-300 hover:bg-slate-50 kp-focus"
            data-dropdown-button
            data-ms-button
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="{{ $panelId }}"
        >
            <span class="truncate">{{ $title }}: {{ $empty }}</span>

            <svg class="size-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div id="{{ $panelId }}" class="absolute left-0 right-0 z-40 mt-1.5 hidden rounded-xl border border-kp-border bg-white p-2 shadow-xl shadow-slate-900/10" data-dropdown-panel>
            <input
                type="text"
                class="mb-2 h-9 w-full rounded-lg border border-kp-border bg-white px-3 text-[13px] text-kp-ink kp-focus placeholder:text-slate-400"
                placeholder="{{ $placeholder }}"
                aria-label="Buscar en {{ $title }}"
                data-ms-search>

            <div class="kp-scrollbar max-h-60 space-y-0.5 overflow-y-auto" role="group" aria-label="{{ $title }}" data-ms-list>
                @forelse($options as $option)
                    @php($value = (string) $option)
                    <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-[13px] text-kp-ink transition-colors hover:bg-slate-50 ms-item">
                        <input
                            class="size-4 shrink-0 rounded border-kp-border accent-kp-primary kp-focus"
                            type="checkbox"
                            name="{{ $name }}[]"
                            value="{{ $value }}"
                            @checked(in_array($value, $selected, true))>
                        <span class="min-w-0 flex-1 truncate ms-text">{{ $value }}</span>
                    </label>
                @empty
                    <div class="px-2 py-3 text-[13px] text-kp-muted">Sin opciones en este rango.</div>
                @endforelse
            </div>

            <div class="mt-2 flex items-center justify-between gap-2 border-t border-kp-border pt-2">
                <button type="button" class="rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" data-ms-clear>
                    Limpiar
                </button>
                <button type="button" class="rounded-md bg-kp-primary-dark px-3 py-1.5 text-[11px] font-semibold text-white transition hover:bg-kp-primary-darker" data-ms-apply>
                    Aplicar
                </button>
            </div>
        </div>
    </div>
</div>
