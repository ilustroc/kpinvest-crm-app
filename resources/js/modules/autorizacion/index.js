import { $, $$, escapeHtml, ready } from '../../core/dom';
import { setupModals } from '../../core/modal';

function money(value) {
    return Number(String(value ?? 0).replaceAll(',', '') || 0).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value || '-';
    }
}

function parseJsonAttribute(element, name, fallback = []) {
    try {
        return JSON.parse(element.getAttribute(name) || JSON.stringify(fallback));
    } catch (_) {
        return fallback;
    }
}

function parseBase64JsonAttribute(element, name, fallback = []) {
    const encoded = element.getAttribute(name);

    if (!encoded) {
        return fallback;
    }

    try {
        const binary = atob(encoded);
        const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
        const decoded = new TextDecoder().decode(bytes);

        return JSON.parse(decoded);
    } catch (_) {
        return fallback;
    }
}

function toDmy(value) {
    if (!value) return '-';

    const text = String(value).trim();
    const datePart = text.split(' ')[0];
    const separator = datePart.includes('-') ? '-' : '/';
    const [year, month, day] = datePart.split(separator);

    if (year && month && day) {
        return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;
    }

    return text;
}

function setupDecisionModals(root) {
    $$('.js-open-nota', root).forEach((button) => {
        button.addEventListener('click', () => {
            const form = $('#formNotaEstado');
            const title = $('#modalNotaEstadoTitulo');
            const textarea = $('#notaEstadoTxt');

            form?.setAttribute('action', button.dataset.action || '#');

            if (title) {
                title.textContent = button.dataset.title || 'Agregar nota';
            }

            if (textarea) {
                textarea.value = '';
                setTimeout(() => textarea.focus(), 120);
            }
        });
    });

    $$('.js-open-rechazo', root).forEach((button) => {
        button.addEventListener('click', () => {
            const form = $('#formRechazo');
            const textarea = $('#motivoTxt');

            form?.setAttribute('action', button.dataset.action || '#');

            if (textarea) {
                textarea.value = '';
                setTimeout(() => textarea.focus(), 120);
            }
        });
    });
}

function accountsFromOperationText(operationText) {
    return String(operationText || '')
        .split(',')
        .map((operation) => operation.trim())
        .filter(Boolean)
        .map((operation) => ({
            operacion: operation,
            entidad: '',
            producto: '',
            cosecha: '',
            saldo_capital: 0,
            deuda_total: 0,
        }));
}

function uniqueOperations(values) {
    return [...new Set((values || [])
        .map((value) => String(value || '').trim())
        .filter(Boolean))];
}

function operationsFromPayments(payments) {
    return uniqueOperations((payments || []).map((payment) => payment?.operacion ?? payment?.oper));
}

function renderAccounts(accounts, operationText = '') {
    const container = $('#acc_cuentas');

    if (!container) return;

    const visibleAccounts = Array.isArray(accounts) && accounts.length
        ? accounts
        : accountsFromOperationText(operationText);

    container.innerHTML = visibleAccounts.map((account, index) => {
        const year = account?.fecha_castigo ? String(account.fecha_castigo).slice(0, 4) : '-';

        return `
            <details class="rounded-lg border border-kp-border bg-white" ${index === 0 ? 'open' : ''}>
                <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-kp-ink">
                    Operacion ${escapeHtml(account?.operacion || '-')} / ${escapeHtml(account?.entidad || '-')} / ${escapeHtml(account?.cosecha || '-')}
                </summary>
                <dl class="grid gap-2 border-t border-kp-border p-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-bold text-kp-muted">Anio Castigo</dt><dd>${escapeHtml(year)}</dd></div>
                    <div><dt class="font-bold text-kp-muted">Entidad</dt><dd>${escapeHtml(account?.entidad || '-')}</dd></div>
                    <div><dt class="font-bold text-kp-muted">Producto</dt><dd>${escapeHtml(account?.producto || '-')}</dd></div>
                    <div><dt class="font-bold text-kp-muted">Cosecha</dt><dd>${escapeHtml(account?.cosecha || '-')}</dd></div>
                    <div><dt class="font-bold text-kp-muted">Capital</dt><dd>S/ ${money(account?.saldo_capital)}</dd></div>
                    <div><dt class="font-bold text-kp-muted">Deuda Total</dt><dd>S/ ${money(account?.deuda_total)}</dd></div>
                </dl>
            </details>
        `;
    }).join('');
}

function renderSchedule(button, type) {
    const wrapper = $('#crono_wrap');
    const body = $('#crono_body');
    const total = $('#crono_total');
    const title = $('#crono_titulo');

    if (!wrapper || !body || !total || !title) return;

    const schedule = parseBase64JsonAttribute(
        button,
        'data-crono-b64',
        parseJsonAttribute(button, 'data-crono', []),
    );

    if (type === 'cancelacion') {
        wrapper.classList.add('hidden');
        return;
    }

    const hasBalloon = button.dataset.hasbalon === '1'
        || schedule.some((row) => row?.es_balon === true || row?.es_balon === 1 || row?.es_balon === '1');

    title.textContent = hasBalloon ? 'Cronograma de cuotas (con balon)' : 'Cronograma de cuotas';

    let sum = 0;

    body.innerHTML = schedule.map((row) => {
        const amount = Number(row?.monto || 0);
        sum += amount;

        return `
            <tr>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-center text-sm text-kp-ink">${escapeHtml(String(row?.nro ?? '').padStart(2, '0'))}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(row?.fecha || '-')}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-right text-sm text-kp-ink">${money(amount)}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${row?.es_balon ? 'BALON' : ''}</td>
            </tr>
        `;
    }).join('');

    total.textContent = money(sum);
    wrapper.classList.remove('hidden');
}

