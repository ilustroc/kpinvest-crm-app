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
    <div class="kp-modal-backdrop"></div>
    <div class="kp-modal-card kp-modal-lg">
        <div class="kp-modal-head bg-slate-50">
            <div>
                <h2 class="text-lg font-extrabold text-slate-900">Detalle de Propuesta</h2>
                <p class="text-xs text-slate-500 flex items-center gap-2">
                    <span class="font-mono bg-slate-200 px-1.5 py-0.5 rounded text-slate-700" id="f_op">--</span>
                    <span class="text-slate-300">|</span>
                    <span id="t_fecha">--</span>
                </p>
            </div>
            <button class="kp-x" data-modal-close>&times;</button>
        </div>

        <div class="kp-modal-body p-0">
            {{-- Resumen de Montos --}}
            <div class="grid grid-cols-1 md:grid-cols-3 border-b border-slate-100">
                <div class="p-5 border-r border-slate-100 bg-slate-50/50">
                    <label class="kp-label">Deuda Total</label>
                    <div class="text-xl font-bold text-slate-400 line-through" id="t_deuda">S/ 0.00</div>
                </div>
                <div class="p-5 border-r border-slate-100 bg-emerald-50/30">
                    <label class="kp-label text-emerald-700">Monto Negociado</label>
                    <div class="text-3xl font-black text-emerald-600 tracking-tighter" id="t_neg">S/ 0.00</div>
                </div>
                <div class="p-5 bg-slate-50/50">
                    <label class="kp-label">Tipo de Acuerdo</label>
                    <div class="mt-1"><span class="autz-badge autz-badge-info" id="t_tipo">--</span></div>
                </div>
            </div>

            <div class="p-6 space-y-8">
                {{-- Info Cliente --}}
                <section>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div class="autz-kv border-none bg-transparent p-0">
                            <div class="autz-k">Titular</div>
                            <div class="autz-v text-base" id="t_titular">--</div>
                        </div>
                        <div class="autz-kv border-none bg-transparent p-0">
                            <div class="autz-k">DNI / Documento</div>
                            <div class="autz-v text-base font-mono" id="f_dni">--</div>
                        </div>
                        <div class="autz-kv border-none bg-transparent p-0">
                            <div class="autz-k text-emerald-600">Asesor Responsable</div>
                            <div class="autz-v text-base" id="t_asesor">--</div>
                        </div>
                    </div>
                </section>

                {{-- Notas --}}
                <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div id="nota_general_wrap" class="autz-box bg-blue-50 border-blue-100 hidden">
                        <span class="text-[10px] font-black text-blue-600 uppercase block mb-1">Nota del Asesor</span>
                        <p id="nota_general_txt" class="text-blue-900 italic"></p>
                    </div>
                    <div id="nota_sup_wrap" class="autz-box bg-amber-50 border-amber-100 hidden">
                        <span class="text-[10px] font-black text-amber-600 uppercase block mb-1">Nota de Pre-Aprobación</span>
                        <p id="nota_sup_txt" class="text-amber-900 italic"></p>
                    </div>
                </section>

                {{-- Cuentas y Cronograma --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="autz-title mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            Cuentas Incluidas
                        </h3>
                        <div id="acc_cuentas" class="autz-acc space-y-2"></div>
                    </div>

                    <div id="crono_wrap" class="hidden">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="autz-title" id="crono_titulo">Cronograma</h3>
                            <span class="autz-chip autz-chip-info hidden" id="crono_balon">BALÓN</span>
                        </div>
                        <div class="autz-table-wrap ring-slate-200">
                            <table class="autz-table">
                                <thead>
                                    <tr class="!bg-slate-100">
                                        <th class="text-center w-16">Cuota</th>
                                        <th class="text-center">Vencimiento</th>
                                        <th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody id="crono_body"></tbody>
                                <tfoot>
                                    <tr class="bg-slate-900 text-white font-bold">
                                        <td colspan="2" class="px-4 py-3 text-right text-xs uppercase">Total Convenio</td>
                                        <td class="px-4 py-3 text-right text-emerald-400 text-base" id="crono_total">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="kp-modal-foot bg-slate-50">
            <button class="kp-btn kp-btn-ghost" data-modal-close>Cerrar Ficha</button>
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