<x-ui.modal
    id="cronModal"
    title="Cronograma"
    subtitle="Detalle de cuotas registradas para la propuesta."
    max-width="3xl"
>
    <div class="px-5 py-4">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <x-ui.badge id="cronTypeBadge" variant="info">Propuesta</x-ui.badge>
            <span id="cronCount" class="text-[11px] font-medium text-kp-muted">0 cuotas</span>
            <span class="ml-auto inline-flex items-center gap-1.5 text-[11px] text-kp-muted">
                Total
                <strong id="cronTotal" class="text-[13px] font-semibold tabular-nums text-kp-ink">S/ 0.00</strong>
            </span>
        </div>

        <x-tables.table :contained="true" min-width="min-w-[560px]">
            <thead>
                <tr>
                    <x-tables.th align="right" class="w-14">#</x-tables.th>
                    <x-tables.th>Fecha de pago</x-tables.th>
                    <x-tables.th align="right">Monto</x-tables.th>
                    <x-tables.th align="center" id="thBalon" class="hidden w-28">Cuota</x-tables.th>
                </tr>
            </thead>
            <tbody id="cronTbody"></tbody>
        </x-tables.table>
    </div>

    <div class="flex justify-end border-t border-kp-border bg-white px-5 py-3.5">
        <x-ui.button type="button" variant="secondary" data-modal-close>
            Cerrar
        </x-ui.button>
    </div>
</x-ui.modal>
