@props([
    'dni',
    'titular',
    'cuentas',
    'pagos',
    'promesas',
    'advisor' => null,
    'totCapital' => 0,
    'totDeuda' => 0,
    'totPagos' => 0,
])

<section {{ $attributes->merge(['class' => 'kp-section-anchor border border-kp-border bg-white']) }}>
    <div class="grid divide-y divide-kp-border sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-[minmax(260px,1.6fr)_repeat(3,minmax(128px,.72fr))_minmax(160px,.8fr)]">
        <div class="min-w-0 px-3 py-2.5 sm:col-span-2 sm:px-4 lg:col-span-1">
            <h1 class="truncate text-lg font-bold uppercase leading-tight tracking-tight text-kp-ink">{{ $titular }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-kp-muted">
                <span class="font-mono font-semibold text-kp-ink">DNI {{ $dni }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ count($cuentas ?? []) }} cuenta(s)</span>
                <span aria-hidden="true">·</span>
                <span>{{ count($pagos ?? []) }} pago(s)</span>
                <span aria-hidden="true">·</span>
                <span>{{ count($promesas ?? []) }} promesa(s)</span>
            </div>
        </div>

        <div class="px-3 py-2.5">
            <div class="text-[9px] font-bold uppercase tracking-wider text-kp-muted">Deuda capital</div>
            <div class="mt-0.5 text-base font-bold tabular-nums text-kp-ink">S/ {{ number_format((float) $totCapital, 2) }}</div>
        </div>

        <div class="px-3 py-2.5">
            <div class="text-[9px] font-bold uppercase tracking-wider text-kp-muted">Deuda total</div>
            <div class="mt-0.5 text-base font-extrabold tabular-nums text-kp-navy">S/ {{ number_format((float) $totDeuda, 2) }}</div>
        </div>

        <div class="px-3 py-2.5">
            <div class="text-[9px] font-bold uppercase tracking-wider text-kp-muted">Pagos registrados</div>
            <div class="mt-0.5 text-base font-bold tabular-nums text-kp-ink">S/ {{ number_format((float) $totPagos, 2) }}</div>
        </div>

        <div class="min-w-0 px-3 py-2.5">
            <div class="text-[9px] font-bold uppercase tracking-wider text-kp-muted">Asesor responsable</div>
            <div class="mt-0.5 truncate text-xs font-semibold text-kp-ink">{{ $advisor ?: 'Sin asignar' }}</div>
        </div>
    </div>
</section>
