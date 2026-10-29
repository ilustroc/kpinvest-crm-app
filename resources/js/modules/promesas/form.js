import { $, $$, escapeHtml } from '../../core/dom';
import { formatMoney } from '../../core/money';
import { notify } from '../../core/toast';

function numberValue(value) {
    const parsed = parseFloat(String(value ?? '').replace(/[^\d,.-]/g, '').replaceAll(',', ''));

    return Number.isNaN(parsed) ? 0 : parsed;
}

function fixed(value) {
    return (Math.round((Number(value) || 0) * 100) / 100).toFixed(2);
}

function displayMoney(value) {
    return formatMoney(value, 'PEN');
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

function operationCard(detail) {
    const meta = [detail.account, detail.entity, detail.harvest]
        .filter(Boolean)
        .map((value) => escapeHtml(value))
        .join(' · ');

    return `
        <div class="flex min-w-0 items-start gap-2 rounded-md border border-kp-border bg-white px-2.5 py-2">
            <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded bg-kp-primary-soft text-[9px] font-bold text-kp-primary-ink">OP</span>
            <div class="min-w-0">
                <p class="truncate text-[12px] font-semibold text-kp-ink">${escapeHtml(detail.operation)}</p>
                <p class="mt-0.5 truncate text-[10.5px] text-kp-muted">${meta || 'Sin cuenta asociada'}</p>
            </div>
        </div>
    `;
}

function emptySelection() {
    return '<p class="rounded-md border border-dashed border-kp-border bg-kp-surface-muted px-3 py-2 text-[11px] text-kp-muted sm:col-span-2">Ninguna operación seleccionada.</p>';
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
    const diffNode = $('#cvDiff', root);
    const scheduleCount = $('#cvScheduleCount', root);
    const hidden = $('#cvHidden', root);
    const dayHint = $('#cvHintDia', root);
    const form = $('#formPropuesta', root);
    const submitButton = form?.querySelector('button[type="submit"]');
    const balloonInput = $('#cronBalon', root);
    const balloonHint = $('#hintBalon', root);
    const selectAll = $('#chkAll', root);
    const operationChecks = $$('.chkOp', root);
    const proposalButton = $('#btnPropuesta', root);
    const selectionCount = $('#selCount', root);
    const operationsSummary = $('#opsResumen', root);
    const operationsHidden = $('#opsHidden', root);
    const operationsCount = $('#opsCount', root);
    const operationsCountLabel = $('#opsCountLabel', root);

    const selectedOperations = () => operationChecks
        .filter((input) => input.checked && !input.disabled)
        .map((input) => input.value)
        .filter(Boolean);

    const selectedOperationDetails = () => operationChecks
        .filter((input) => input.checked && !input.disabled)
        .map((input) => {
            const row = input.closest('tr');

            return {
                operation: input.value,
                account: row?.dataset.cuenta || '',
                entity: row?.dataset.entidad || '',
                harvest: row?.dataset.cosecha || '',
            };
        })
        .filter((detail) => detail.operation);

    function isConventionType() {
        const type = String(typeSelect?.value || '').toLowerCase();

        return type === 'convenio' || type === 'convenio_balon';
    }

    function renderSelectionSummary(details) {
        if (operationsSummary) {
            operationsSummary.innerHTML = details.length
                ? details.map(operationCard).join('')
                : emptySelection();
        }

        if (operationsCount) {
            operationsCount.textContent = String(details.length);
        }

        if (operationsCountLabel) {
            operationsCountLabel.textContent = details.length === 1 ? 'operación' : 'operaciones';
        }
    }

    function refreshSelection() {
        const details = selectedOperationDetails();
        const selected = details.map((detail) => detail.operation);

        if (selectionCount) selectionCount.textContent = String(selected.length);
        if (proposalButton) proposalButton.disabled = selected.length === 0;

        renderSelectionSummary(details);

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

    function rowStates() {
        return Array.from(tableBody?.querySelectorAll('tr') || []).map((row) => ({
            date: row.querySelector('.cr-fecha')?.value || '',
            amount: row.querySelector('.cr-monto')?.value || '',
        }));
    }

    function updateScheduleMeta() {
        const rows = Array.from(tableBody?.querySelectorAll('tr') || []);
        const type = String(typeSelect?.value || '').toLowerCase();
        const isBalloonType = type === 'convenio_balon';
        const ballonRow = rows.length;

        if (scheduleCount) {
            scheduleCount.textContent = `${rows.length} ${rows.length === 1 ? 'cuota' : 'cuotas'}`;
        }

        if (balloonInput) {
            balloonInput.value = isBalloonType && ballonRow > 0 ? String(ballonRow) : '';
        }

        if (balloonHint) {
            balloonHint.classList.toggle('hidden', !isBalloonType);
        }

        rows.forEach((row, index) => {
            const badge = row.querySelector('.cr-tipo');

            if (!badge) return;

            const isBalloon = isBalloonType && index === ballonRow - 1;

            badge.textContent = isBalloon ? 'Balón' : 'Regular';
            badge.className = isBalloon
                ? 'cr-tipo inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700'
                : 'cr-tipo inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-500';
        });
    }

    function recalc() {
        if (!tableBody) return;

        const rows = Array.from(tableBody.querySelectorAll('tr'));
        const conventionTotal = numberValue(total?.value);
        const convention = isConventionType();
        let sum = 0;

        if (hidden) hidden.innerHTML = '';

        rows.forEach((row) => {
            const date = row.querySelector('.cr-fecha')?.value || '';
            const amount = row.querySelector('.cr-monto')?.value || '';

            sum += numberValue(amount);

            hidden?.appendChild(createHidden('cron_fecha[]', date));
            hidden?.appendChild(createHidden('cron_monto[]', amount));
        });

        if (sumNode) sumNode.textContent = displayMoney(sum);

        const ok = !convention || Math.abs(sum - conventionTotal) <= 0.01;

        if (submitButton) submitButton.disabled = convention ? !ok : false;
        if (sumNode) sumNode.classList.toggle('text-red-700', convention && !ok);

        if (error) {
            error.classList.toggle('hidden', !convention || ok);
        }

        if (diffNode) {
            if (!convention) {
                diffNode.textContent = 'Disponible al seleccionar un convenio.';
                diffNode.className = 'text-[10.5px] font-medium text-kp-muted';
            } else if (conventionTotal <= 0) {
                diffNode.textContent = 'Captura el monto convenio para validar el cronograma.';
                diffNode.className = 'text-[10.5px] font-medium text-kp-muted';
            } else {
                const difference = sum - conventionTotal;

                if (Math.abs(difference) <= 0.01) {
                    diffNode.textContent = 'Coincide con el monto convenio.';
                    diffNode.className = 'text-[10.5px] font-medium text-kp-success-ink';
                } else if (difference > 0) {
                    diffNode.textContent = `Excedente: ${displayMoney(difference)}`;
                    diffNode.className = 'text-[10.5px] font-medium text-red-700';
                } else {
                    diffNode.textContent = `Faltante: ${displayMoney(Math.abs(difference))}`;
                    diffNode.className = 'text-[10.5px] font-medium text-red-700';
                }
            }
        }

        updateScheduleMeta();
    }

    function renderRows(count, preserve = true) {
        if (!tableBody) return;

        const rows = Math.max(1, parseInt(count || '1', 10));
        const previous = preserve ? rowStates() : [];

        tableBody.innerHTML = '';

        for (let index = 1; index <= rows; index += 1) {
            const row = document.createElement('tr');
            const previousRow = previous[index - 1] || {};

            row.innerHTML = `
                <td class="whitespace-nowrap border-t border-kp-border px-3 py-2 text-center text-[12px] font-medium tabular-nums text-slate-500">${String(index).padStart(2, '0')}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-3 py-2">
                    <input type="date" class="cr-fecha h-8 w-full min-w-[150px] rounded-md border border-kp-border bg-white px-2.5 text-[12px] text-kp-ink shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition hover:border-slate-300 kp-focus">
                </td>
                <td class="whitespace-nowrap border-t border-kp-border px-3 py-2">
                    <div class="relative min-w-[110px]">
                        <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-slate-400">S/</span>
                        <input type="number" step="0.01" min="0.01" inputmode="decimal" class="cr-monto h-8 w-full rounded-md border border-kp-border bg-white pl-7 pr-2 text-right text-[12px] tabular-nums text-kp-ink shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition hover:border-slate-300 kp-focus">
                    </div>
                </td>
                <td class="whitespace-nowrap border-t border-kp-border px-3 py-2 text-center">
                    <span class="cr-tipo inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-500">Regular</span>
                </td>
            `;

            const dateInput = row.querySelector('.cr-fecha');
            const amountInput = row.querySelector('.cr-monto');

            if (dateInput) dateInput.value = previousRow.date || '';
            if (amountInput) amountInput.value = previousRow.amount || '';

            tableBody.appendChild(row);
        }

        updateScheduleMeta();
        recalc();
    }

    function applyTypeUi() {
        const type = String(typeSelect?.value || '').toLowerCase();
        const convention = type === 'convenio' || type === 'convenio_balon';

        conventionBox?.classList.toggle('hidden', !convention);
        cancelBox?.classList.toggle('hidden', convention);

        setRequired('[name="nro_cuotas"]', convention);
        setRequired('[name="monto_convenio"]', convention);
        setRequired('[name="fecha_pago_cancel"]', !convention);
        setRequired('[name="monto_cancel"]', !convention);

        if (typeTag) {
            typeTag.textContent = typeSelect?.selectedOptions?.[0]?.textContent?.trim() || 'Convenio';
        }

        if (convention) {
            if (tableBody && tableBody.children.length === 0) {
                renderRows(quotaCount?.value || 1, false);
            } else {
                recalc();
            }
        } else {
            if (error) error.classList.add('hidden');
            if (sumNode) sumNode.classList.remove('text-red-700');
            if (submitButton) submitButton.disabled = false;

            if (diffNode) {
                diffNode.textContent = 'Disponible al seleccionar un convenio.';
                diffNode.className = 'text-[10.5px] font-medium text-kp-muted';
            }

            updateScheduleMeta();
        }
    }

    function generateSchedule() {
        if (!tableBody) return;

        const rowsCount = Math.max(1, parseInt(quotaCount?.value || '1', 10));

        renderRows(rowsCount, true);

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
    quotaCount?.addEventListener('change', () => renderRows(quotaCount.value, true));
    table?.addEventListener('input', (event) => {
        if (event.target.matches('.cr-monto, .cr-fecha')) recalc();
    });
    total?.addEventListener('input', recalc);
    startDate?.addEventListener('change', () => {
        if (!dayHint) return;

        if (!startDate.value) {
            dayHint.textContent = 'Día de pago: -';
            return;
        }

        const date = new Date(`${startDate.value}T00:00:00`);
        dayHint.textContent = `Día de pago: ${date.getDate()} de cada mes`;
    });

    form?.addEventListener('submit', (event) => {
        const type = String(typeSelect?.value || '').toLowerCase();
        const convention = type === 'convenio' || type === 'convenio_balon';

        if (balloonInput) {
            const rows = tableBody?.querySelectorAll('tr').length || 0;
            balloonInput.value = type === 'convenio_balon' && rows ? String(rows) : '';
        }

        if (!convention) return;

        recalc();

        const conventionTotal = numberValue(total?.value);
        const rows = tableBody ? Array.from(tableBody.querySelectorAll('tr')) : [];
        const sum = rows.reduce((carry, row) => carry + numberValue(row.querySelector('.cr-monto')?.value), 0);

        if (Math.abs(sum - conventionTotal) > 0.01) {
            event.preventDefault();
            event.stopPropagation();
            error?.classList.remove('hidden');
            sumNode?.classList.add('text-red-700');
            notify('No se puede guardar: el total del cronograma debe coincidir con el Monto convenio.', 'warning');
        }
    }, true);

    refreshSelection();
    applyTypeUi();
}
