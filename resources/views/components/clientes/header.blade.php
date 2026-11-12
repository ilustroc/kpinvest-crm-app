@props([
    'dni',
    'titular',
    'cuentas',
    'pagos',
    'ccdDocs',
    'totCapital' => 0,
    'totDeuda' => 0,
    'totPagos' => 0,
])

@php
    $ccdDocs = collect($ccdDocs ?? []);
    $cuentas = collect($cuentas ?? []);
    $pagos = collect($pagos ?? []);

    $accountCount = $cuentas->pluck('cuenta')->filter(fn ($account) => trim((string) $account) !== '')->unique()->count();
    $operationCount = $cuentas->count();
    $paymentCount = $pagos->count();
    $accountCurrencies = $cuentas
        ->pluck('moneda')
        ->map(fn ($currency) => strtoupper(trim((string) $currency)))
        ->filter()
        ->unique()
        ->values();
    $summaryCurrency = $accountCurrencies->count() === 1
        ? $accountCurrencies->first()
        : ($accountCurrencies->isEmpty() ? 'PEN' : null);
@endphp

<section {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]']) }}>
    <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-kp-navy via-kp-primary to-kp-primary-dark" aria-hidden="true"></div>

    <div class="px-4 pb-3.5 pt-[18px] sm:px-5 sm:pb-4 sm:pt-[22px] lg:px-5">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:items-center lg:gap-0">
            <div class="min-w-0 lg:pr-5">
                <span class="text-[10px] font-semibold uppercase tracking-[0.15em] text-kp-muted">Ficha del cliente</span>

                <h1 class="mt-1 break-words text-[20px] font-semibold uppercase leading-[1.15] tracking-tight text-kp-ink sm:text-[22px] lg:text-[21px]">
                    {{ $titular }}
                </h1>

                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-kp-muted sm:text-[12px]">
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap font-medium text-slate-600">
                        <svg class="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                        </svg>
                        Doc. {{ $dni }}
                    </span>
                    <span class="whitespace-nowrap">{{ $accountCount }} {{ $accountCount === 1 ? 'cuenta' : 'cuentas' }}</span>
                    <span class="whitespace-nowrap">{{ $operationCount }} {{ $operationCount === 1 ? 'operación' : 'operaciones' }}</span>
                    <span class="whitespace-nowrap">{{ $paymentCount }} {{ $paymentCount === 1 ? 'pago registrado' : 'pagos registrados' }}</span>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-kp-border/80 pt-3 sm:gap-x-5 md:grid-cols-4 md:gap-x-0 md:gap-y-0 md:divide-x md:divide-kp-border/80 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.15fr)_minmax(0,1.15fr)_auto] lg:border-l lg:pl-4 lg:pt-0">
                <x-clientes.summary-card
                    label="Deuda total"
                    value="{{ \App\Support\Money::format($totDeuda, $summaryCurrency) }}"
                    tone="primary"
                    class="min-w-0 md:px-3 md:first:pl-0"
                />
                <x-clientes.summary-card
                    label="Deuda capital"
                    value="{{ \App\Support\Money::format($totCapital, $summaryCurrency) }}"
                    tone="neutral"
                    class="min-w-0 md:px-3"
                />
                <x-clientes.summary-card
                    label="Pagos registrados"
                    value="{{ \App\Support\Money::format($totPagos, $summaryCurrency) }}"
                    tone="success"
                    class="min-w-0 md:px-3"
                />

                <div class="min-w-0 md:px-3 md:last:pr-0">
                    <dt class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-kp-muted">
                        <span class="size-1.5 shrink-0 rounded-full bg-kp-navy" aria-hidden="true"></span>
                        CCD
                    </dt>
                    <dd class="mt-1.5 flex min-h-5 flex-wrap items-center gap-1">
                        @if($ccdDocs->isEmpty())
                            <span class="text-[11px] font-medium text-kp-muted">Sin CCD</span>
                        @else
                            @foreach($ccdDocs as $document)
                                @php
                                    $href = route('clientes.ccd.show', [
                                        'numdoc' => $dni,
                                        'ccdCliente' => $document->id,
                                    ]);
                                    $name = filled($document->pdf)
                                        ? pathinfo((string) $document->pdf, PATHINFO_FILENAME)
                                        : (($document->cosecha ? 'CCD '.$document->cosecha : 'CCD').' #'.$document->id);
                                @endphp

                                @if($href)
                                    <a
                                        href="{{ $href }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex size-6 items-center justify-center rounded-md text-kp-primary transition-colors hover:bg-kp-primary/5 hover:text-kp-primary-dark kp-focus"
                                        title="Abrir {{ $name }}"
                                        aria-label="Abrir {{ $name }}"
                                    >
                                        <svg class="size-5 transition-transform hover:scale-110" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </a>
                                @else
                                    <span
                                        class="inline-flex items-center justify-center text-slate-400"
                                        title="{{ $name }}"
                                        aria-label="{{ $name }} (no disponible)"
                                    >
                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>
                                @endif
                            @endforeach
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</section>
