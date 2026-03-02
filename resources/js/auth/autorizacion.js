// resources/js/auth/autorizacion.js

document.addEventListener('DOMContentLoaded', () => {
    // ==========================
    // 1. Helpers de Formato
    // ==========================
    const $ = (id) => document.getElementById(id);

    const money = (v) => Number(String(v ?? 0).replace(/[^0-9.-]+/g, ""))
        .toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const fmt2 = (n) => (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);

    const toDMY = (val) => {
        if (!val) return '—';
        const s = String(val).trim();
        const datePart = s.split(' ')[0];
        const sep = datePart.includes('-') ? '-' : '/';
        const parts = datePart.split(sep);
        if (parts.length === 3) {
            const [y, m, d] = parts;
            // Detecta si viene YYYY-MM-DD o DD-MM-YYYY
            return y.length === 4 ? `${d.padStart(2, '0')}/${m.padStart(2, '0')}/${y}` : s;
        }
        return s;
    };

    // ==========================
    // 2. Sistema de Tabs
    // ==========================
    const tabBtns = document.querySelectorAll('[data-tab-btn]');
    const tabs = document.querySelectorAll('[data-tab]');

    const showTab = (key) => {
        tabBtns.forEach(b => b.classList.toggle('is-active', b.dataset.tabBtn === key));
        tabs.forEach(s => s.classList.toggle('hidden', s.dataset.tab !== key));
    };

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => showTab(btn.dataset.tabBtn));
    });

    // Iniciar en la pestaña de promesas
    showTab('promesas');

    // ==========================
    // 3. Core del Modal (KP System)
    // ==========================
    const openModal = (id) => {
        const el = $(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden'); // Bloquea scroll del body
    };

    const closeModal = (id) => {
        const el = $(id);
        if (!el) return;
        el.classList.add('hidden');
        el.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    };

    // Botones de cerrar (X o Cancelar)
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.kp-modal');
            if (modal?.id) closeModal(modal.id);
        });
    });

    // Cerrar al hacer clic en el backdrop (fondo oscuro)
    document.querySelectorAll('.kp-modal-backdrop').forEach(bg => {
        bg.addEventListener('click', () => {
            const modal = bg.closest('.kp-modal');
            if (modal?.id) closeModal(modal.id);
        });
    });

    // Tecla ESC para cerrar
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const openModalEl = document.querySelector('.kp-modal:not(.hidden)');
            if (openModalEl) closeModal(openModalEl.id);
        }
    });

    // ==========================
    // 4. Lógica de Ficha Promesa
    // ==========================
    document.querySelectorAll('.js-ver-ficha').forEach(btn => {
        btn.addEventListener('click', () => {
            const d = btn.dataset;
            const tipoRaw = (d.tipo || '').toLowerCase();

            // 1. Llenado de cabecera y campos básicos
            if($('f_dni'))      $('f_dni').textContent = d.dni || '—';
            if($('f_op'))       $('f_op').textContent = d.operacion || '—';
            if($('t_titular'))  $('t_titular').textContent = d.titular || '—';
            if($('t_deuda'))    $('t_deuda').textContent = `S/ ${d.deuda || '0.00'}`;
            if($('t_neg'))      $('t_neg').textContent = `S/ ${d.negociado || '0.00'}`;
            if($('t_fecha'))    $('t_fecha').textContent = d.fecha || '—';
            
            // Asesor Responsable (Importante: debe venir en el data-asesor del botón)
            if($('t_asesor'))   $('t_asesor').textContent = d.asesor || 'No asignado';

            // Tipo de acuerdo con estilo de badge
            if($('t_tipo')) {
                const esCancelacion = tipoRaw === 'cancelacion';
                $('t_tipo').textContent = esCancelacion ? 'Cancelación' : 'Convenio';
                $('t_tipo').className = esCancelacion 
                    ? 'px-2 py-1 rounded-md bg-rose-50 text-rose-700 ring-1 ring-rose-200 text-sm font-bold'
                    : 'px-2 py-1 rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 text-sm font-bold';
            }

            // 2. Notas dinámicas (Asesor y Supervisor)
            const notaGen = (d.notaGen || '').trim();
            const notaSup = (d.notaSup || '').trim();
            
            if ($('nota_general_wrap')) {
                $('nota_general_wrap').classList.toggle('hidden', !notaGen);
                if (notaGen) $('nota_general_txt').textContent = notaGen;
            }

            if ($('nota_sup_wrap')) {
                $('nota_sup_wrap').classList.toggle('hidden', !notaSup);
                if (notaSup) $('nota_sup_txt').textContent = notaSup;
            }

            // 3. Renderizado de Cuentas Incluidas (Acordeón)
            const acc = $('acc_cuentas');
            if (acc) {
                let cuentas = [];
                try { cuentas = JSON.parse(btn.getAttribute('data-cuentas') || '[]'); } catch (e) { console.error("Error parse cuentas", e); }
                
                acc.innerHTML = !cuentas.length 
                    ? '<div class="autz-box text-center text-slate-500">No hay cuentas detalladas.</div>'
                    : cuentas.map((c, i) => `
                        <details class="autz-acc group" ${i === 0 ? 'open' : ''}>
                            <summary class="flex items-center justify-between p-3 cursor-pointer hover:bg-slate-50 rounded-xl transition-all">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-slate-500 uppercase">Op. ${c.operacion || '—'}</span>
                                    <span class="text-sm font-extrabold text-slate-800">${c.entidad || 'Entidad'}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-emerald-600 font-black">S/ ${fmt2(c.deuda_total)}</span>
                                    <svg class="w-4 h-4 inline ml-2 text-slate-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </summary>
                            <div class="p-4 bg-slate-50/50 rounded-b-xl grid grid-cols-2 gap-4 border-t border-slate-100">
                                <div class="autz-kv bg-white"><div class="autz-k">Producto</div><div class="autz-v">${c.producto || '—'}</div></div>
                                <div class="autz-kv bg-white"><div class="autz-k">Cosecha</div><div class="autz-v">${c.cosecha || '—'}</div></div>
                                <div class="autz-kv bg-white col-span-2"><div class="autz-k">Capital</div><div class="autz-v text-emerald-700">S/ ${fmt2(c.saldo_capital)}</div></div>
                            </div>
                        </details>
                    `).join('');
            }

            // 4. Cronograma de Cuotas
            const cronoWrap = $('crono_wrap');
            if (cronoWrap) {
                let cuotas = [];
                try { cuotas = JSON.parse(btn.getAttribute('data-crono') || '[]'); } catch (e) {}

                if (tipoRaw === 'cancelacion' || !cuotas.length) {
                    cronoWrap.classList.add('hidden');
                } else {
                    cronoWrap.classList.remove('hidden');
                    if($('crono_total')) $('crono_total').textContent = d.negociado;
                    
                    const tbody = $('crono_body');
                    tbody.innerHTML = cuotas.map(c => `
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 text-center font-mono text-slate-500">${String(c.nro).padStart(2, '0')}</td>
                            <td class="px-4 py-3 text-center font-medium">${toDMY(c.fecha)}</td>
                            <td class="px-4 py-3 text-end font-black text-slate-900">S/ ${fmt2(c.monto)}</td>
                        </tr>
                    `).join('');
                }
            }

            openModal('modalFicha');
        });
    });

    // ==========================
    // 5. Modales de Acción (Aprobar/Rechazar)
    // ==========================
    document.querySelectorAll('.js-open-nota').forEach(btn => {
        btn.addEventListener('click', () => {
            const frm = $('formNotaEstado');
            if (frm) frm.action = btn.dataset.action;
            if ($('notaTitle')) $('notaTitle').textContent = btn.dataset.title || 'Autorizar';
            openModal('modalNota');
            setTimeout(() => $('notaEstadoTxt')?.focus(), 200);
        });
    });

    document.querySelectorAll('.js-open-rechazo').forEach(btn => {
        btn.addEventListener('click', () => {
            const frm = $('formRechazo');
            if (frm) frm.action = btn.dataset.action;
            openModal('modalRechazo');
            setTimeout(() => $('motivoTxt')?.focus(), 200);
        });
    });

    // ==========================
    // 6. Ver Nota completa (modalTexto)
    // ==========================
    document.querySelectorAll('.js-show-note').forEach(btn => {
        btn.addEventListener('click', () => {
            const title = btn.dataset.title || 'Nota';
            let text = '';
            
            try { 
                // Intentamos parsear si viene como JSON, sino lo tomamos como string
                text = JSON.parse(btn.getAttribute('data-text') || '""'); 
            } catch (e) { 
                text = btn.getAttribute('data-text') || '—'; 
            }

            const t = $('txtTitle');
            const b = $('txtBody');
            
            if (t) t.textContent = title;
            if (b) b.textContent = text || '—';

            openModal('modalTexto');
        });
    });

    // ==========================
    // 7. CNA Ficha y Fetch Pagos
    // ==========================
    document.querySelectorAll('.js-ver-cna').forEach(btn => {
        btn.addEventListener('click', async () => {
            const d = btn.dataset;
            if($('cna_dni')) $('cna_dni').textContent = d.dni;
            
            // Lógica de fetch de pagos para CNA
            const urlMeta = document.querySelector('meta[name="autz-pagos-url"]');
            const tbodyPagos = $('cna_pagos_tbody');
            
            if (urlMeta && tbodyPagos) {
                tbodyPagos.innerHTML = '<tr><td colspan="7" class="p-4 text-center">Cargando pagos...</td></tr>';
                try {
                    const res = await fetch(urlMeta.content.replace('__DNI__', d.dni));
                    const data = await res.json();
                    const pagos = data.pagos || [];
                    
                    tbodyPagos.innerHTML = pagos.length 
                        ? pagos.map(p => `
                            <tr>
                                <td>${p.operacion}</td>
                                <td>${toDMY(p.fecha)}</td>
                                <td class="text-end font-bold">S/ ${money(p.monto_pagado)}</td>
                                <td>${p.entidad}</td>
                                <td>${p.cuenta_recaudo || '—'}</td>
                            </tr>
                        `).join('')
                        : '<tr><td colspan="7" class="p-4 text-center text-slate-400">No se encontraron pagos.</td></tr>';
                } catch (err) {
                    tbodyPagos.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-red-500">Error al cargar pagos.</td></tr>';
                }
            }
            openModal('modalCnaFicha');
        });
    });
});