<section aria-labelledby="promesaCondicionesTitle">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h4 id="promesaCondicionesTitle" class="text-[13px] font-semibold text-kp-ink">Condiciones de la promesa</h4>
            <p class="mt-0.5 text-[11px] text-kp-muted">Define la modalidad, el contacto y los datos de la propuesta.</p>
        </div>
        <span class="text-[10px] font-medium text-slate-400">Los campos con * son obligatorios</span>
    </div>

    <div class="mt-3 grid gap-3 md:grid-cols-2">
        <div>
            <x-ui.select label="Tipo de propuesta *" name="tipo" id="tipoPropuesta" required autofocus>
                <optgroup label="Convenios">
                    <option value="convenio" data-balon="0">Convenio</option>
                    <option value="convenio_balon" data-balon="1">Convenio (cuota balón)</option>
                </optgroup>
                <optgroup label="Otros">
                    <option value="cancelacion">Cancelación</option>
                </optgroup>
            </x-ui.select>
            <x-forms.error name="tipo" />
        </div>

        <div>
            <x-ui.input
                label="Teléfono de contacto *"
                name="telefono"
                id="telefonoPropuesta"
                maxlength="30"
                inputmode="tel"
                pattern="^\+?\d[\d\s\-]{5,}$"
                title="Ingresa un teléfono válido"
                placeholder="+51 9XXXXXXXX"
                aria-describedby="telefonoHelp"
                required
            />
            <p id="telefonoHelp" class="mt-1 text-[10.5px] text-kp-muted">Formato local o internacional. Se usa para coordinar el pago.</p>
            <x-forms.error name="telefono" />
        </div>
    </div>

    <div class="mt-3">
        <x-ui.textarea
            label="Observación"
            name="nota"
            id="notaPropuesta"
            rows="2"
            maxlength="500"
            placeholder="Comentario contextual para la propuesta"
        />
        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
            <x-forms.error name="nota" />
            <p class="text-[10.5px] text-kp-muted">Opcional · máximo 500 caracteres.</p>
        </div>
    </div>
</section>