function setupPromiseDetail(root) {
    $$('.js-ver-ficha', root).forEach((button) => {
        button.addEventListener('click', () => {
            const type = (button.dataset.tipo || '').toLowerCase();

            setText('f_dni', button.dataset.dni || '-');
            setText('f_op', button.dataset.operacion || '-');
            setText('t_fecha', button.dataset.fecha || '-');
            setText('t_tipo', type ? (type === 'cancelacion' ? 'Cancelacion' : 'Convenio') : '-');
            setText('t_asesor', button.dataset.asesor || '-');
            setText('t_titular', button.dataset.titular || '-');
            setText('t_deuda', button.dataset.deuda || '-');
            setText('t_neg', button.dataset.negociado || '-');

            const noteGeneral = [button.dataset.notaGen, button.dataset.detalle, button.dataset.nota]
                .map((value) => (value || '').trim())
                .find(Boolean) || '';
            const noteSupervisor = [button.dataset.notaSup, button.dataset.notaPreaprobacion]
                .map((value) => (value || '').trim())
                .find(Boolean) || '';

            const generalWrapper = $('#nota_general_wrap');
            const supervisorWrapper = $('#nota_sup_wrap');

            if (generalWrapper) {
                generalWrapper.classList.toggle('hidden', !noteGeneral);
                setText('nota_general_txt', noteGeneral);
            }

            if (supervisorWrapper) {
                supervisorWrapper.classList.toggle('hidden', !noteSupervisor);
                setText('nota_sup_txt', noteSupervisor);
            }

            const accounts = parseBase64JsonAttribute(
                button,
                'data-cuentas-b64',
                parseJsonAttribute(button, 'data-cuentas', []),
            );

            renderAccounts(accounts, button.dataset.operacion || '');
            renderSchedule(button, type);
        });
    });
}

function renderEmptyPayments(message, tone = 'muted') {
    const body = $('#cna_pagos_tbody');
    const color = tone === 'danger' ? 'text-red-700' : 'text-kp-muted';

    if (!body) return;

    body.innerHTML = `<tr><td colspan="7" class="border-t border-kp-border px-4 py-6 text-center text-sm ${color}">${escapeHtml(message)}</td></tr>`;
}

function addPaymentRow(payment) {
    const body = $('#cna_pagos_tbody');

    if (!body) return;

    const date = payment.fecha ? new Date(payment.fecha).toLocaleDateString('es-PE') : '-';

    body.insertAdjacentHTML('beforeend', `
        <tr>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(payment.operacion ?? payment.oper ?? '-')}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(date)}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-right text-sm text-kp-ink">${money(payment.monto_pagado ?? payment.monto ?? 0)}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(payment.gestor ?? '-')}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(payment.entidad ?? '-')}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(payment.cosecha ?? '-')}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(payment.cuenta_recaudo ?? payment.cuenta ?? '-')}</td>
        </tr>
    `);
}

function setupCnaDetail(root) {
    $$('.js-ver-cna', root).forEach((button) => {
        button.addEventListener('click', async () => {
            const dni = (button.dataset.dni || '').trim();
            const body = $('#cna_pagos_tbody');
            const routeTemplate = root.dataset.pagosUrlTemplate || '';

            setText('cna_dni', dni || '-');
            setText('cna_carta', button.dataset.nrocarta || '-');
            setText('cna_fecha', toDmy(button.dataset.fecha));
            setText('cna_fecha_pago', toDmy(button.dataset.fechaPago));
            setText('cna_monto_pagado', money(button.dataset.montoPagado));
            setText('cna_obs', button.dataset.observacion || '-');

            let operations = uniqueOperations(parseBase64JsonAttribute(
                button,
                'data-operaciones-b64',
                parseJsonAttribute(button, 'data-operaciones', []),
            ));
            setText('cna_ops', operations.length ? operations.join(', ') : '-');

            if (body) {
                body.innerHTML = '<tr><td colspan="7" class="border-t border-kp-border px-4 py-6 text-center text-sm text-kp-muted">Cargando...</td></tr>';
            }

            let total = 0;

            try {
                const url = routeTemplate.replace('__DNI__', encodeURIComponent(dni || ''));
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const json = await response.json();
                const payments = Array.isArray(json.pagos) ? json.pagos : [];
                const paymentOperations = operationsFromPayments(payments);

                if (!operations.length && paymentOperations.length) {
                    operations = paymentOperations;
                    setText('cna_ops', operations.join(', '));
                }

                if (!payments.length) {
                    renderEmptyPayments('Sin pagos registrados.');
                } else if (body) {
                    body.innerHTML = '';
                    payments.forEach((payment) => {
                        total += Number(String(payment.monto_pagado ?? payment.monto ?? 0).replaceAll(',', '')) || 0;
                        addPaymentRow(payment);
                    });
                }
            } catch (error) {
                console.error('CNA pagos fetch error:', error);
                renderEmptyPayments('Error cargando pagos.', 'danger');
            }

            setText('cna_total_pagos', `S/ ${money(total)}`);
        });
    });
}

ready(() => {
    const root = $('[data-module="autorizacion-index"]');

    if (!root) return;

    setupDecisionModals(root);
    setupPromiseDetail(root);
    setupCnaDetail(root);
    setupModals(root);
});
