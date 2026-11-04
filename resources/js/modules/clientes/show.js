import { $, $$, escapeHtml, ready } from '../../core/dom';
import { setupModals } from '../../core/modal';
import { formatMoney } from '../../core/money';
import { setupPromesaForm } from '../promesas/form';
import { setupPromiseSchedule } from '../promesas/schedule';
import { setupCnaForm } from '../cna/form';

function uniqueValues(rows, key) {
    return [...new Set(rows.map((row) => (row.dataset[key] || '').trim()).filter(Boolean))];
}

function isCanceledAssignment(value) {
    return String(value || '').toUpperCase().includes('CANCEL');
}

function cnaLabel(row) {
    const count = Number(row.dataset.cnaCount || 0);

    if (!count) return 'Sin CNA';

    const status = String(row.dataset.cnaStatus || '').toLowerCase();
    const label = status.includes('pre')
        ? 'Pre-aprobada'
        : status.includes('aprob')
            ? 'Aprobada'
            : status.includes('rechaz')
                ? 'Rechazada'
                : 'Pendiente';
    const number = row.dataset.cnaNro || 'CNA';

    return `${number} · ${label}`;
}

function setField(panel, name, value, title = null) {
    const field = panel.querySelector(`[data-op-field="${name}"]`);

    if (!field) return;

    field.textContent = value;
    field.setAttribute('title', title || value);
}

