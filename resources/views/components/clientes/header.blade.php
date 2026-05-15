@props([
    'dni',
    'titular',
    'cuentas',
    'pagos',
    'promesas',
    'ccdDocs',
    'totCapital' => 0,
    'totDeuda' => 0,
    'totPagos' => 0,
])

@php
    $ccdDocs = collect($ccdDocs ?? []);
@endphp

<x-ui.card>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <div class="inline-flex items-center rounded-full border border-kp-green/25 bg-kp-green-soft px-3 py-1 text-xs font-bold uppercase tracking-wide text-kp-green-dark">
                DNI {{ $dni }}
            </div>
            <h1 class="mt-3 truncate text-2xl font-extrabold text-kp-ink">{{ $titular }}</h1>
            <div class="mt-2 flex flex-wrap gap-2 text-sm text-kp-muted">
                <span>{{ count($cuentas ?? []) }} cuenta(s)</span>
                <span>/</span>
                <span>{{ count($pagos ?? []) }} pago(s)</span>
                <span>/</span>
                <span>{{ count($promesas ?? []) }} promesa(s)</span>
            </div>
        </div>

        <div class="grid w-full gap-3 sm:grid-cols-2 lg:w-auto lg:grid-cols-4">
            <x-clientes.summary-card label="Deuda capital" value="S/ {{ number_format((float) $totCapital, 2) }}" />
            <x-clientes.summary-card label="Deuda total" value="S/ {{ number_format((float) $totDeuda, 2) }}" />
            <x-clientes.summary-card label="Pagos registrados" value="S/ {{ number_format((float) $totPagos, 2) }}" />

            <div class="rounded-lg border border-kp-border bg-white px-4 py-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-kp-muted">Cartas CCD</div>
                @if($ccdDocs->isEmpty())
                    <div class="mt-2 text-sm font-semibold text-kp-muted">Sin cartas</div>
                @elseif($ccdDocs->count() === 1)
                    @php
                        $d = $ccdDocs->first();
                        $href = $d->link
                            ?: (preg_match('#^https?://#', (string) $d->pdf)
                                ? $d->pdf
                                : asset('storage/ccd/'.ltrim((string) $d->pdf, '/')));
                    @endphp
                    @if($href)
                        <x-ui.button :href="$href" target="_blank" size="sm" variant="secondary" class="mt-2 w-full">
                            Ver CCD
                        </x-ui.button>
                    @else
                        <div class="mt-2 text-sm font-semibold text-kp-muted">CCD no disponible</div>
                    @endif
                @else
                    <details class="relative mt-2">
                        <summary class="flex cursor-pointer list-none items-center justify-between rounded-md border border-kp-border bg-white px-3 py-2 text-sm font-semibold text-kp-ink kp-focus">
                            Cartas ({{ $ccdDocs->count() }})
                            <span class="text-kp-muted">v</span>
                        </summary>
                        <div class="absolute right-0 z-20 mt-2 max-h-64 w-64 overflow-auto rounded-md border border-kp-border bg-white p-1 shadow-lg">
                            @foreach($ccdDocs as $d)
                                @php
                                    $href = $d->link
                                        ?: (preg_match('#^https?://#', (string) $d->pdf)
                                            ? $d->pdf
                                            : asset('storage/ccd/'.ltrim((string) $d->pdf, '/')));
                                    $name = $d->pdf
                                        ? pathinfo((string) $d->pdf, PATHINFO_FILENAME)
                                        : (($d->cosecha ? 'CCD '.$d->cosecha : 'CCD').' #'.$d->id);
                                @endphp
                                @if($href)
                                    <a href="{{ $href }}" target="_blank" class="block rounded px-3 py-2 text-sm text-kp-ink hover:bg-slate-50">
                                        {{ $name }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        </div>
    </div>
</x-ui.card>
