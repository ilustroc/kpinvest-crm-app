import { $, $$, escapeHtml, ready } from '../../core/dom';
import { closeDropdown, setupDropdowns } from '../../core/dropdown';
import { formQuery, resetToDefaults } from '../../core/forms';
import { fetchJson, fetchText } from '../../core/http';

function selectedValues(form, name) {
    return $$(`input[name="${name}[]"]:checked`, form).map((field) => field.value);
}

function setMultiSelectLabel(msRoot) {
    if (!msRoot) return;

    const title = msRoot.dataset.title || 'Filtro';
    const empty = msRoot.dataset.empty || 'Todos';
    const button = $('[data-ms-button]', msRoot);
    const textNode = button?.querySelector('span') || button;
    const checked = $$('input[type="checkbox"]:checked', msRoot).length;

    if (textNode) {
        textNode.textContent = checked ? `${title} (${checked})` : `${title}: ${empty}`;
    }
}

function renderOptions(form, msRoot, name, options) {
    if (!msRoot) return;

    const selected = new Set(selectedValues(form, name));
    const list = $('[data-ms-list]', msRoot);
    if (!list) return;

    if (!options || !options.length) {
        list.innerHTML = '<div class="px-2 py-3 text-sm text-kp-muted">Sin opciones en este rango.</div>';
        setMultiSelectLabel(msRoot);
        return;
    }

    list.innerHTML = options.map((option) => {
        const value = String(option);
        const checked = selected.has(value) ? 'checked' : '';

        return `
            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-kp-ink hover:bg-slate-50 ms-item">
                <input class="h-4 w-4 rounded border-kp-border text-kp-green kp-focus" type="checkbox" name="${name}[]" value="${escapeHtml(value)}" ${checked}>
                <span class="min-w-0 flex-1 truncate ms-text">${escapeHtml(value)}</span>
            </label>
        `;
    }).join('');

    setMultiSelectLabel(msRoot);
}

function initMultiSelect(form, msRoot, callbacks) {
    if (!msRoot || msRoot.dataset.inited === '1') {
        setMultiSelectLabel(msRoot);
        return;
    }

    msRoot.dataset.inited = '1';

    const search = $('[data-ms-search]', msRoot);
    const list = $('[data-ms-list]', msRoot);
    const clear = $('[data-ms-clear]', msRoot);
    const apply = $('[data-ms-apply]', msRoot);

    search?.addEventListener('input', () => {
        const query = (search.value || '').toLowerCase();
        $$('.ms-item', list).forEach((item) => {
            item.style.display = item.innerText.toLowerCase().includes(query) ? '' : 'none';
        });
    });

    clear?.addEventListener('click', () => {
        $$('input[type="checkbox"]', msRoot).forEach((field) => {
            field.checked = false;
        });
        setMultiSelectLabel(msRoot);
    });

    apply?.addEventListener('click', async () => {
        setMultiSelectLabel(msRoot);
        closeDropdown(msRoot);
        await callbacks.loadData();
        await callbacks.refreshFacets(false);
    });

    setMultiSelectLabel(msRoot);
}

export function initReportFilters(config) {
    ready(() => {
        const root = $(config.rootSelector);
        if (!root) return;

        setupDropdowns(root);

        const form = $('[data-report-filters]', root);
        const exportButton = $('[data-report-export]', root);
        const summary = $('[data-report-summary]', root);
        const baseUrl = form?.getAttribute('action') || location.pathname;
        const exportUrl = document.querySelector(`meta[name="${config.exportMeta}"]`)?.content || '';
        const facetsUrl = document.querySelector(`meta[name="${config.facetsMeta}"]`)?.content || '';

        if (!form) return;

        const buildQuery = (extra = {}) => formQuery(form, extra);

        const updateExport = () => {
            if (!exportButton || !exportUrl) return;
            const query = buildQuery();
            exportButton.href = query ? `${exportUrl}?${query}` : exportUrl;
        };

        const updateSummary = () => {
            if (!summary) return;
            const meta = $('#pagMeta');
            const page = meta?.dataset.page || '';
            const total = meta?.dataset.total || '';
            summary.textContent = total ? `Pagina ${page} - ${total} resultados` : '';
        };

        const hookPagination = () => {
            $$(`${config.tableSelector} [data-pagination] a`).forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    callbacks.loadData(link.getAttribute('href'));
                });
            });
        };

        const callbacks = {
            async loadData(url = null) {
                const table = $(config.tableSelector);
                if (!table) return;

                const extra = config.partialParam ? { partial: 1 } : {};
                const targetUrl = url || `${baseUrl}?${buildQuery(extra)}`;

                table.innerHTML = '<div class="rounded-md border border-dashed border-kp-border bg-slate-50 px-4 py-8 text-center text-sm font-semibold text-kp-muted">Cargando...</div>';

                const html = await fetchText(targetUrl);
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const fragment = doc.querySelector(config.tableSelector);

                if (!fragment) {
                    table.innerHTML = '<div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">No se pudo renderizar la tabla.</div>';
                    return;
                }

                table.outerHTML = fragment.outerHTML;
                hookPagination();
                updateExport();
                updateSummary();

                const query = buildQuery();
                history.replaceState(null, '', query ? `${baseUrl}?${query}` : baseUrl);
            },

            async refreshFacets(loadAfter = false) {
                if (!facetsUrl) {
                    if (loadAfter) await callbacks.loadData();
                    return;
                }

                const data = await fetchJson(`${facetsUrl}?${buildQuery()}`);

                config.facets.forEach(({ name, response }) => {
                    renderOptions(form, root.querySelector(`[data-multiselect="${name}"]`), name, data[response] || []);
                });

                $$('[data-multiselect]', root).forEach((msRoot) => setMultiSelectLabel(msRoot));
                updateExport();

                if (loadAfter) await callbacks.loadData();
            },
        };

        $$('[data-multiselect]', root).forEach((msRoot) => initMultiSelect(form, msRoot, callbacks));

        $('[data-report-search]', root)?.addEventListener('click', (event) => {
            event.preventDefault();
            callbacks.refreshFacets(true).catch(() => callbacks.loadData());
        });

        $('[data-report-clear]', root)?.addEventListener('click', async () => {
            resetToDefaults(form);
            $$('[data-multiselect]', root).forEach((msRoot) => setMultiSelectLabel(msRoot));
            await callbacks.refreshFacets(true);
        });

        $$('input[data-default]', form).forEach((field) => {
            field.addEventListener('change', () => callbacks.refreshFacets(true));
        });

        $('input[name="q"]', form)?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;

            event.preventDefault();
            callbacks.refreshFacets(true).catch(() => callbacks.loadData());
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            callbacks.refreshFacets(true).catch(() => callbacks.loadData());
        });

        hookPagination();
        updateExport();
        updateSummary();
    });
}
