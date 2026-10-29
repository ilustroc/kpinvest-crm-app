<section class="overflow-hidden rounded-lg border border-kp-border bg-white" aria-labelledby="cnaContextTitle">
    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-kp-border bg-kp-surface-muted/70 px-3.5 py-3">
        <div class="flex min-w-0 items-start gap-2.5">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink" aria-hidden="true">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </span>
            <div class="min-w-0">
                <h4 id="cnaContextTitle" class="text-[13px] font-semibold text-kp-ink">Contexto de la CNA</h4>
                <p class="mt-0.5 text-[11px] text-kp-muted">Se incluyen todas las operaciones vinculadas a la misma cuenta de deuda.</p>
            </div>
        </div>
        <x-ui.badge variant="info">Cuenta de deuda</x-ui.badge>
    </div>

    <dl class="grid grid-cols-1 gap-x-4 gap-y-2.5 px-3.5 py-3 sm:grid-cols-2">
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Cuenta de deuda</dt>
            <dd class="mt-0.5 truncate font-mono text-[12px] font-medium text-slate-700" title="Cuenta de deuda de la operación">
                <span id="cnaCuenta">—</span>
            </dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Entidad</dt>
            <dd class="mt-0.5 truncate text-[12px] font-medium text-slate-700">
                <span id="cnaEntidad">—</span>
            </dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Cosecha / lote</dt>
            <dd class="mt-0.5 truncate text-[12px] font-medium text-slate-700">
                <span id="cnaCosecha">—</span>
            </dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Pagos registrados</dt>
            <dd class="mt-0.5 text-[12px] font-medium text-slate-700">
                <span id="cnaPagosCount">—</span>
            </dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Origen</dt>
            <dd class="mt-0.5 truncate text-[12px] font-medium text-slate-700">
                <span id="cnaOrigen">—</span>
            </dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Serie</dt>
            <dd class="mt-0.5 text-[12px] font-medium text-slate-700">
                <span id="cnaSerie">—</span>
            </dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Plantilla documental</dt>
            <dd class="mt-0.5 text-[11px] font-medium leading-5 text-slate-600 [overflow-wrap:anywhere]">
                <span id="cnaPlantilla" class="font-mono">—</span>
            </dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Total pagado registrado</dt>
            <dd class="mt-0.5 text-[12px] font-medium tabular-nums text-slate-700">
                <span id="cnaPagosSum">—</span>
            </dd>
        </div>
    </dl>

    <div class="border-t border-kp-border px-3.5 py-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Operaciones incluidas</p>
            <span id="cnaOpsCount" class="inline-flex min-w-6 items-center justify-center rounded-md border border-kp-border bg-kp-surface-muted px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">0</span>
        </div>
        <div id="cnaOpsList" class="mt-1.5 grid gap-1.5 sm:grid-cols-2">
            <span class="text-[11px] text-kp-muted">Selecciona una operación en la tabla.</span>
        </div>
        <p class="mt-2 text-[10.5px] leading-5 text-kp-muted">
            La cuenta de recaudo de un pago no forma parte de esta solicitud. Esta pantalla no crea cuentas ni modifica asociaciones históricas. Si la cosecha no está mapeada, el origen se determina por la entidad al guardar.
        </p>
    </div>
</section>
