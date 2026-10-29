@props([
    'ccdDocs',
])

@php
    $ccdDocs = collect($ccdDocs ?? []);
@endphp

<section
    id="documentos"
    {{ $attributes->merge(['class' => 'min-w-0 max-w-full overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]']) }}
>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3.5">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="h-4 w-1 shrink-0 rounded-full bg-slate-400" aria-hidden="true"></span>
            <div class="min-w-0">
                <h2 class="text-[15px] font-semibold text-kp-ink">Documentos CCD</h2>
                <p class="mt-0.5 text-[11px] text-kp-muted">Cartas de cesión de derechos asociadas al DNI y a su cosecha.</p>
            </div>
        </div>
        <x-ui.badge variant="neutral">{{ $ccdDocs->count() }} {{ $ccdDocs->count() === 1 ? 'documento' : 'documentos' }}</x-ui.badge>
    </div>

    @if($ccdDocs->isEmpty())
        <div class="px-4 py-5">
            <p class="rounded-lg border border-dashed border-kp-border bg-kp-surface-muted px-3 py-4 text-center text-[11px] text-kp-muted">
                Sin documentos CCD registrados para este cliente.
            </p>
        </div>
    @else
        <ul class="grid gap-2 p-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($ccdDocs as $document)
                @php
                    $href = $document->link
                        ?: (preg_match('#^https?://#', (string) $document->pdf)
                            ? $document->pdf
                            : (filled($document->pdf) ? asset('storage/ccd/'.ltrim((string) $document->pdf, '/')) : null));
                    $name = filled($document->pdf)
                        ? pathinfo((string) $document->pdf, PATHINFO_FILENAME)
                        : (($document->cosecha ? 'CCD '.$document->cosecha : 'CCD').' #'.$document->id);
                @endphp

                <li class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-kp-border bg-white px-3 py-2.5">
                    <span class="flex min-w-0 items-center gap-2">
                        <svg class="size-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span class="min-w-0">
                            <span class="block truncate text-[12px] font-semibold text-slate-700" title="{{ $name }}">{{ $name }}</span>
                            <span class="mt-0.5 block truncate text-[10px] text-kp-muted">
                                Cosecha {{ $document->cosecha ?: 'sin cosecha' }}
                            </span>
                        </span>
                    </span>

                    @if($href)
                        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="shrink-0 text-[10.5px] font-semibold text-kp-secondary hover:text-kp-navy kp-focus">Abrir</a>
                    @else
                        <span class="shrink-0 text-[10px] font-medium text-slate-400">No disponible</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
