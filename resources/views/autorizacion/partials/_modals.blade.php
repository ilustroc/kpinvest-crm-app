{{-- resources/views/autorizacion/partials/_modals.blade.php --}}

{{-- Modal: RECHAZO --}}
<div id="modalRechazo" class="kp-modal hidden" aria-hidden="true">
    <div class="kp-modal-backdrop" data-modal-close></div>
    <div class="kp-modal-card" role="dialog" aria-modal="true" aria-labelledby="rechTitle">
        <div class="kp-modal-head">
            <div class="font-extrabold text-slate-900" id="rechTitle">Motivo / Nota de rechazo</div>
            <button type="button" class="kp-x" data-modal-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="formRechazo" method="POST" action="#">
            @csrf
            <div class="kp-modal-body space-y-3">
                <label class="kp-label">Nota (obligatoria)</label>
                <textarea name="nota_estado" id="motivoTxt" class="kp-input h-28" maxlength="500" required placeholder="Escriba el motivo del rechazo..."></textarea>
            </div>

            <div class="kp-modal-foot">
                <button type="button" class="kp-btn kp-btn-ghost" data-modal-close>Cancelar</button>
                <button class="kp-btn kp-btn-danger" type="submit">Rechazar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal: NOTA (aprobar / preaprobar) --}}
<div id="modalNota" class="kp-modal hidden" aria-hidden="true">
    <div class="kp-modal-backdrop" data-modal-close></div>
    <div class="kp-modal-card" role="dialog" aria-modal="true" aria-labelledby="notaTitle">
        <div class="kp-modal-head">
            <div class="font-extrabold text-slate-900" id="notaTitle">Agregar nota</div>
            <button type="button" class="kp-x" data-modal-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="formNotaEstado" method="POST" action="#">
            @csrf
            <div class="kp-modal-body space-y-3">
                <label class="kp-label">Nota (opcional)</label>
                <textarea name="nota_estado" id="notaEstadoTxt" class="kp-input h-28" maxlength="500"
                          placeholder="(opcional) Escribe una nota para esta decisión…"></textarea>
            </div>

            <div class="kp-modal-foot">
                <button type="button" class="kp-btn kp-btn-ghost" data-modal-close>Cancelar</button>
                <button class="kp-btn kp-btn-primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal: FICHA PROMESA --}}
<div id="modalFicha" class="kp-modal hidden" aria-hidden="true">
  <div class="kp-modal-backdrop" data-modal-close></div>

  <div class="kp-modal-card kp-modal-lg" role="dialog" aria-modal="true">
    {{-- HEADER --}}
    <div class="kf-head">
      <div>
        <div class="kf-title">Detalle de Propuesta</div>
        <div class="kf-meta">
          <span class="kf-chip">
            <span class="opacity-70">OP</span>
            <span class="font-mono font-black" id="f_op">--</span>
          </span>

          <span class="kf-chip">
            <span class="opacity-70">Fecha</span>
            <span class="font-black" id="t_fecha">--</span>
          </span>

          <span class="kf-chip">
            <span class="opacity-70">Tipo</span>
            <span id="t_tipo" class="font-black">--</span>
          </span>
        </div>
      </div>

      <button class="kf-x" type="button" data-modal-close aria-label="Cerrar">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
        </svg>
      </button>
    </div>

    {{-- BODY --}}
    <div class="kp-modal-body kf-body p-0">

      {{-- Datos cliente --}}
      <div class="kf-pad">
        <div class="kf-card">
          <div class="kf-card-h">
            <div class="kf-card-t">
              <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-width="2" stroke-linecap="round" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                <path stroke-width="2" stroke-linecap="round" d="M12 11a4 4 0 100-8 4 4 0 000 8z"/>
              </svg>
              Datos del cliente
            </div>
          </div>

          <div class="kf-card-b">
            <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-3 gap-3">
              <div class="kf-kv">
                <div class="kf-k">Documento / RUC</div>
                <div class="kf-v" id="f_dni">--</div>
              </div>
              <div class="kf-kv">
                <div class="kf-k">Titular</div>
                <div class="kf-v" id="t_titular">--</div>
              </div>
              <div class="kf-kv">
                <div class="kf-k">Deuda total</div>
                <div class="kf-v" id="t_deuda">S/ 0.00</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- GRID --}}
      <div class="kf-grid">
        {{-- LEFT --}}
        <div class="kf-left">
          {{-- STATS --}}
          <div class="kf-stats">

            <div class="kf-stat">
              <div class="lbl">Monto negociado</div>
              <div class="val" id="t_neg">S/ 0.00</div>
            </div>

            <div class="kf-stat">
              <div class="lbl">Asesor responsable</div>
              <div class="val" id="t_asesor">--</div>
            </div>
          </div>

          {{-- Notas --}}
          <div id="notes_grid" class="kf-notes grid grid-cols-1 md:grid-cols-2 gap-3">
            <div id="nota_general_wrap" class="kf-note is-ases hidden">
              <div class="t">Nota del asesor</div>
              <div class="p" id="nota_general_txt"></div>
            </div>

            <div id="nota_sup_wrap" class="kf-note is-sup hidden">
              <div class="t">Nota de pre-aprobación</div>
              <div class="p" id="nota_sup_txt"></div>
            </div>
          </div>

          {{-- Cuentas --}}
          <div class="kf-card">
            <div class="kf-card-h">
              <div class="kf-card-t">
                <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-width="2" stroke-linecap="round" d="M3 7h18M3 12h18M3 17h18"/>
                </svg>
                Cuentas incluidas
              </div>
            </div>

            <div class="kf-card-b">
              <div id="acc_cuentas" class="kf-acc space-y-2"></div>
            </div>
          </div>
        </div>

        {{-- RIGHT --}}
        <div class="kf-right">
          <div id="crono_wrap" class="kf-crono">
            <div class="kf-crono-h">
              <div class="kf-crono-t" id="crono_titulo">Cronograma de pagos</div>
              <span class="kf-chip hidden" id="crono_balon">BALÓN</span>
            </div>

            <div class="kf-crono-scroll">
              <table class="kf-table">
                <thead>
                  <tr>
                    <th class="w-16 text-center">#</th>
                    <th class="text-center">Vencimiento</th>
                    <th class="text-end">Monto</th>
                  </tr>
                </thead>
                <tbody id="crono_body"></tbody>
              </table>

              <div id="crono_empty" class="kf-empty hidden">No hay cronograma...</div>
            </div>

            <div class="kf-total">
              <div class="lbl">Total convenio</div>
              <div class="val">S/ <span id="crono_total">0.00</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- FOOT --}}
    <div class="kf-foot">
      <button class="kp-btn kp-btn-ghost" type="button" data-modal-close>Cerrar ficha</button>
    </div>
  </div>
