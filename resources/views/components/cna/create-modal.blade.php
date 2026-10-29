@props([
    'dni',
    'titular' => null,
    'autoOpen' => false,
])

@if($autoOpen)
    <div data-modal-auto-open="#modalCna" hidden></div>
@endif

<x-ui.modal
    id="modalCna"
    title="Solicitar Carta de No Adeudo (CNA)"
    subtitle="Revisa la cuenta de deuda, las operaciones incluidas y el pago informado antes de enviar."
    max-width="3xl"
>
    <form method="POST" action="{{ route('clientes.cna.store', $dni) }}" data-once>
        @csrf

        <div class="space-y-4 px-5 py-5">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-kp-border bg-slate-50/60 px-3.5 py-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Cliente</p>
                    <p class="mt-0.5 truncate text-[13px] font-semibold text-kp-ink">{{ $titular ?: 'Titular del DNI' }}</p>
                    <p class="mt-0.5 text-[11px] text-kp-muted">DNI <span class="font-mono">{{ $dni }}</span></p>
                </div>
                <x-ui.badge variant="neutral">Solicitud CNA</x-ui.badge>
            </div>

            <x-cna.debt-selector />

            @if($errors->has('operaciones') || $errors->has('cuenta'))
                <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[11px] font-medium text-red-700">
                    {{ $errors->first('operaciones') ?: $errors->first('cuenta') }}
                </p>
            @endif

            <section class="overflow-hidden rounded-lg border border-kp-border bg-white" aria-labelledby="cnaPagoTitle">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-kp-border bg-kp-surface-muted/70 px-3.5 py-3">
                    <div class="flex min-w-0 items-start gap-2.5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink" aria-hidden="true">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <h4 id="cnaPagoTitle" class="text-[13px] font-semibold text-kp-ink">Datos de pago informado</h4>
                            <p class="mt-0.5 text-[11px] text-kp-muted">Registra la fecha y el importe del pago que sustenta la solicitud.</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-400">Monto</p>
                        <p id="cnaMontoPreview" class="mt-0.5 text-[15px] font-semibold tabular-nums text-kp-ink">S/ 0.00</p>
                    </div>
                </div>
                <div class="grid gap-3 px-3.5 py-3 md:grid-cols-2">
                    <x-forms.date label="Fecha de pago realizado" name="fecha_pago_realizado" value="{{ old('fecha_pago_realizado') }}" required />
                    <x-forms.input
                        id="monto_pagado"
                        label="Monto pagado"
                        name="monto_pagado"
                        type="number"
                        step="0.01"
                        min="0.01"
                        inputmode="decimal"
                        placeholder="0.00"
                        value="{{ old('monto_pagado') }}"
                        required
                    />
                </div>
                <div class="border-t border-kp-border px-3.5 py-2.5 text-[10.5px] text-kp-muted">
                    El importe se muestra con el formato del CRM y no modifica los cálculos de deuda.
                </div>
            </section>

            <x-forms.textarea name="observacion" label="Observación" rows="3" placeholder="Comentario contextual para la revisión">{{ old('observacion') }}</x-forms.textarea>

            <div class="flex gap-2.5 rounded-lg border border-kp-secondary-border bg-kp-secondary-soft/60 px-3.5 py-3">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-kp-secondary-dark" aria-hidden="true">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </span>
                <p class="text-[11px] leading-5 text-slate-600">
                    <span class="font-semibold text-kp-secondary-dark">Documento:</span> el DOCX se genera al aprobar la solicitud. Si la conversión a PDF no está disponible, se conserva el DOCX.
                </p>
            </div>

            <input type="hidden" name="cuenta" id="cnaCuentaInput">
            <div id="cnaOpsHidden"></div>

            @if(old('operaciones'))
                <div id="cnaOldSelection" data-operaciones='@json(old('operaciones'))' hidden></div>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-kp-border px-5 py-4">
            <p class="text-[10.5px] text-kp-muted">
                <span id="cnaFooterOps">—</span>
                <span class="mx-1 text-slate-300">·</span>
                <span id="cnaFooterMonto">S/ 0.00</span>
            </p>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" data-modal-close>
                    Cancelar
                </x-ui.button>
                <x-ui.button type="submit" variant="success">
                    Enviar solicitud
                </x-ui.button>
            </div>
        </div>
    </form>
</x-ui.modal>
