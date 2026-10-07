@props([
    'dni',
    'titular',
    'cuentas',
    'pagos',
    'promesas',
    'totCapital' => 0,
    'totDeuda' => 0,
    'totPagos' => 0,
])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-kp-border bg-white kp-surface-shadow']) }}>
    <div class="flex flex-col gap-3 border-b border-kp-border px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <div class="mb-1 flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-kp-green-dark">Ficha de cliente</span>
                <span class="h-3 w-px bg-kp-border" aria-hidden="true"></span>
                <span class="font-mono text-xs font-semibold text-kp-muted">DNI {{ $dni }}</span>
            </div>
            <h1 class="truncate text-xl font-bold uppercase tracking-tight text-kp-ink">{{ $titular }}</h1>
        </div>

        <dl class="grid grid-cols-3 divide-x divide-kp-border rounded-md border border-kp-border bg-slate-50 lg:min-w-[330px]">
            <div class="px-3 py-2 text-center">
                <dt class="text-[9px] font-bold uppercase tracking-wider text-kp-muted">Cuentas</dt>
                <dd class="mt-0.5 text-base font-bold tabular-nums text-kp-ink">{{ count($cuentas ?? []) }}</dd>
            </div>
            <div class="px-3 py-2 text-center">
                <dt class="text-[9px] font-bold uppercase tracking-wider text-kp-muted">Pagos</dt>
                <dd class="mt-0.5 text-base font-bold tabular-nums text-kp-ink">{{ count($pagos ?? []) }}</dd>
            </div>
            <div class="px-3 py-2 text-center">
                <dt class="text-[9px] font-bold uppercase tracking-wider text-kp-muted">Promesas</dt>
                <dd class="mt-0.5 text-base font-bold tabular-nums text-kp-ink">{{ count($promesas ?? []) }}</dd>
            </div>
        </dl>
    </div>

    <dl class="grid divide-y divide-kp-border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        <div class="flex items-center justify-between gap-3 px-4 py-3 sm:block">
            <dt class="text-[10px] font-bold uppercase tracking-wider text-kp-muted">Deuda capital</dt>
            <dd class="mt-0.5 text-base font-bold tabular-nums text-kp-ink">S/ {{ number_format((float) $totCapital, 2) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3 px-4 py-3 sm:block">
            <dt class="text-[10px] font-bold uppercase tracking-wider text-kp-muted">Deuda total</dt>
            <dd class="mt-0.5 text-base font-bold tabular-nums text-kp-ink">S/ {{ number_format((float) $totDeuda, 2) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3 border-l-2 !border-l-kp-green px-4 py-3 sm:block">
            <dt class="text-[10px] font-bold uppercase tracking-wider text-kp-muted">Pagos registrados</dt>
            <dd class="mt-0.5 text-base font-bold tabular-nums text-kp-green-dark">S/ {{ number_format((float) $totPagos, 2) }}</dd>
        </div>
    </dl>
</section>
