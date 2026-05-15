import { $, $$, escapeHtml, ready } from '../../core/dom';

const PAYMENT_HEADERS = [
    'Fecha',
    'DNI',
    'Nombre',
    'Operacion',
    'Monto',
    'Agente',
    'Cosecha',
    'Cuenta_Recaudo',
    'Entidad Financiera',
];

function splitCsv(line, separator) {
    const output = [];
    let current = '';
    let quoted = false;

    for (let i = 0; i < line.length; i += 1) {
        const char = line[i];

        if (char === '"') {
            quoted = !quoted;
        } else if (char === separator && !quoted) {
            output.push(current);
            current = '';
        } else {
            current += char;
        }
    }

    output.push(current);

    return output.map((value) => value.replace(/^"|"$/g, '').trim());
}

function parseCsv(text) {
    const normalized = String(text || '').replace(/\r/g, '');
    const lines = normalized.split(/\n+/).filter(Boolean);

    if (!lines.length) {
        return [];
    }

    const separator = lines[0].split(';').length > lines[0].split(',').length ? ';' : ',';

    return lines.map((line) => splitCsv(line, separator));
}

function isNumber(value) {
    if (value === '' || value == null) return true;

    const normalized = String(value).replace(/\s/g, '').replace(/,/g, '.');

    return /^-?\d+(\.\d+)?$/.test(normalized);
}

function parseDate(value) {
    if (!value) return null;

    const text = value.trim();
    let match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (match) return text;

    match = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);

    if (match) return `${match[3]}-${match[2]}-${match[1]}`;

    return null;
}

function isDate(value) {
    return value === '' || parseDate(value) !== null;
}

function renderPaymentIssues(issues, body) {
    if (!body) return;

    body.innerHTML = issues.slice(0, 80).map((issue) => `
        <tr>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${issue.row}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(issue.column)}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(issue.value)}</td>
            <td class="border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(issue.detail)}</td>
        </tr>
    `).join('');
}

function setupPaymentPrecheck(root) {
    const file = $('#csvFilePagos', root);
    const button = $('#btnImportPagos', root);
    const box = $('#precheckBoxPagos', root);
    const headerMessage = $('#hdrMsgPagos', root);
    const typeMessage = $('#typeMsgPagos', root);
    const issuesWrap = $('#issuesWrapPagos', root);
    const issuesBody = $('#issuesBodyPagos', root);

    if (!file || !button || !box) return;

    file.addEventListener('change', (event) => {
        const selected = event.target.files?.[0];

        if (!selected) {
            button.disabled = true;
            return;
        }

        const reader = new FileReader();

        reader.onload = (readerEvent) => {
            const rows = parseCsv(readerEvent.target.result || '');

            if (!rows.length) {
                button.disabled = true;
                return;
            }

            box.classList.remove('hidden');
            issuesBody.innerHTML = '';
            issuesWrap.classList.add('hidden');

            const header = rows[0];
            const missing = PAYMENT_HEADERS.filter((expected) => !header.includes(expected));
            const extra = header.filter((value) => !PAYMENT_HEADERS.includes(value));
            const headerOk = missing.length === 0;

            headerMessage.innerHTML = headerOk
                ? `Encabezados: OK (<span class="font-semibold text-emerald-700">${header.length}</span>)`
                : `Encabezados: faltan <span class="font-semibold text-red-700">${escapeHtml(missing.join(', ') || '-')}</span>${extra.length ? `, extra: <span class="font-semibold text-amber-700">${escapeHtml(extra.join(', '))}</span>` : ''}`;

            const issues = [];
            let sampled = 0;

            for (let rowIndex = 1; rowIndex < Math.min(rows.length, 51); rowIndex += 1) {
                const row = rows[rowIndex];

                if (!row?.length) continue;

                sampled += 1;

                PAYMENT_HEADERS.forEach((headerName, columnIndex) => {
                    const value = String(row[columnIndex] ?? '').trim();
                    let ok = true;
                    let detail = '';

                    if (headerName === 'Fecha') {
                        ok = isDate(value);
                        detail = 'Fecha invalida. Use YYYY-MM-DD o DD/MM/YYYY';
                    } else if (headerName === 'Monto') {
                        ok = isNumber(value);
                        detail = 'Numero invalido';
                    }

                    if (!ok) {
                        issues.push({
                            row: rowIndex + 1,
                            column: headerName,
                            value,
                            detail,
                        });
                    }
                });
            }

            typeMessage.textContent = `Tipos por muestra: ${sampled} fila(s) verificadas, ${issues.length} posible(s) problema(s)`;

            if (issues.length) {
                issuesWrap.classList.remove('hidden');
                renderPaymentIssues(issues, issuesBody);
            }

            button.disabled = !headerOk;
        };

        reader.readAsText(selected, 'UTF-8');
    });
}

function setupImportSubmit(root) {
    $$('[data-import-form]', root).forEach((form) => {
        if (form.dataset.inited === '1') return;

        form.dataset.inited = '1';

        form.addEventListener('submit', () => {
            const button = $('[data-import-submit]', form);

            if (button) {
                button.disabled = true;
                button.textContent = 'Procesando...';
            }
        });
    });
}

ready(() => {
    $$('[data-module="integracion-imports"]').forEach((root) => {
        setupPaymentPrecheck(root);
        setupImportSubmit(root);
    });
});
