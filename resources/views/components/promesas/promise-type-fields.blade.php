<div class="space-y-4">
    <div class="grid gap-4 md:grid-cols-2">
        <x-forms.select label="Tipo de propuesta" name="tipo" id="tipoPropuesta" required>
            <optgroup label="Convenios">
                <option value="convenio" data-balon="0">Convenio</option>
                <option value="convenio_balon" data-balon="1">Convenio (cuota balón)</option>
            </optgroup>
            <optgroup label="Otros">
                <option value="cancelacion">Cancelación</option>
            </optgroup>
        </x-forms.select>

        <x-forms.input
            label="Teléfono de contacto"
            name="telefono"
            id="telefonoPropuesta"
            maxlength="30"
            inputmode="tel"
            pattern="^\+?\d[\d\s\-]{5,}$"
            title="Ingresa un teléfono válido"
            placeholder="+51 9XXXXXXXX"
            required
        />
    </div>

    <x-forms.textarea 
        label="Observación" 
        name="nota" 
        rows="3" 
        maxlength="500"
        placeholder="Comentario contextual" 
    />
</div>

<hr class="my-5 border-kp-border">

<div id="formConvenio" class="space-y-4">
    <h4 class="text-sm font-bold text-kp-ink">Configuración del cronograma</h4>
    
    <div class="rounded-lg border border-kp-border bg-slate-50 p-4">
        <div class="grid gap-4 md:grid-cols-3">
            <x-forms.input label="Monto convenio (S/)" name="monto_convenio" type="number" id="cvTotal" step="0.01" min="0.01" required />
            <x-forms.input label="Nro. cuotas" name="nro_cuotas" type="number" id="cvNro" min="1" step="1" required />
            <x-forms.input label="Monto de cuota (S/)" name="monto_cuota" type="number" id="cvCuota" step="0.01" min="0.01" />
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-3 sm:flex-nowrap">
            <div class="w-full sm:w-auto sm:flex-1">
                <x-forms.label for="cvFechaIni">Fecha inicial auto</x-forms.label>
                <input type="date" id="cvFechaIni" class="mt-1 w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm transition kp-focus">
                <div class="mt-1 text-xs font-semibold text-kp-muted" id="cvHintDia">Día de pago: -</div>
            </div>
            <div class="w-full pb-5 sm:w-auto">
                <x-ui.button type="button" id="cvGen" variant="secondary" class="w-full sm:w-auto">
                    Generar cronograma
                </x-ui.button>
            </div>
        </div>
    </div>

    <p class="text-xs text-kp-muted">Puedes editar fechas e importes específicos en la tabla después de generar.</p>

    <x-tables.table id="tblCrono">
        <thead>
            <tr>
                <x-tables.th class="w-16">#</x-tables.th>
                <x-tables.th>Fecha</x-tables.th>
                <x-tables.th>Importe (S/)</x-tables.th>
            </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
            <tr>
                <x-tables.td colspan="2" align="right" class="text-sm font-bold text-kp-ink">Total cronograma</x-tables.td>
                <x-tables.td>
                    <span id="cvSuma" class="text-base font-black text-kp-ink">0.00</span>
                    <div id="cvErr" class="mt-1 hidden text-xs font-semibold text-red-600">
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
        <span class="hidden font-semibold text-kp-ink" id="hintBalon">En cuota balón, la última fila se marca como balón.</span>
    </p>
</div>

<div id="formCancelacion" class="mt-4 hidden space-y-4">
    <h4 class="text-sm font-bold text-kp-ink">Detalles de cancelación</h4>
    
    <div class="rounded-lg border border-kp-border bg-slate-50 p-4 grid gap-4 md:grid-cols-2">
        <x-forms.input label="Monto a cancelar (S/)" name="monto_cancel" type="number" step="0.01" min="0.01" />
        <x-forms.date label="Fecha límite de pago" name="fecha_pago_cancel" />
    </div>
</div>