<div class="grid gap-3 md:grid-cols-3">
    <x-forms.select label="Tipo de propuesta" name="tipo" id="tipoPropuesta" required>
        <optgroup label="Convenios">
            <option value="convenio" data-balon="0">Convenio</option>
            <option value="convenio_balon" data-balon="1">Convenio (cuota balon)</option>
        </optgroup>
        <optgroup label="Otros">
            <option value="cancelacion">Cancelacion</option>
        </optgroup>
    </x-forms.select>

    <x-forms.input
        label="Telefono de contacto"
        name="telefono"
        id="telefonoPropuesta"
        maxlength="30"
        inputmode="tel"
        pattern="^\+?\d[\d\s\-]{5,}$"
        title="Ingresa un telefono valido"
        placeholder="+51 9XXXXXXXX"
        required
    />

    <x-forms.input
        label="Observacion (opcional)"
        name="nota"
        maxlength="500"
        placeholder="Detalle max. 500"
    />
</div>

<div id="formConvenio" class="mt-4 grid gap-3">
    <div class="grid gap-3 md:grid-cols-4">
        <x-forms.input label="Nro cuotas" name="nro_cuotas" type="number" id="cvNro" min="1" step="1" required />
        <x-forms.input label="Monto convenio (S/)" name="monto_convenio" type="number" id="cvTotal" step="0.01" min="0.01" required />
        <x-forms.input label="Monto de cuota (S/)" name="monto_cuota" type="number" id="cvCuota" step="0.01" min="0.01" />

        <div class="space-y-1">
            <x-forms.label for="cvFechaIni">Fecha inicial auto</x-forms.label>
            <input type="date" id="cvFechaIni" class="w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus">
            <div class="text-xs text-kp-muted" id="cvHintDia">Dia de pago: -</div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button type="button" id="cvGen" variant="secondary" size="sm">
            Generar cronograma
        </x-ui.button>
        <span class="text-sm text-kp-muted">Puedes editar fechas y montos despues de generar.</span>
    </div>

    <x-tables.table id="tblCrono">
        <thead>
            <tr>
                <x-tables.th>#</x-tables.th>
                <x-tables.th>Fecha</x-tables.th>
                <x-tables.th>Importe (S/)</x-tables.th>
            </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
            <tr>
                <x-tables.td colspan="2" align="right" class="font-bold">Total cronograma</x-tables.td>
                <x-tables.td>
                    <span id="cvSuma" class="font-bold">0.00</span>
                    <div id="cvErr" class="mt-1 hidden text-xs font-semibold text-red-700">
                        El total del cronograma debe coincidir con el Monto convenio.
                    </div>
                </x-tables.td>
            </tr>
        </tfoot>
    </x-tables.table>

    <div id="cvHidden"></div>
    <input type="hidden" name="cron_balon" id="cronBalon">

    <p class="text-xs text-kp-muted">
        El total del cronograma debe coincidir con el Monto convenio.
        <span class="hidden" id="hintBalon">En cuota balon, la ultima fila se marca como balon.</span>
    </p>
</div>

<div id="formCancelacion" class="mt-4 hidden grid gap-3 md:grid-cols-2">
    <x-forms.date label="Fecha de pago" name="fecha_pago_cancel" />
    <x-forms.input label="Monto (S/)" name="monto_cancel" type="number" step="0.01" min="0.01" />
</div>
