import { $, $$, escapeHtml } from '../../core/dom';
import { formatMoney } from '../../core/money';

function originFromHarvest(value) {
    const harvest = String(value || '').toUpperCase().trim();
    const faa = new Set(['BBVA3', 'BBVA4', 'BBVA5', 'BBVA6', 'CAJAAQP3']);
    const faa2 = new Set(['BBVA7', 'BBVA8', 'CONFIANZA_5']);
    const kpi = new Set([
        'BBVA1', 'BBVA2', 'CAJAAQP1', 'CAJAAQP2', 'CAJAAQP4', 'CAJAAQP5',
        'COMPARTAMOS_1', 'COMPARTAMOS_2', 'CONFIANZA', 'CONFIANZA_2', 'CONFIANZA_3',
        'CONFIANZA_4', 'CONFIANZA_6', 'CONFIANZA_7', 'CONFIANZA_8', 'CONFIANZA_9',
        'CONFIANZA_10', 'CONFIANZA_11', 'CONFIANZA_12', 'SEMBRANDO', 'WANDOO_1',
    ]);

    if (faa.has(harvest)) return { origin: 'FONDO ACREENCIA AREQUIPA', series: 'F', template: 'cna_fondo_acreencia_arequipa.docx' };
    if (faa2.has(harvest)) return { origin: 'ACREENCIA II', series: 'F2', template: 'cna_fondo_acreencia_arequipa_2.docx' };
    if (kpi.has(harvest)) return { origin: 'KP INVEST SAC', series: 'KPI', template: 'cna_kpinvest.docx' };

    return { origin: 'No reconocido', series: '-', template: '-' };
}

function hiddenOperation(operation) {
    const input = document.createElement('input');

    input.type = 'hidden';
    input.name = 'operaciones[]';
    input.value = operation;

    return input;
}

function operationItem(operation, product) {
    const productLabel = String(product || '').trim();

    return `
        <div class="flex min-w-0 items-center justify-between gap-2 rounded-md border border-kp-border bg-white px-2.5 py-2">
            <span class="font-mono text-[11.5px] font-semibold text-kp-ink">${escapeHtml(operation)}</span>
            ${productLabel ? `<span class="max-w-[48%] truncate text-[10px] text-kp-muted" title="${escapeHtml(productLabel)}">${escapeHtml(productLabel)}</span>` : ''}
        </div>
    `;
}

export function setupCnaForm(root) {
    const buttons = $$('.genCnaBtn', root);
    const operationsHidden = $('#cnaOpsHidden', root);
    const operationsList = $('#cnaOpsList', root);
    const operationsCount = $('#cnaOpsCount', root);
    const accountInput = $('#cnaCuentaInput', root);
    const accountLabel = $('#cnaCuenta', root);
    const entityLabel = $('#cnaEntidad', root);
    const harvestLabel = $('#cnaCosecha', root);
    const originLabel = $('#cnaOrigen', root);
    const seriesLabel = $('#cnaSerie', root);
    const templateLabel = $('#cnaPlantilla', root);
    const paymentsCount = $('#cnaPagosCount', root);
    const paymentsSum = $('#cnaPagosSum', root);
    const footerOps = $('#cnaFooterOps', root);
    const footerAmount = $('#cnaFooterMonto', root);
    const amountInput = $('#monto_pagado', root);
    const amountPreview = $('#cnaMontoPreview', root);
    const oldSelection = $('#cnaOldSelection', root);

    let currentCurrency = 'PEN';

    const rows = () => $$('#tblCuentas tbody tr', root);
    const rowOperation = (row) => row?.dataset.oper || row?.querySelector('.genCnaBtn')?.dataset.oper || '';

    const updateAmountPreview = () => {
        const text = formatMoney(amountInput?.value || 0, currentCurrency);

        if (amountPreview) amountPreview.textContent = text;
        if (footerAmount) footerAmount.textContent = text;
    };

    const populate = (button) => {
        const operation = button.dataset.oper || '';
        let account = button.dataset.cuenta || button.closest('tr')?.dataset.cuenta || '';
        const entity = button.dataset.entidad || button.closest('tr')?.dataset.entidad || '';
        const harvest = button.dataset.cosecha || button.closest('tr')?.dataset.cosecha || '';
        const currency = (button.dataset.moneda || button.closest('tr')?.dataset.moneda || 'PEN').toUpperCase();

        currentCurrency = currency || 'PEN';

        if (!account && operation) {
            const row = rows().find((item) => rowOperation(item) === operation);
            account = row?.dataset.cuenta || row?.querySelector('.genCnaBtn')?.dataset.cuenta || '';
        }

        if (accountInput) accountInput.value = account;
        if (accountLabel) accountLabel.textContent = account || 'Sin cuenta de deuda';
        if (entityLabel) entityLabel.textContent = entity || 'Sin entidad';
        if (harvestLabel) harvestLabel.textContent = harvest || 'Sin cosecha';

        const info = originFromHarvest(harvest);

        if (originLabel) originLabel.textContent = info.origin;
        if (seriesLabel) seriesLabel.textContent = info.series;
        if (templateLabel) templateLabel.textContent = info.template;

        const accountOperations = rows()
            .filter((row) => (row.dataset.cuenta || '') === account)
            .map((row) => rowOperation(row))
            .filter(Boolean);
        const unique = [...new Set(accountOperations.length ? accountOperations : [operation].filter(Boolean))];

        if (operationsList) {
            operationsList.innerHTML = unique.length
                ? unique.map((item) => operationItem(
                    item,
                    rows().find((row) => rowOperation(row) === item)?.dataset.producto || '',
                )).join('')
                : '<span class="text-[11px] text-kp-muted">Selecciona una operación en la tabla.</span>';
        }

        if (operationsCount) operationsCount.textContent = String(unique.length);

        if (operationsHidden) {
            operationsHidden.innerHTML = '';
            unique.forEach((item) => operationsHidden.appendChild(hiddenOperation(item)));
        }

        const operationSet = new Set(unique);
        const includedRows = rows().filter((row) => operationSet.has(rowOperation(row)));
        const paymentCount = includedRows.reduce((sum, row) => sum + Number(row.dataset.pagosCount || 0), 0);
        const paymentTotal = includedRows.reduce((sum, row) => sum + Number(row.dataset.pagosSum || 0), 0);

        if (paymentsCount) paymentsCount.textContent = `${paymentCount} ${paymentCount === 1 ? 'pago' : 'pagos'}`;
        if (paymentsSum) paymentsSum.textContent = formatMoney(paymentTotal, currentCurrency);
        if (footerOps) footerOps.textContent = `${unique.length} ${unique.length === 1 ? 'operación incluida' : 'operaciones incluidas'}`;

        updateAmountPreview();
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => populate(button));
    });

    amountInput?.addEventListener('input', updateAmountPreview);
    updateAmountPreview();

    if (oldSelection) {
        let oldOperations = [];

        try {
            oldOperations = JSON.parse(oldSelection.dataset.operaciones || '[]');
        } catch (_) {
            oldOperations = [];
        }

        const firstOperation = (Array.isArray(oldOperations) ? oldOperations : [])
            .map((value) => String(value || '').trim())
            .find(Boolean);

        if (firstOperation) {
            const row = rows().find((item) => rowOperation(item) === firstOperation);
            const button = row?.querySelector('.genCnaBtn');

            if (button) populate(button);
        }
    }
}