function setupOperationContext(root) {
    const panel = $('[data-operation-context]', root);
    const table = $('#tblCuentas', root);

    if (!panel || !table) return;

    const checks = $$('.chkOp', root);
    const countBadge = $('[data-op-context-count]', panel);
    const note = $('[data-op-context-note]', panel);

    function update() {
        const selected = checks
            .filter((input) => input.checked && !input.disabled)
            .map((input) => input.closest('tr'))
            .filter(Boolean);

        if (!selected.length) {
            if (countBadge) countBadge.textContent = 'Sin selección';
            setField(panel, 'operacion', '—');
            setField(panel, 'cuenta', '—');
            setField(panel, 'entidad', '—');
            setField(panel, 'cosecha', '—');
            setField(panel, 'asesor', '—');
            setField(panel, 'pagos', '—');
            setField(panel, 'cna', '—');
            setField(panel, 'total', '—');

            if (note) {
                note.textContent = 'Selecciona una o varias operaciones para revisar su cuenta, entidad, cosecha, asesor, pagos y CNA.';
            }

            return;
        }

        const operations = uniqueValues(selected, 'oper');
        const accounts = uniqueValues(selected, 'cuenta');
        const entities = uniqueValues(selected, 'entidad');
        const harvests = uniqueValues(selected, 'cosecha');
        const advisors = uniqueValues(selected, 'asesor');
        const currencies = uniqueValues(selected, 'moneda').map((currency) => currency.toUpperCase());
        const currency = currencies.length === 1 ? currencies[0] : (currencies.length === 0 ? 'PEN' : null);
        const activeAdvisors = advisors.filter((advisor) => !isCanceledAssignment(advisor));
        const canceledCount = selected.filter((row) => isCanceledAssignment(row.dataset.asesor)).length;
        const cnaIds = new Set();

        selected.forEach((row) => {
            (row.dataset.cnaIds || '')
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean)
                .forEach((id) => cnaIds.add(id));
        });

        const paymentCount = selected.reduce((carry, row) => carry + Number(row.dataset.pagosCount || 0), 0);
        const paymentSum = selected.reduce((carry, row) => carry + Number(row.dataset.pagosSum || 0), 0);
        const debtTotal = selected.reduce((carry, row) => carry + Number(row.dataset.total || 0), 0);

        if (countBadge) {
            countBadge.textContent = `${selected.length} ${selected.length === 1 ? 'operación' : 'operaciones'}`;
        }

        if (selected.length === 1) {
            const row = selected[0];
            const canceled = isCanceledAssignment(row.dataset.asesor);

            setField(panel, 'operacion', row.dataset.oper || 'Sin operación');
            setField(panel, 'cuenta', row.dataset.cuenta || 'Sin cuenta de deuda');
            setField(panel, 'entidad', row.dataset.entidad || 'Sin entidad');
            setField(panel, 'cosecha', row.dataset.cosecha || 'Sin cosecha');
            setField(panel, 'asesor', canceled ? 'En CANCELADOS (sin asesor activo)' : (row.dataset.asesor || 'Sin asignar'));
            setField(panel, 'pagos', `${paymentCount} ${paymentCount === 1 ? 'pago' : 'pagos'} · ${formatMoney(paymentSum, currency)}`);
            setField(panel, 'cna', cnaLabel(row));
            setField(panel, 'total', formatMoney(debtTotal, currency));

            if (note) {
                note.textContent = canceled
                    ? 'Esta operación está asignada a CANCELADOS. La deuda registrada se mantiene; CANCELADOS no equivale a deuda pagada.'
                    : 'El asesor, los pagos y la CNA mostrados corresponden únicamente a esta operación.';
            }

            return;
        }

        const advisorParts = [];

        if (activeAdvisors.length) {
            advisorParts.push(activeAdvisors.length === 1 ? activeAdvisors[0] : `${activeAdvisors.length} asesores activos`);
        }

        if (canceledCount > 0) {
            advisorParts.push(`${canceledCount} en CANCELADOS`);
        }

        setField(panel, 'operacion', `${operations.length} operaciones`, operations.join(', '));
        setField(panel, 'cuenta', accounts.length === 1 ? accounts[0] : `${accounts.length} cuentas de deuda`, accounts.join(', '));
        setField(panel, 'entidad', entities.length === 1 ? entities[0] : 'Varias entidades', entities.join(', '));
        setField(panel, 'cosecha', harvests.length === 1 ? harvests[0] : 'Varias cosechas', harvests.join(', '));
        setField(panel, 'asesor', advisorParts.length ? advisorParts.join(' · ') : 'Sin asignar', advisors.join(', '));
        setField(panel, 'pagos', `${paymentCount} ${paymentCount === 1 ? 'pago' : 'pagos'} · ${formatMoney(paymentSum, currency)}`);
        setField(panel, 'cna', cnaIds.size ? `${cnaIds.size} ${cnaIds.size === 1 ? 'solicitud' : 'solicitudes'}` : 'Sin CNA');
        setField(panel, 'total', formatMoney(debtTotal, currency));

        if (note) {
            note.textContent = canceledCount > 0
                ? 'La selección incluye operaciones en CANCELADOS. Esa etiqueta no equivale a deuda pagada; revisa cada fila antes de continuar.'
                : (activeAdvisors.length > 1 || harvests.length > 1 || entities.length > 1
                    ? 'La selección mezcla operaciones con distinta entidad, cosecha o asesor. Revisa cada fila antes de continuar.'
                    : 'Los totales y pagos mostrados corresponden solo a las operaciones seleccionadas.');

            if (currencies.length > 1) {
                note.textContent += ' La selección mezcla monedas; los importes se muestran sin conversión.';
            }
        }
    }

    checks.forEach((input) => {
        input.addEventListener('change', update);
    });

    $('#chkAll', root)?.addEventListener('change', () => {
        window.setTimeout(update, 0);
    });

    table.addEventListener('click', (event) => {
        if (event.target.closest('a, button, input, select, textarea, label')) return;

        const row = event.target.closest('tr');

        if (!row) return;

        const checkbox = row.querySelector('.chkOp');

        if (!checkbox || checkbox.disabled) return;

        checkbox.checked = !checkbox.checked;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    });

    update();
}

function setupToggleTargets(root) {
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-toggle-target]');

        if (!button) return;

        const target = $(button.dataset.toggleTarget, root);

        if (target) {
            target.classList.toggle('hidden');
        }
    });
}

function setupDeletePayments(root) {
    const toggle = $('#toggleDeletePagos', root);

    if (!toggle) return;

    const table = $('#tblPagos', root);
    const deleteButton = $('#btnDeletePagos', root);
    const checkAll = $('#chkAllPagos', root);

    function updateButton() {
        if (deleteButton) {
            deleteButton.disabled = !table?.querySelector('.chkPago:checked');
        }
    }

    function showColumns(active) {
        $$('.col-del', root).forEach((element) => element.classList.toggle('hidden', !active));

        if (!active) {
            if (checkAll) checkAll.checked = false;
            $$('.chkPago', root).forEach((input) => {
                input.checked = false;
            });
            updateButton();
        }
    }

    toggle.addEventListener('change', () => showColumns(toggle.checked));

    checkAll?.addEventListener('change', () => {
        $$('.chkPago', root).forEach((input) => {
            input.checked = checkAll.checked;
        });
        updateButton();
    });

    table?.addEventListener('change', (event) => {
        if (event.target.classList.contains('chkPago')) {
            updateButton();
        }
    });

    showColumns(false);
}

