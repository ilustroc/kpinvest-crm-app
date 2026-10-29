import { $, escapeHtml } from '../../core/dom';
import { openModal } from '../../core/modal';
import { formatMoney } from '../../core/money';

function formatDate(value) {
    if (!value) return '-';

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) return '-';

    return date.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function money(value) {
    return formatMoney(value, 'PEN');
}

function parseDataset(row) {
    try {
        return JSON.parse(row.getAttribute('data-dataset') || '{}');
    } catch (_) {
        return null;
    }
}

export function setupPromiseSchedule(root) {
    const table = $('#tblPromesas', root);
    const modal = $('#cronModal', root);
    const body = $('#cronTbody', root);
    const thBalloon = $('#thBalon', root);
    const count = $('#cronCount', root);
    const total = $('#cronTotal', root);
    const typeBadge = $('#cronTypeBadge', root);

    if (!table || !modal || !body) return;

    table.addEventListener('click', (event) => {
        if (event.target.closest('a, button, input, select, textarea, label')) return;

        const row = event.target.closest('tr[data-open-cronograma]');

        if (!row) return;

        const data = parseDataset(row);

        if (!data) return;

        const title = modal.querySelector('h3');
        const type = String(data.tipo || '').toLowerCase();
        const showBalloon = type === 'convenio_balon';
        const quotas = Array.isArray(data.cuotas) ? data.cuotas : [];

        const typeLabels = {
            convenio_balon: 'Convenio [Cuota Balón]',
            convenio: 'Convenio',
            cancelacion: 'Cancelación',
            parcial: 'Pago parcial',
        };

        if (title) {
            title.textContent = `Cronograma - ${typeLabels[type] || 'Propuesta'}`;
        }

        if (typeBadge) {
            typeBadge.textContent = typeLabels[type] || 'Propuesta';
        }

        if (thBalloon) {
            thBalloon.classList.toggle('hidden', !showBalloon);
        }

        if (count) {
            count.textContent = `${quotas.length} ${quotas.length === 1 ? 'cuota' : 'cuotas'}`;
        }

        if (total) {
            total.textContent = money(quotas.reduce((carry, quota) => carry + (Number(quota?.monto) || 0), 0));
        }

        if (!quotas.length) {
            body.innerHTML = `
                <tr>
                    <td colspan="${showBalloon ? 4 : 3}" class="border-t border-kp-border px-4 py-8 text-center text-[12px] text-kp-muted">
                        Sin cuotas registradas para esta propuesta.
                    </td>
                </tr>
            `;
            openModal(modal);
            return;
        }

        body.innerHTML = quotas.map((quota) => `
            <tr>
                <td class="whitespace-nowrap border-t border-kp-border px-3 py-2.5 text-right text-[12px] font-medium tabular-nums text-slate-500">${escapeHtml(quota?.nro ?? '')}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-3 py-2.5 text-[12px] text-kp-ink">${escapeHtml(formatDate(quota?.fecha))}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-3 py-2.5 text-right text-[12px] tabular-nums text-kp-ink">${escapeHtml(money(quota?.monto))}</td>
                ${showBalloon ? `<td class="whitespace-nowrap border-t border-kp-border px-3 py-2.5 text-center text-[12px] text-kp-ink">${quota?.es_balon == 1 ? '<span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Balón</span>' : '<span class="text-slate-300">—</span>'}</td>` : ''}
            </tr>
        `).join('');

        openModal(modal);
    });
}
