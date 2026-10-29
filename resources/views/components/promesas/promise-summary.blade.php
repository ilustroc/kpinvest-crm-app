<section class="rounded-lg border border-kp-border bg-white px-3.5 py-3" aria-labelledby="promesaOpsTitle">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 items-start gap-2.5">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kp-success-soft text-kp-success-ink" aria-hidden="true">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </span>
            <div class="min-w-0">
                <h4 id="promesaOpsTitle" class="text-[13px] font-semibold text-kp-ink">Operaciones incluidas</h4>
                <p class="mt-0.5 text-[11px] text-kp-muted">Selección tomada de la tabla de cuentas de la ficha.</p>
            </div>
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-md border border-kp-primary-border bg-kp-primary-soft px-2 py-1 text-[10.5px] font-semibold text-kp-primary-ink">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
            </svg>
            <span id="opsCount">0</span>
            <span id="opsCountLabel">operaciones</span>
        </span>
    </div>

    <div id="opsResumen" class="mt-3 grid gap-2 sm:grid-cols-2">
        <p class="rounded-md border border-dashed border-kp-border bg-kp-surface-muted px-3 py-2 text-[11px] text-kp-muted">
            Ninguna operación seleccionada.
        </p>
    </div>
    <div id="opsHidden"></div>
</section>
