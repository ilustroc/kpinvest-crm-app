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
    <div class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between">
        
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-2xl font-black uppercase tracking-tight text-kp-ink">
                {{ $titular }}
            </h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm font-semibold text-kp-muted">
                <div class="inline-flex items-center rounded-sm bg-kp-green-soft px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-kp-green-dark">
                    DNI {{ $dni }}
                </div>
                
                <span class="text-slate-300">•</span>
                <span>{{ count($cuentas ?? []) }} cuenta(s)</span>
                
                <span class="text-slate-300">•</span>
                <span>{{ count($pagos ?? []) }} pago(s)</span>
                
                <span class="text-slate-300">•</span>
                <span>{{ count($promesas ?? []) }} promesa(s)</span>
            </div>
        </div>

        <div class="grid w-full grid-cols-2 gap-3 sm:grid-cols-4 xl:w-auto">
            <x-clientes.summary-card label="Deuda capital" value="S/ {{ number_format((float) $totCapital, 2) }}" />
            <x-clientes.summary-card label="Deuda total" value="S/ {{ number_format((float) $totDeuda, 2) }}" />
            <x-clientes.summary-card label="Pagos registrados" value="S/ {{ number_format((float) $totPagos, 2) }}" />

            <div class="flex flex-col justify-center rounded-lg border border-kp-border bg-white px-4 py-3">
                <div class="text-[10px] font-bold uppercase tracking-wide text-kp-muted">Cartas CCD</div>
                
                <div class="mt-1 flex items-center">
                    @if($ccdDocs->isEmpty())
                        <span class="text-base font-black text-kp-ink">Sin cartas</span>
                    @elseif($ccdDocs->count() === 1)
                        @php
                            $d = $ccdDocs->first();
                            $href = $d->link
                                ?: (preg_match('#^https?://#', (string) $d->pdf)
                                    ? $d->pdf
                                    : asset('storage/ccd/'.ltrim((string) $d->pdf, '/')));
                        @endphp
                        @if($href)
                            <a href="{{ $href }}" target="_blank" class="inline-flex w-full items-center justify-center rounded border border-kp-border bg-white px-3 py-1.5 text-xs font-bold text-kp-ink transition-colors hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-kp-green-soft">
                                Ver CCD
                            </a>
                        @else
                            <span class="text-sm font-semibold text-kp-muted">No disp.</span>
                        @endif
                    @else
                        <details class="group relative w-full">
                            <summary class="flex w-full cursor-pointer list-none items-center justify-between rounded border border-kp-border bg-white px-3 py-1.5 text-xs font-bold text-kp-ink transition-colors hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-kp-green-soft">
                                <span>Ver {{ $ccdDocs->count() }}</span>
                                <svg class="size-3 text-kp-muted transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </summary>
                            <div class="absolute right-0 top-full z-20 mt-1 max-h-48 w-48 overflow-y-auto rounded border border-kp-border bg-white p-1">
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
                                        <a href="{{ $href }}" target="_blank" class="block truncate rounded px-2 py-1.5 text-xs font-semibold text-kp-ink transition-colors hover:bg-slate-50">
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
    </div>
</x-ui.card>