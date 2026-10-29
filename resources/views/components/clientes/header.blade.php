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
    $cuentas = collect($cuentas ?? []);
    $pagos = collect($pagos ?? []);
    $promesas = collect($promesas ?? []);

    $pendingPromises = $promesas->filter(function ($promise) {
        $status = strtolower((string) ($promise->workflow_estado ?? 'pendiente'));

        return str_contains($status, 'pend') || str_contains($status, 'pre');
    });

    $pendingPromiseTotal = (float) $pendingPromises->sum(fn ($promise) => (float) ($promise->monto ?? 0));
    $hasPendingBalance = (float) $totDeuda > 0.009;
    $statusLabel = $hasPendingBalance ? 'Con saldo pendiente' : 'Sin deuda';
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
    $summaryHint = $accountCurrencies->count() > 1
        ? 'Monedas mixtas: revisa por operación'
        : 'Suma de las operaciones';
@endphp

<section {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]']) }}>
    <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-kp-navy via-kp-primary to-kp-primary-dark" aria-hidden="true"></div>

    <div class="p-4 pt-5 sm:p-5 sm:pt-6 lg:p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-kp-muted">Ficha del cliente</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $hasPendingBalance ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-kp-primary-border bg-kp-success-soft text-kp-success-ink' }}">
                        <span class="size-1.5 rounded-full {{ $hasPendingBalance ? 'bg-amber-500' : 'bg-kp-success' }}" aria-hidden="true"></span>
                        {{ $statusLabel }}
                    </span>
                </div>

                <h1 class="mt-1.5 text-[23px] font-semibold uppercase leading-tight tracking-tight text-kp-ink sm:text-[27px]">
                    {{ $titular }}
                </h1>

                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[12px] text-kp-muted">
                    <span class="inline-flex items-center gap-1.5 font-medium text-slate-600">
                        <svg class="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                        </svg>
                        DNI {{ $dni }}
                    </span>
                    <span>{{ $accountCount }} {{ $accountCount === 1 ? 'cuenta' : 'cuentas' }}</span>
                    <span>{{ $operationCount }} {{ $operationCount === 1 ? 'operación' : 'operaciones' }}</span>
                    <span>{{ $paymentCount }} {{ $paymentCount === 1 ? 'pago registrado' : 'pagos registrados' }}</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:shrink-0">
                <a href="#cuentas" class="inline-flex items-center gap-2 rounded-lg border border-kp-border bg-white px-3 py-2 text-[12px] font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 kp-focus">
                    <svg class="size-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                    Ver operaciones
                </a>

                @if($ccdDocs->isNotEmpty())
                    <a href="#documentos" class="inline-flex items-center gap-2 rounded-lg border border-kp-border bg-white px-3 py-2 text-[12px] font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 kp-focus">
                        <svg class="size-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        Documentos
                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">{{ $ccdDocs->count() }}</span>
                    </a>
                @endif
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-kp-border pt-4 sm:grid-cols-4">
            <x-clientes.summary-card
                label="Deuda total"
                value="{{ \App\Support\Money::format($totDeuda, $summaryCurrency) }}"
                hint="{{ $summaryHint }}"
                tone="primary"
            />
            <x-clientes.summary-card
                label="Deuda capital"
                value="{{ \App\Support\Money::format($totCapital, $summaryCurrency) }}"
                hint="{{ $summaryHint }}"
                tone="neutral"
            />
            <x-clientes.summary-card
                label="Pagos registrados"
                value="{{ \App\Support\Money::format($totPagos, $summaryCurrency) }}"
                hint="{{ $paymentCount }} {{ $paymentCount === 1 ? 'movimiento' : 'movimientos' }} del DNI"
                tone="success"
            />
            <x-clientes.summary-card
                label="Promesas"
                value="{{ $promesas->count() }}"
                hint="{{ $pendingPromises->count() > 0 ? $pendingPromises->count().' por aprobar · '.\App\Support\Money::format($pendingPromiseTotal, 'PEN') : 'Sin promesas pendientes' }}"
                tone="warning"
            />
        </dl>
    </div>
</section>