</div>

{{-- Modal: CNA FICHA --}}
<div id="modalCnaFicha" class="kp-modal hidden" aria-hidden="true">
    <div class="kp-modal-backdrop" data-modal-close></div>
    <div class="kp-modal-card kp-modal-lg" role="dialog" aria-modal="true" aria-labelledby="cnaTitle">
        <div class="kp-modal-head">
            <div class="font-extrabold text-slate-900" id="cnaTitle">
                CNA — DNI <span id="cna_dni">—</span> · Carta <span id="cna_carta">—</span>
            </div>
            <button type="button" class="kp-x" data-modal-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="kp-modal-body space-y-4">
            <div class="autz-grid">
                <div class="autz-kv"><div class="autz-k">Fecha solicitud</div><div class="autz-v" id="cna_fecha">—</div></div>
                <div class="autz-kv"><div class="autz-k">Operación(es)</div><div class="autz-v" id="cna_ops">—</div></div>
                <div class="autz-kv"><div class="autz-k">Fecha pago realizado</div><div class="autz-v" id="cna_fecha_pago">—</div></div>
                <div class="autz-kv"><div class="autz-k">Monto pagado (S/)</div><div class="autz-v" id="cna_monto_pagado">0.00</div></div>
            </div>

            <div>
                <div class="text-sm font-extrabold text-slate-900 mb-1">Observación</div>
                <div id="cna_obs" class="autz-box">—</div>
            </div>

            <div class="text-center">
                <div class="text-sm font-extrabold text-slate-900">Total de pagos del cliente</div>
                <div id="cna_total_pagos" class="autz-total">S/ 0.00</div>
            </div>

            <div>
                <div class="text-sm font-extrabold text-slate-900 mb-2">Pagos realizados</div>
                <div class="autz-table-wrap">
                    <table class="autz-table">
                        <thead>
                            <tr>
                                <th>Operación</th>
                                <th>Fecha</th>
                                <th class="text-end">Monto (S/)</th>
                                <th>Gestor</th>
                                <th>Entidad</th>
                                <th>Cosecha</th>
                                <th>Cuenta recaudo</th>
                            </tr>
                        </thead>
                        <tbody id="cna_pagos_tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="kp-modal-foot">
            <button type="button" class="kp-btn kp-btn-ghost" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>

{{-- Modal: Texto / Nota completa --}}
<div id="modalTexto" class="kp-modal hidden" aria-hidden="true">
    <div class="kp-modal-backdrop" data-modal-close></div>
    <div class="kp-modal-card" role="dialog" aria-modal="true" aria-labelledby="txtTitle">
        <div class="kp-modal-head">
            <div class="font-extrabold text-slate-900" id="txtTitle">Nota Completa</div>
            <button type="button" class="kp-x" data-modal-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="kp-modal-body">
            <div id="txtBody" class="whitespace-pre-wrap text-sm text-slate-700 leading-relaxed"></div>
        </div>

        <div class="kp-modal-foot">
            <button type="button" class="kp-btn kp-btn-ghost" data-modal-close>Cerrar</button>
        </div>
    </div>
</div>