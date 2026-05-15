import { $, escapeHtml } from '../../core/dom';
import { openModal } from '../../core/modal';

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
    return `S/ ${(Number(value) || 0).toFixed(2)}`;
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

        if (title) {
            title.textContent = `Cronograma - ${
                showBalloon ? 'Convenio [Cuota Balon]' : (type === 'convenio' ? 'Convenio' : 'Cancelacion')
            }`;
        }

        if (thBalloon) {
            thBalloon.classList.toggle('hidden', !showBalloon);
        }

        body.innerHTML = (data.cuotas || []).map((quota) => `
            <tr>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-right text-sm text-kp-ink">${escapeHtml(quota?.nro ?? '')}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-sm text-kp-ink">${escapeHtml(formatDate(quota?.fecha))}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-right text-sm text-kp-ink">${escapeHtml(money(quota?.monto))}</td>
                ${showBalloon ? `<td class="whitespace-nowrap border-t border-kp-border px-4 py-3 text-center text-sm text-kp-ink">${quota?.es_balon == 1 ? '<span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-bold text-amber-700">Balon</span>' : ''}</td>` : ''}
            </tr>
        `).join('');

        openModal(modal);
    });
}