function setupConfirmForms(root) {
    $$('form[data-confirm]', root).forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm || 'Confirmar accion?')) {
                event.preventDefault();
            }
        });
    });
}

function setupOnceForms(root) {
    $$('form[data-once]', root).forEach((form) => {
        let locked = false;

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;

            if (locked) {
                event.preventDefault();
                return;
            }

            if (!form.checkValidity()) return;

            locked = true;
            form.setAttribute('aria-busy', 'true');

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                button.disabled = true;
                if (button.tagName === 'BUTTON') {
                    button.dataset.prev = button.textContent || '';
                    button.textContent = 'Enviando...';
                }
            });
        }, { capture: true });
    });
}

function setupAsignacionForm(root) {
    const button = $('#btnSolicitarAsignacion', root);
    const badge = $('#selCountAsignacion', root);
    const selectAll = $('#chkAll', root);
    const checks = $$('.chkOp', root);
    const summary = $('#asignacionOpsResumen', root);
    const hidden = $('#asignacionOpsHidden', root);
    const countNode = $('#asignacionOpsCount', root);
    const countLabel = $('#asignacionOpsCountLabel', root);
    const footerOps = $('#asignacionFooterOps', root);

    if (!button && !summary) return;

    function selectedDetails() {
        return checks
            .filter((input) => input.checked && !input.disabled)
            .map((input) => {
                const row = input.closest('tr');
                return {
                    operation: input.value,
                    account: row?.dataset.cuenta || '',
                    entity: row?.dataset.entidad || '',
                    harvest: row?.dataset.cosecha || '',
                    advisor: row?.dataset.asesor || 'Sin asignar',
                };
            })
            .filter((item) => item.operation);
    }

    function sync() {
        const details = selectedDetails();
        const count = details.length;

        if (badge) badge.textContent = String(count);
        if (button) button.disabled = count === 0;
        if (countNode) countNode.textContent = String(count);
        if (countLabel) countLabel.textContent = count === 1 ? 'operación' : 'operaciones';
        if (footerOps) {
            footerOps.textContent = `${count} ${count === 1 ? 'operación seleccionada' : 'operaciones seleccionadas'}`;
        }

        if (summary) {
            if (!count) {
                summary.innerHTML = '<p class="rounded-md border border-dashed border-kp-border bg-kp-surface-muted px-3 py-2 text-[11px] text-kp-muted sm:col-span-2">Ninguna operación seleccionada.</p>';
            } else {
                summary.innerHTML = details.map((detail) => {
                    const meta = [detail.entity, detail.harvest, `Actual: ${detail.advisor || 'Sin asignar'}`]
                        .filter(Boolean)
                        .map((val) => escapeHtml(val))
                        .join(' · ');

                    return `
                        <div class="flex min-w-0 items-start gap-2 rounded-md border border-kp-border bg-white px-2.5 py-2">
                            <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded bg-kp-primary-soft text-[9px] font-bold text-kp-primary-ink">OP</span>
                            <div class="min-w-0">
                                <p class="truncate text-[12px] font-semibold text-kp-ink">${escapeHtml(detail.operation)}</p>
                                <p class="mt-0.5 truncate text-[10.5px] text-kp-muted">${meta}</p>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        if (hidden) {
            hidden.innerHTML = '';
            details.forEach((detail) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'operaciones[]';
                input.value = detail.operation;
                hidden.appendChild(input);
            });
        }
    }

    checks.forEach((input) => input.addEventListener('change', sync));
    selectAll?.addEventListener('change', () => window.setTimeout(sync, 0));
    button?.addEventListener('click', sync);

    sync();
}

ready(() => {
    const root = $('[data-module="clientes-show"]');

    if (!root) return;

    setupToggleTargets(root);
    setupDeletePayments(root);
    setupConfirmForms(root);
    setupOperationContext(root);
    setupPromesaForm(root);
    setupAsignacionForm(root);
    setupPromiseSchedule(root);
    setupCnaForm(root);
    setupOnceForms(root);
    setupModals(root);
});