<div id="formConvenio" class="mt-5 space-y-3 border-t border-kp-border pt-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h4 class="text-[13px] font-semibold text-kp-ink">Cronograma del convenio</h4>
            <p class="mt-0.5 text-[11px] text-kp-muted">Define el importe, las cuotas y las fechas de pago.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-md border border-kp-border bg-kp-surface-muted px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.04em] text-slate-500">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
            </svg>
            Editable
        </span>
    </div>

    <div class="rounded-lg border border-kp-border bg-kp-surface-muted p-3.5">
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <x-ui.input
                    label="Monto convenio (S/) *"
                    name="monto_convenio"
                    type="number"
                    id="cvTotal"
                    step="0.01"
                    min="0.01"
                    inputmode="decimal"
                    placeholder="0.00"
                    required
                />
                <x-forms.error name="monto_convenio" />
            </div>

            <div>
                <x-ui.input
                    label="Nro. cuotas *"
                    name="nro_cuotas"
                    type="number"
                    id="cvNro"
                    min="1"
                    step="1"
                    inputmode="numeric"
                    placeholder="1"
                    required
                />
                <x-forms.error name="nro_cuotas" />
            </div>

            <div>
                <x-ui.input
                    label="Monto por cuota (S/)"
                    name="monto_cuota"
                    type="number"
                    id="cvCuota"
                    step="0.01"
                    min="0.01"
                    inputmode="decimal"
                    placeholder="Opcional"
                />
                <p class="mt-1 text-[10.5px] text-kp-muted">Referencia para generar el cronograma.</p>
                <x-forms.error name="monto_cuota" />
            </div>
        </div>

        <div id="cvErr" class="mt-3 hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs font-medium text-red-700" role="alert">
            <div class="flex items-start gap-2">
                <svg class="mt-0.5 size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>El total del cronograma debe coincidir con el Monto convenio.</span>
            </div>
        </div>

        <p id="cvDiff" class="mt-2 text-[10.5px] font-medium text-kp-muted">Captura el monto convenio para validar el cronograma.</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
            <div>
                <x-ui.date label="Fecha inicial" id="cvFechaIni" />
                <p id="cvHintDia" class="mt-1 text-[10.5px] font-medium text-kp-muted">Día de pago: -</p>
            </div>
            <x-ui.button type="button" id="cvGen" variant="secondary" class="w-full sm:w-auto">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                </svg>
                Generar cronograma
            </x-ui.button>
        </div>
    </div>

    <div class="overflow-hidden rounded-[10px] border border-kp-border bg-white">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-kp-border bg-white px-3.5 py-2.5">
            <div>
                <h5 class="text-[12px] font-semibold text-kp-ink">Cuotas</h5>
                <p class="mt-0.5 text-[10.5px] text-kp-muted">Edita fechas e importes; el total se calcula automáticamente.</p>
            </div>
            <span id="cvScheduleCount" class="inline-flex items-center rounded-full border border-kp-border bg-kp-surface-muted px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                0 cuotas
            </span>
        </div>

        <x-tables.table id="tblCrono" :flush="true" :contained="true" min-width="min-w-[560px]">
            <thead>
                <tr>
                    <x-tables.th align="center" class="w-14">#</x-tables.th>
                    <x-tables.th>Fecha de pago</x-tables.th>
                    <x-tables.th align="right">Importe (S/)</x-tables.th>
                    <x-tables.th align="center" class="w-24">Cuota</x-tables.th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr class="bg-kp-surface-muted">
                    <x-tables.td colspan="2" align="right" class="text-[11px] font-semibold uppercase tracking-[0.04em] text-slate-500">Total del cronograma</x-tables.td>
                    <x-tables.td align="right" class="font-semibold text-kp-ink">
                        <span id="cvSuma" class="tabular-nums">S/ 0.00</span>
                    </x-tables.td>
                    <x-tables.td align="center">
                        <span class="text-[10px] font-semibold uppercase tracking-[0.04em] text-slate-400">Calculado</span>
                    </x-tables.td>
                </tr>
            </tfoot>
        </x-tables.table>
    </div>

    <p class="hidden text-[10.5px] font-medium text-amber-700" id="hintBalon">
        En cuota balón, la última fila se marca como balón.
    </p>

    <x-forms.error name="cron_monto" />
    <x-forms.error name="cron_fecha" />
    <x-forms.error name="cron_balon" />

    <div id="cvHidden"></div>
    <input type="hidden" name="cron_balon" id="cronBalon">
</div>

<div id="formCancelacion" class="mt-5 hidden space-y-3 border-t border-kp-border pt-4">
    <div>
        <h4 class="text-[13px] font-semibold text-kp-ink">Detalles de cancelación</h4>
        <p class="mt-0.5 text-[11px] text-kp-muted">Registra el importe y la fecha límite de pago de la cancelación.</p>
    </div>

    <div class="rounded-lg border border-kp-border bg-kp-surface-muted p-3.5">
        <div class="grid gap-3 md:grid-cols-2">
            <div>
                <x-ui.input
                    label="Monto a cancelar (S/) *"
                    name="monto_cancel"
                    type="number"
                    step="0.01"
                    min="0.01"
                    inputmode="decimal"
                    placeholder="0.00"
                />
                <x-forms.error name="monto_cancel" />
            </div>

            <div>
                <x-ui.date label="Fecha límite de pago *" name="fecha_pago_cancel" id="fechaPagoCancel" />
                <x-forms.error name="fecha_pago_cancel" />
            </div>
        </div>

        <p class="mt-2 text-[10.5px] text-kp-muted">
            La propuesta se registrará como cancelación total de la operación seleccionada.
        </p>
    </div>
</div>
