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

    // Helpers robustos (una sola vez)
    const parseJSON = (raw) => { try { return JSON.parse(raw); } catch { return null; } };

    // Devuelve SIEMPRE string limpio (arregla "campa\u00f1a" y comillas)
    const readText = (val) => {
    if (val == null) return '';
    const s = String(val).trim();
    const j = parseJSON(s);
    return (typeof j === 'string') ? j : s;
    };

    // Devuelve SIEMPRE array (arregla data-crono/data-cuentas en cualquier formato)
    const readArrayAttr = (el, attr) => {
    const raw = el.getAttribute(attr);
    if (!raw) return [];

    let v = parseJSON(raw);

    // Caso: viene como string JSON dentro ( "\"[{...}]\"" )
    if (typeof v === 'string') v = parseJSON(v);

    // Caso: objeto {cuotas:[...]} o {data:[...]}
    if (v && typeof v === 'object' && !Array.isArray(v)) {
        if (Array.isArray(v.cuotas)) return v.cuotas;
        if (Array.isArray(v.data)) return v.data;
    }

    return Array.isArray(v) ? v : [];
    };

    // ✅ Delegación: un solo listener para todos los botones (paginación OK)
    document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-ver-ficha');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    try {
        const d = btn.dataset;
        const tipoRaw = (d.tipo || '').toLowerCase();

        // Básicos
        if ($('f_dni'))     $('f_dni').textContent = d.dni || '—';
        if ($('f_op'))      $('f_op').textContent = d.operacion || '—';
        if ($('t_titular')) $('t_titular').textContent = readText(d.titular) || '—';
        if ($('t_deuda'))   $('t_deuda').textContent = `S/ ${money(d.deuda || 0)}`;
        if ($('t_neg'))     $('t_neg').textContent   = `S/ ${money(d.negociado || 0)}`;
        if ($('t_fecha'))   $('t_fecha').textContent = toDMY(d.fecha);
        if ($('t_asesor'))  $('t_asesor').textContent = readText(d.asesor) || 'No asignado';

        if ($('t_tipo')) $('t_tipo').textContent = (tipoRaw === 'cancelacion') ? 'Cancelación' : 'Convenio';

        // Notas (arregla unicode/quotes)
        const notaGen = readText(d.notaGen).trim();
        const notaSup = readText(d.notaSup).trim();

        if ($('nota_general_wrap')) {
        $('nota_general_wrap').classList.toggle('hidden', !notaGen);
        if (notaGen) $('nota_general_txt').textContent = notaGen;
        }

        if ($('nota_sup_wrap')) {
        $('nota_sup_wrap').classList.toggle('hidden', !notaSup);
        if (notaSup) $('nota_sup_txt').textContent = notaSup;
        }

        const notesGrid = document.getElementById('notes_grid');
        if (notesGrid) {
        const visibleCount = (notaGen ? 1 : 0) + (notaSup ? 1 : 0);
        notesGrid.classList.toggle('is-single', visibleCount === 1);
        }
        
        // Cuentas
        const cuentas = readArrayAttr(btn, 'data-cuentas');
        const acc = $('acc_cuentas');

        if (acc) {
        acc.innerHTML = !cuentas.length
            ? `<div class="kf-empty">No hay cuentas detalladas.</div>`
            : cuentas.map((c, i) => `
                <details ${i === 0 ? 'open' : ''}>
                <summary>
                    <div class="flex flex-col">
                    <span class="text-[11px] font-black text-slate-500 uppercase">OP. ${c.operacion || '—'}</span>
                    <span class="text-sm font-extrabold text-slate-900">${c.entidad || 'Entidad'}</span>
                    </div>
                    <div class="flex items-center gap-2">
                    <span class="text-indigo-700 font-black">S/ ${fmt2(c.deuda_total)}</span>
                    <svg class="w-4 h-4 text-slate-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-width="2" stroke-linecap="round" d="M19 9l-7 7-7-7"></path>
                    </svg>
                    </div>
                </summary>

                <div class="p-4 border-t border-slate-100 bg-white grid grid-cols-2 gap-3">
                    <div class="kf-kv">
                    <div class="kf-k">Producto</div>
                    <div class="kf-v">${c.producto || '—'}</div>
                    </div>
                    <div class="kf-kv">
                    <div class="kf-k">Cosecha</div>
                    <div class="kf-v">${c.cosecha || '—'}</div>
                    </div>
                    <div class="kf-kv col-span-2">
                    <div class="kf-k">Capital</div>
                    <div class="kf-v text-indigo-700 font-black">S/ ${fmt2(c.saldo_capital)}</div>
                    </div>
                </div>
                </details>
            `).join('');
        }

        // Cronograma (aquí se arregla “no sale”)
        const cuotas = readArrayAttr(btn, 'data-crono');
        const cronoWrap = $('crono_wrap');
        const tbody = $('crono_body');
        const empty = $('crono_empty');

        if (cronoWrap) cronoWrap.classList.remove('hidden');

        if ($('crono_total')) $('crono_total').textContent = money(d.negociado || 0);

        if (tipoRaw === 'cancelacion') {
        if (tbody) tbody.innerHTML = '';
        if (empty) { empty.classList.remove('hidden'); empty.textContent = 'No aplica cronograma porque es una cancelación.'; }
        } else if (!cuotas.length) {
        if (tbody) tbody.innerHTML = '';
        if (empty) { empty.classList.remove('hidden'); empty.textContent = 'No hay cronograma cargado para esta propuesta.'; }
        } else {
        if (empty) empty.classList.add('hidden');

        const total = cuotas.reduce((a, c) => a + (Number(c.monto) || 0), 0);
        if ($('crono_total')) $('crono_total').textContent = money(total);

        if (tbody) {
            tbody.innerHTML = cuotas.map(c => `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="text-center font-mono text-slate-500">${String(c.nro ?? '').padStart(2,'0')}</td>
                <td class="text-center font-semibold text-slate-800">${toDMY(c.fecha)}</td>
                <td class="text-end font-black text-slate-900">S/ ${fmt2(c.monto)}</td>
            </tr>
            `).join('');
        }
        }

    } catch (err) {
        console.error('Error al abrir ficha:', err);
    } finally {
        openModal('modalFicha');
    }
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