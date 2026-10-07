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
@endphp

<div class="space-y-1">
    <x-forms.label>{{ $title }}</x-forms.label>

    <div class="relative" data-dropdown data-multiselect="{{ $name }}" data-title="{{ $title }}" data-empty="{{ $empty }}">
        <button
            type="button"
            class="flex h-8 w-full items-center justify-between gap-2 rounded-sm border border-kp-border bg-white px-2.5 py-1.5 text-left text-xs font-semibold text-kp-ink transition kp-focus hover:bg-slate-50"
            data-dropdown-button
            data-ms-button>
            <span class="truncate">{{ $title }}: {{ $empty }}</span>
            
            <svg class="size-4 shrink-0 text-kp-muted" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div class="absolute left-0 right-0 z-40 mt-1 hidden rounded-sm border border-kp-border bg-white p-2 shadow-md" data-dropdown-panel>
            <input
                type="text"
                class="mb-2 h-8 w-full rounded-sm border border-kp-border px-2.5 py-1.5 text-xs kp-focus"
                placeholder="{{ $placeholder }}"
                data-ms-search>

            <div class="max-h-64 space-y-1 overflow-y-auto" data-ms-list>
                @forelse($options as $option)
                    @php($value = (string) $option)
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-kp-ink transition-colors hover:bg-slate-50 ms-item">
                        <input
                            class="h-4 w-4 rounded-sm border-kp-border text-kp-blue kp-focus"
                            type="checkbox"
                            name="{{ $name }}[]"
                            value="{{ $value }}"
                            @checked(in_array($value, $selected, true))>
                        <span class="min-w-0 flex-1 truncate ms-text">{{ $value }}</span>
                    </label>
                @empty
                    <div class="px-2 py-3 text-sm text-kp-muted">Sin opciones en este rango.</div>
                @endforelse
            </div>

            <div class="mt-2 flex items-center justify-between gap-2 border-t border-kp-border pt-2">
                <button type="button" class="rounded-sm px-2.5 py-1.5 text-[11px] font-semibold text-kp-muted transition hover:bg-slate-100" data-ms-clear>
                    Limpiar
                </button>
                <button type="button" class="rounded-sm bg-kp-blue px-2.5 py-1.5 text-[11px] font-semibold text-white transition hover:bg-kp-blue-dark" data-ms-apply>
                    Aplicar
                </button>
            </div>
        </div>
    </div>
</div>
