import { $, $$ } from '../../core/dom';

function hiddenOperation(operation) {
    const input = document.createElement('input');

    input.type = 'hidden';
    input.name = 'operaciones[]';
    input.value = operation;

    return input;
}

export function setupCnaForm(root) {
    const buttons = $$('.genCnaBtn', root);
    const operationsHidden = $('#cnaOpsHidden', root);
    const accountInput = $('#cnaCuentaInput', root);
    const oldSelection = $('#cnaOldSelection', root);

    const rows = () => $$('#tblCuentas tbody tr', root);
    const rowOperation = (row) => row?.dataset.oper || row?.querySelector('.genCnaBtn')?.dataset.oper || '';

    const populate = (button) => {
        const operation = button.dataset.oper || '';
        let account = button.dataset.cuenta || button.closest('tr')?.dataset.cuenta || '';

        if (!account && operation) {
            const row = rows().find((item) => rowOperation(item) === operation);
            account = row?.dataset.cuenta || row?.querySelector('.genCnaBtn')?.dataset.cuenta || '';
        }

        if (accountInput) accountInput.value = account;

        const accountOperations = rows()
            .filter((row) => (row.dataset.cuenta || '') === account)
            .map((row) => rowOperation(row))
            .filter(Boolean);
        const unique = [...new Set(accountOperations.length ? accountOperations : [operation].filter(Boolean))];

        if (operationsHidden) {
            operationsHidden.innerHTML = '';
            unique.forEach((item) => operationsHidden.appendChild(hiddenOperation(item)));
        }
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => populate(button));
    });

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
