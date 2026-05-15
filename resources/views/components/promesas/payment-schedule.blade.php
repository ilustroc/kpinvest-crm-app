<x-ui.modal id="cronModal" title="Cronograma" max-width="3xl">
    <div class="px-5 py-5">
        <x-tables.table>
            <thead>
                <tr>
                    <x-tables.th align="right">#</x-tables.th>
                    <x-tables.th>Fecha de pago</x-tables.th>
                    <x-tables.th align="right">Monto</x-tables.th>
                    <x-tables.th align="center" id="thBalon" class="hidden">Balon</x-tables.th>
                </tr>
            </thead>
            <tbody id="cronTbody"></tbody>
        </x-tables.table>
    </div>
    <div class="flex justify-end border-t border-kp-border px-5 py-4">
        <x-ui.button type="button" variant="secondary" data-modal-close>
            Cerrar
        </x-ui.button>
    </div>
</x-ui.modal>
