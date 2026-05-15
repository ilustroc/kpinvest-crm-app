import { $, $$, escapeHtml } from '../../core/dom';

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

    return { origin: '(no reconocido)', series: '-', template: '-' };
}

function hiddenOperation(operation) {
    const input = document.createElement('input');

    input.type = 'hidden';
    input.name = 'operaciones[]';
    input.value = operation;

    return input;
}

function chip(operation) {
    return `<span class="inline-flex items-center rounded-full border border-kp-border bg-white px-2.5 py-1 text-xs font-bold text-kp-ink">${escapeHtml(operation)}</span>`;
}

export function setupCnaForm(root) {
    const buttons = $$('.genCnaBtn', root);
    const operationsHidden = $('#cnaOpsHidden', root);
    const operationsList = $('#cnaOpsList', root);
    const accountInput = $('#cnaCuentaInput', root);
    const accountLabel = $('#cnaCuenta', root);
    const harvestLabel = $('#cnaCosecha', root);
    const templateLabel = $('#cnaPlantilla', root);

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const operation = button.dataset.oper || '';
            let account = button.dataset.cuenta || button.closest('tr')?.dataset.cuenta || '';
            const harvest = button.dataset.cosecha || '';

            if (!account && operation) {
                const row = $$('#tblCuentas tbody tr', root)
                    .find((item) => (item.dataset.oper || '') === operation);
                account = row?.dataset.cuenta || row?.querySelector('.genCnaBtn')?.dataset.cuenta || '';
            }

            if (accountInput) accountInput.value = account;
            if (accountLabel) accountLabel.textContent = account || '-';
            if (harvestLabel) harvestLabel.textContent = harvest || '-';

            const info = originFromHarvest(harvest);

            if (templateLabel) {
                templateLabel.textContent = `${info.origin} / Serie ${info.series} / ${info.template}`;
            }

            const operations = $$('#tblCuentas tbody tr', root)
                .filter((row) => (row.dataset.cuenta || '') === account)
                .map((row) => row.dataset.oper || row.querySelector('.genCnaBtn')?.dataset.oper || '')
                .filter(Boolean);
            const unique = [...new Set(operations.length ? operations : [operation].filter(Boolean))];

            if (operationsList) {
                operationsList.innerHTML = unique.map(chip).join('');
            }

            if (operationsHidden) {
                operationsHidden.innerHTML = '';
                unique.forEach((item) => operationsHidden.appendChild(hiddenOperation(item)));
            }
        });
    });
}
