@props([
    'cna' => null,
    'mode' => 'compact',
])

@php
    $workflow = strtolower((string) ($cna?->workflow_estado ?? ''));
    $isApproved = $cna && str_contains($workflow, 'aprob') && ! str_contains($workflow, 'pre');
    $isRejected = $cna && str_contains($workflow, 'rechaz');
    $isPending = $cna && ! $isApproved && ! $isRejected;

    $docxPath = $cna?->docx_path;
    $pdfPath = $cna?->pdf_path;
    $docxExists = $docxPath ? \Illuminate\Support\Facades\Storage::exists($docxPath) : false;
    $pdfExists = $pdfPath ? \Illuminate\Support\Facades\Storage::exists($pdfPath) : false;
    $hasFile = $docxExists || $pdfExists;
    $hasMissingPath = ($docxPath && ! $docxExists) || ($pdfPath && ! $pdfExists);
    $docTitleId = 'cnaDocTitle-'.($cna?->id ?: 'none');

    if (! $cna) {
        $state = 'none';
        $label = 'Sin CNA';
        $variant = 'neutral';
        $description = 'No hay una solicitud CNA registrada.';
    } elseif ($isRejected) {
        $state = 'rejected';
        $label = 'No aplica';
        $variant = 'neutral';
        $description = 'La solicitud fue rechazada; no se generan documentos.';
    } elseif ($isPending) {
        $state = 'pending';
        $label = 'Al aprobar';
        $variant = 'warning';
        $description = 'El documento se genera cuando la solicitud sea aprobada.';
    } elseif ($hasFile) {
        $state = 'available';
        $label = 'Disponible';
        $variant = 'success';
        $description = $pdfExists
            ? 'DOCX y PDF disponibles para descarga.'
            : 'DOCX disponible. El PDF no está disponible.';
    } elseif ($hasMissingPath) {
        $state = 'missing';
        $label = 'No encontrado';
        $variant = 'danger';
        $description = 'La solicitud está aprobada, pero el archivo registrado no se encontró en el almacenamiento.';
    } else {
        $state = 'not_generated';
        $label = 'Sin archivo';
        $variant = 'warning';
        $description = 'La solicitud está aprobada, pero no hay un documento generado. Revisa la generación antes de descargar.';
    }
@endphp

@if($mode === 'badge')
    <x-ui.badge :variant="$variant" :title="$description">{{ $label }}</x-ui.badge>
@elseif($mode === 'compact')
    @if($state === 'available')
        @php
            $downloadIsPdf = $pdfExists;
            $downloadFormat = $downloadIsPdf ? 'PDF' : 'DOCX';
            $downloadUrl = $downloadIsPdf ? route('cna.pdf', $cna->id) : route('cna.docx', $cna->id);
            $downloadClasses = $downloadIsPdf
                ? 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100'
                : 'border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100';
        @endphp
        <a
            href="{{ $downloadUrl }}"
            target="_blank"
            rel="noopener"
            class="kp-focus inline-flex size-8 items-center justify-center rounded-md border transition-colors {{ $downloadClasses }}"
            aria-label="Descargar CNA {{ $cna->nro_carta ?: $cna->id }} en {{ $downloadFormat }}"
            title="Descargar {{ $downloadFormat }}"
            data-cna-download="{{ $downloadFormat }}"
        >
            @if($downloadIsPdf)
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">
                    <path d="M14 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 19.5V8.25L14 2.25Z" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M14 2.25v6h5.5" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M8 14.25h8M8 17.25h5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            @else
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">
                    <path d="M14 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 19.5V8.25L14 2.25Z" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M14 2.25v6h5.5" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="m8 14 1.5 4 2.5-4 2.5 4 1.5-4" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            @endif
        </a>
    @elseif($state === 'pending')
        <span class="inline-flex items-center gap-1 text-[10.5px] font-medium text-kp-muted" title="{{ $description }}">
            <svg class="size-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            Al aprobar
        </span>
    @elseif($state === 'not_generated' || $state === 'missing')
        <span
            class="inline-flex size-8 items-center justify-center rounded-md border border-kp-border bg-kp-surface-muted text-slate-400"
            title="Sin documento disponible"
            aria-label="Sin documento disponible"
        >
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">
                <path d="M14 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 19.5V8.25L14 2.25Z" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M14 2.25v6h5.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="m8.25 20.25 7.5-16.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
    @else
        <x-ui.badge :variant="$variant" :title="$description">{{ $label }}</x-ui.badge>
    @endif
@else
    <section class="overflow-hidden rounded-lg border border-kp-border bg-white" aria-labelledby="{{ $docTitleId }}">
        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-kp-border bg-kp-surface-muted/70 px-3.5 py-3">
            <div class="flex min-w-0 items-start gap-2.5">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink" aria-hidden="true">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h4 id="{{ $docTitleId }}" class="text-[13px] font-semibold text-kp-ink">Documento de la CNA</h4>
                    <p class="mt-0.5 text-[11px] leading-5 text-kp-muted">{{ $description }}</p>
                </div>
            </div>
            <x-ui.badge :variant="$variant" :title="$description">{{ $label }}</x-ui.badge>
        </div>

        <div class="flex flex-wrap items-center gap-2 px-3.5 py-3">
            @if($pdfExists)
                <x-ui.button :href="route('cna.pdf', $cna->id)" target="_blank" rel="noopener" size="sm" variant="secondary" title="Descargar PDF">
                    PDF
                </x-ui.button>
            @endif
            @if($docxExists)
                <x-ui.button :href="route('cna.docx', $cna->id)" target="_blank" rel="noopener" size="sm" variant="secondary" title="Descargar DOCX">
                    DOCX
                </x-ui.button>
            @endif

            @if($state === 'available' && $docxExists && ! $pdfExists)
                <span class="text-[11px] text-kp-muted">PDF no disponible; se conserva el DOCX.</span>
            @elseif($state === 'available' && $pdfExists && ! $docxExists)
                <span class="text-[11px] text-kp-muted">DOCX no disponible; se conserva el PDF.</span>
            @elseif($state === 'pending')
                <span class="text-[11px] text-kp-muted">Disponible después de la aprobación.</span>
            @elseif($state === 'not_generated' || $state === 'missing')
                <span class="text-[11px] text-kp-muted">No se muestran descargas porque no hay un archivo válido.</span>
            @elseif($state === 'rejected')
                <span class="text-[11px] text-kp-muted">No se generan documentos para solicitudes rechazadas.</span>
            @elseif($state === 'none')
                <span class="text-[11px] text-kp-muted">Solicita una CNA para habilitar la generación documental.</span>
            @endif
        </div>
    </section>
@endif
