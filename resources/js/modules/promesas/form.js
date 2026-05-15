import { $, $$, escapeHtml } from '../../core/dom';

function numberValue(value) {
    const parsed = parseFloat(String(value ?? '').replace(/[^\d,.-]/g, '').replaceAll(',', ''));

    return Number.isNaN(parsed) ? 0 : parsed;
}

function fixed(value) {
    return (Math.round((Number(value) || 0) * 100) / 100).toFixed(2);
}

function addMonthsNoOverflow(base, months) {
    const date = new Date(base);
    const day = date.getDate();

    date.setMonth(date.getMonth() + months);

    if (date.getDate() !== day) {
        date.setDate(0);
    }

    return date;
}

function createHidden(name, value) {
    const input = document.createElement('input');

    input.type = 'hidden';
    input.name = name;
    input.value = String(value || '');

    return input;
}

function chip(value) {
    return `<span class="inline-flex items-center rounded-full border border-kp-border bg-white px-2.5 py-1 text-xs font-bold text-kp-ink">${escapeHtml(value)}</span>`;
}

export function setupPromesaForm(root) {
    const typeSelect = $('#tipoPropuesta', root);
    const typeTag = $('#modalTipoTag', root);
    const conventionBox = $('#formConvenio', root);
    const cancelBox = $('#formCancelacion', root);
    const error = $('#cvErr', root);
    const quotaCount = $('#cvNro', root);
    const total = $('#cvTotal', root);
    const quotaAmount = $('#cvCuota', root);
    const startDate = $('#cvFechaIni', root);
    const generate = $('#cvGen', root);
    const table = $('#tblCrono', root);
    const tableBody = table?.querySelector('tbody');
    const sumNode = $('#cvSuma', root);
    const hidden = $('#cvHidden', root);
    const dayHint = $('#cvHintDia', root);
    const form = $('#formPropuesta', root);
    const submitButton = form?.querySelector('button[type="submit"]');
    const balloonInput = $('#cronBalon', root);
    const selectAll = $('#chkAll', root);
    const operationChecks = $$('.chkOp', root);
    const proposalButton = $('#btnPropuesta', root);
    const selectionCount = $('#selCount', root);
    const operationsSummary = $('#opsResumen', root);
    const operationsHidden = $('#opsHidden', root);

    const selectedOperations = () => operationChecks
        .filter((input) => input.checked && !input.disabled)
        .map((input) => input.value)
        .filter(Boolean);

    function refreshSelection() {
        const selected = selectedOperations();

        if (selectionCount) selectionCount.textContent = String(selected.length);
        if (proposalButton) proposalButton.disabled = selected.length === 0;

        return selected;
    }

    function setRequired(selector, active) {
        const element = root.querySelector(selector);

        if (!element) return;

        if (active) {
            element.setAttribute('required', 'required');
        } else {
            element.removeAttribute('required');
        }
    }

    function recalc() {
        if (!tableBody) return;

        const rows = Array.from(tableBody.querySelectorAll('tr'));
        const conventionTotal = numberValue(total?.value);
        let sum = 0;

        if (hidden) hidden.innerHTML = '';

        rows.forEach((row) => {
            const date = row.querySelector('.cr-fecha')?.value || '';
            const amount = row.querySelector('.cr-monto')?.value || '';

            sum += numberValue(amount);

            hidden?.appendChild(createHidden('cron_fecha[]', date));
            hidden?.appendChild(createHidden('cron_monto[]', amount));
        });

        if (sumNode) sumNode.textContent = fixed(sum);

        const ok = Math.abs(sum - conventionTotal) <= 0.01;

        if (submitButton) submitButton.disabled = !ok;
        if (sumNode) sumNode.classList.toggle('text-red-700', !ok);
        if (error) error.classList.toggle('hidden', ok);
    }

    function renderRows(count) {
        if (!tableBody) return;

        const rows = Math.max(1, parseInt(count || '1', 10));

        tableBody.innerHTML = '';

        for (let index = 1; index <= rows; index += 1) {
            const row = document.createElement('tr');

            row.innerHTML = `
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-center text-sm text-kp-ink">${String(index).padStart(2, '0')}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">
                    <input type="date" class="cr-fecha w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus">
                </td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">
                    <input type="number" step="0.01" min="0.01" class="cr-monto w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus">
                </td>
            `;

            tableBody.appendChild(row);
        }

        recalc();
    }

    function applyTypeUi() {
        const type = String(typeSelect?.value || '').toLowerCase();
        const isConvention = type === 'convenio' || type === 'convenio_balon';

        conventionBox?.classList.toggle('hidden', !isConvention);
        cancelBox?.classList.toggle('hidden', isConvention);

        setRequired('[name="nro_cuotas"]', isConvention);
        setRequired('[name="monto_convenio"]', isConvention);
        setRequired('[name="fecha_pago_cancel"]', !isConvention);
        setRequired('[name="monto_cancel"]', !isConvention);

        if (typeTag) {
            typeTag.textContent = typeSelect?.selectedOptions?.[0]?.textContent?.trim() || 'Convenio';
        }

        if (isConvention) {
            renderRows(quotaCount?.value || 1);
        } else if (submitButton) {
            submitButton.disabled = false;
        }
    }

    function generateSchedule() {
        if (!tableBody) return;

        const rowsCount = Math.max(1, parseInt(quotaCount?.value || '1', 10));

        renderRows(rowsCount);

        const start = startDate?.value ? new Date(`${startDate.value}T00:00:00`) : null;
        const conventionTotal = numberValue(total?.value);
        const requestedQuota = numberValue(quotaAmount?.value);
        const rows = Array.from(tableBody.querySelectorAll('tr'));

        rows.forEach((row, index) => {
            const date = row.querySelector('.cr-fecha');
            const amount = row.querySelector('.cr-monto');

            if (start && date) {
                date.valueAsDate = addMonthsNoOverflow(start, index);
            }

            if (amount) {
                amount.value = fixed(requestedQuota > 0 ? requestedQuota : conventionTotal / rows.length);
            }
        });

        recalc();
    }

    function prepareProposalModal() {
        const selected = refreshSelection();

        if (operationsSummary) {
            operationsSummary.innerHTML = selected.length
                ? selected.map(chip).join('')
                : '<span class="text-kp-muted">Ninguna</span>';
        }

        if (operationsHidden) {
            operationsHidden.innerHTML = '';
            selected.forEach((operation) => {
                operationsHidden.appendChild(createHidden('operaciones[]', operation));
            });
        }

        total?.dispatchEvent(new Event('input'));
        applyTypeUi();
    }

    selectAll?.addEventListener('change', () => {
        operationChecks.forEach((input) => {
            if (!input.disabled) input.checked = selectAll.checked;
        });
        refreshSelection();
    });

    operationChecks.forEach((input) => {
        input.addEventListener('change', () => {
            const enabled = operationChecks.filter((item) => !item.disabled).length;
            const checked = operationChecks.filter((item) => item.checked && !item.disabled).length;

            if (enabled && selectAll) {
                selectAll.checked = checked === enabled;
            }

            refreshSelection();
        });
    });

    proposalButton?.addEventListener('click', prepareProposalModal);
    typeSelect?.addEventListener('change', applyTypeUi);
    generate?.addEventListener('click', generateSchedule);
    quotaCount?.addEventListener('change', () => renderRows(quotaCount.value));
    table?.addEventListener('input', (event) => {
        if (event.target.matches('.cr-monto, .cr-fecha')) recalc();
    });
    total?.addEventListener('input', recalc);
    startDate?.addEventListener('change', () => {
        if (!dayHint) return;

        if (!startDate.value) {
            dayHint.textContent = 'Dia de pago: -';
            return;
        }

        const date = new Date(`${startDate.value}T00:00:00`);
        dayHint.textContent = `Dia de pago: ${date.getDate()} de cada mes`;
    });

    form?.addEventListener('submit', (event) => {
        const type = String(typeSelect?.value || '').toLowerCase();
        const isConvention = type === 'convenio' || type === 'convenio_balon';

        if (balloonInput) {
            const rows = tableBody?.querySelectorAll('tr').length || 0;
            balloonInput.value = type === 'convenio_balon' && rows ? String(rows) : '';
        }

        if (!isConvention) return;

        recalc();

        const conventionTotal = numberValue(total?.value);
        const rows = tableBody ? Array.from(tableBody.querySelectorAll('tr')) : [];
        const sum = rows.reduce((carry, row) => carry + numberValue(row.querySelector('.cr-monto')?.value), 0);

        if (Math.abs(sum - conventionTotal) > 0.01) {
            event.preventDefault();
            event.stopPropagation();
            error?.classList.remove('hidden');
            sumNode?.classList.add('text-red-700');
            window.alert('No se puede guardar: el total del cronograma debe coincidir con el Monto convenio.');
        }
    }, true);

    refreshSelection();
    applyTypeUi();
}
