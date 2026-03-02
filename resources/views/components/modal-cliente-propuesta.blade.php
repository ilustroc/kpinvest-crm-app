{{-- resources/views/components/modal-cliente-propuesta.blade.php --}}
@props(['dni'])

<div id="modalPropuesta"
     class="fixed inset-0 z-[90] hidden"
     data-modal
     role="dialog"
     aria-modal="true"
     aria-labelledby="modalPropuestaTitle">

  {{-- Backdrop --}}
  <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-[2px]"
       data-modal-close></div>

  {{-- Dialog --}}
  <div class="relative mx-auto my-6 w-[calc(100%-1.5rem)] max-w-5xl">
    <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/90 shadow-[0_30px_90px_rgba(2,6,23,.25)]">

      <form method="POST"
            action="{{ route('clientes.promesas.store', $dni) }}"
            id="formPropuesta"
            data-once>
        @csrf

        {{-- Header --}}
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200/70 bg-white/70">
          <div class="flex items-center gap-3 min-w-0">
            <div class="h-10 w-10 rounded-2xl bg-emerald-500/10 text-emerald-700 grid place-items-center">
              <i class="bi bi-flag-fill"></i>
            </div>

            <div class="min-w-0">
              <div class="flex items-center gap-2 min-w-0">
                <h3 id="modalPropuestaTitle" class="text-base font-black text-slate-900 truncate">
                  Generar propuesta
                </h3>

                <span id="modalTipoTag"
                      class="inline-flex items-center rounded-full border border-slate-200/70 bg-white px-2.5 py-1 text-[11px] font-extrabold text-slate-700">
                  Convenio
                </span>
              </div>

              <p class="text-xs text-slate-500">
                DNI {{ $dni }} · Se guardará con las operaciones seleccionadas
              </p>
            </div>
          </div>

          {{-- ✅ X real (ya no depende de Bootstrap) --}}
          <button type="button"
                  class="h-10 w-10 rounded-2xl border border-slate-200/70 bg-white/60 text-slate-700 hover:bg-white grid place-items-center"
                  data-modal-close
                  aria-label="Cerrar">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        {{-- Body --}}
        <div class="p-5 max-h-[72vh] overflow-auto space-y-4">

          {{-- Ops --}}
          <div>
            <div class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-slate-500 mb-2">
              Operaciones a incluir
            </div>

            <div id="opsResumen" class="flex flex-wrap gap-2"></div>
            <div id="opsHidden"></div>
          </div>

          {{-- Top fields --}}
          <div class="grid grid-cols-12 gap-3">
            <div class="col-span-12 md:col-span-4">
              <label class="form-label">Tipo de propuesta</label>
              <select name="tipo" id="tipoPropuesta" class="form-select" required>
                <optgroup label="Convenios">
                  <option value="convenio" data-balon="0">Convenio</option>
                  <option value="convenio_balon" data-balon="1">Convenio (cuota balón)</option>
                </optgroup>
                <optgroup label="Otros">
                  <option value="cancelacion">Cancelación</option>
                </optgroup>
              </select>
            </div>

            <div class="col-span-12 md:col-span-4">
              <label class="form-label">Teléfono de contacto</label>
              <input name="telefono"
                     id="telefonoPropuesta"
                     class="form-control"
                     placeholder="+51 9XXXXXXXX"
                     maxlength="30"
                     inputmode="tel"
                     pattern="^\+?\d[\d\s\-]{5,}$"
                     title="Ingresa un teléfono válido"
                     required>
            </div>

            <div class="col-span-12 md:col-span-4">
              <label class="form-label">Observación (opcional)</label>
              <input name="nota" class="form-control" maxlength="500" placeholder="Detalle (máx. 500)">
            </div>
          </div>

          {{-- Convenio --}}
          <div id="formConvenio" class="space-y-3">
            <div class="grid grid-cols-12 gap-3">
              <div class="col-span-12 md:col-span-3">
                <label class="form-label">Nro cuotas</label>
                <input type="number" min="1" step="1" name="nro_cuotas" id="cvNro" class="form-control" required>
              </div>

              <div class="col-span-12 md:col-span-3">
                <label class="form-label">Monto convenio (S/)</label>
                <input type="number" step="0.01" min="0.01" name="monto_convenio" id="cvTotal" class="form-control" required>
              </div>

              <div class="col-span-12 md:col-span-3">
                <label class="form-label">Monto de cuota (S/)</label>
                <input type="number" step="0.01" min="0.01" name="monto_cuota" id="cvCuota" class="form-control">
              </div>

              <div class="col-span-12 md:col-span-3">
                <label class="form-label">Fecha inicial (auto)</label>
                <input type="date" id="cvFechaIni" class="form-control">
                <div class="form-text" id="cvHintDia">Día de pago: —</div>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <button type="button" id="cvGen" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-magic"></i> Generar cronograma
              </button>
              <span class="text-xs text-slate-500">Puedes editar fechas y montos después de generar.</span>
            </div>

            <div class="rounded-2xl border border-slate-200/70 bg-white/60 overflow-hidden">
              <table class="table table-sm align-middle tbl-compact" id="tblCrono">
                <thead>
                  <tr>
                    <th style="width:70px">#</th>
                    <th style="width:180px">Fecha</th>
                    <th>Importe (S/)</th>
                  </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                  <tr>
                    <td colspan="2" class="text-end fw-bold">Total cronograma</td>
                    <td>
                      <span id="cvSuma" class="fw-bold text-slate-900">0.00</span>
                      <div id="cvErr" class="text-xs text-rose-600 mt-1 hidden">
                        El total del cronograma debe coincidir con el Monto convenio.
                      </div>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div id="cvHidden"></div>
            <input type="hidden" name="cron_balon" id="cronBalon">

            <div class="text-xs text-slate-500">
              * El total del cronograma debe coincidir con el <b>Monto convenio</b>.
              <span class="block hidden" id="hintBalon">
                (En cuota balón: la última fila es la cuota balón).
              </span>
            </div>
          </div>

          {{-- Cancelación --}}
          <div id="formCancelacion" class="grid grid-cols-12 gap-3 hidden">
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Fecha de pago</label>
              <input type="date" name="fecha_pago_cancel" class="form-control">
            </div>
            <div class="col-span-12 md:col-span-6">
              <label class="form-label">Monto (S/)</label>
              <input type="number" step="0.01" min="0.01" name="monto_cancel" class="form-control">
            </div>
          </div>

        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-slate-200/70 bg-white/70">
          <button type="button" class="btn btn-outline-secondary" data-modal-close>
            Cancelar
          </button>

          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle"></i> Guardar propuesta
          </button>
        </div>
      </form>

    </div>
  </div>
</div>