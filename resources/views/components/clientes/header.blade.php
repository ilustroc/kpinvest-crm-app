@props([
    'dni',
    'titular',
    'cuentas',
    'pagos',
    'totCapital' => 0,
    'totDeuda' => 0,
    'totPagos' => 0,
])

@php
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
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:gap-0">
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

            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-kp-border/80 pt-3 sm:gap-x-5 md:grid-cols-3 lg:w-fit lg:grid-cols-[repeat(3,max-content)] lg:justify-self-end lg:gap-x-12 lg:border-t-0 lg:pt-0">
                <x-clientes.summary-card
                    label="Deuda total"
                    value="{{ \App\Support\Money::format($totDeuda, $summaryCurrency) }}"
                    tone="primary"
                    class="min-w-0"
                />
                <x-clientes.summary-card
                    label="Deuda capital"
                    value="{{ \App\Support\Money::format($totCapital, $summaryCurrency) }}"
                    tone="neutral"
                    class="min-w-0"
                />
                <x-clientes.summary-card
                    label="Pagos registrados"
                    value="{{ \App\Support\Money::format($totPagos, $summaryCurrency) }}"
                    tone="success"
                    class="min-w-0"
                />
            </dl>
        </div>
    </div>
</section>
