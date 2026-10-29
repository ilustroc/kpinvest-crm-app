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

function formatDate(value) {
    if (!value) return '';

    const parts = String(value).split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
}

function renderOptions(form, msRoot, name, options) {
    if (!msRoot) return;

    const selected = new Set(selectedValues(form, name));
    const list = $('[data-ms-list]', msRoot);
    if (!list) return;

    if (!options || !options.length) {
        list.innerHTML = '<div class="px-2 py-3 text-[13px] text-kp-muted">Sin opciones en este rango.</div>';
        setMultiSelectLabel(msRoot);
        return;
    }

    list.innerHTML = options.map((option) => {
        const value = String(option);
        const checked = selected.has(value) ? 'checked' : '';

        return `
            <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-[13px] text-kp-ink transition-colors hover:bg-slate-50 ms-item">
                <input class="size-4 shrink-0 rounded border-kp-border accent-kp-primary kp-focus" type="checkbox" name="${name}[]" value="${escapeHtml(value)}" ${checked}>
                <span class="min-w-0 flex-1 truncate ms-text">${escapeHtml(value)}</span>
            </label>
        `;
    }).join('');

    setMultiSelectLabel(msRoot);
}

function renderActiveFilters(form, root) {
    const container = $('[data-report-filter-chips]', root);
    if (!container) return;

    const chips = [];
    const from = form.querySelector('[name="from"]')?.value || '';
    const to = form.querySelector('[name="to"]')?.value || '';

    if (from && to) {
        chips.push({ label: `Periodo: ${formatDate(from)} – ${formatDate(to)}`, tone: 'neutral' });
    } else if (from) {
        chips.push({ label: `Desde: ${formatDate(from)}`, tone: 'neutral' });
    } else if (to) {
        chips.push({ label: `Hasta: ${formatDate(to)}`, tone: 'neutral' });
    }

    let activeCount = 0;

    $$('[data-multiselect]', form).forEach((msRoot) => {
        const checked = $$('input[type="checkbox"]:checked', msRoot);
        if (!checked.length) return;

        const title = msRoot.dataset.title || 'Filtro';

        if (checked.length === 1) {
            const value = checked[0].closest('label')?.querySelector('.ms-text')?.textContent?.trim()
                || checked[0].value;
            chips.push({ label: `${title}: ${value}`, tone: 'primary' });
        } else {
            chips.push({ label: `${title} (${checked.length})`, tone: 'primary' });
        }
    });

    const query = (form.querySelector('[name="q"]')?.value || '').trim();
    if (query) {
        chips.push({ label: `Búsqueda: ${query}`, tone: 'primary' });
    }

    container.innerHTML = chips.map((chip) => {
        const tone = chip.tone === 'primary'
            ? 'border-kp-primary-border bg-kp-primary-soft text-kp-primary-ink'
            : 'border-kp-border bg-kp-surface-muted text-slate-600';

        return `<span class="inline-flex items-center rounded-md border px-2 py-1 text-[10.5px] font-medium ${tone}">${escapeHtml(chip.label)}</span>`;
    }).join('');
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

    list?.addEventListener('change', () => setMultiSelectLabel(msRoot));

    clear?.addEventListener('click', () => {
        $$('input[type="checkbox"]', msRoot).forEach((field) => {
            field.checked = false;
        });
        setMultiSelectLabel(msRoot);
    });

    apply?.addEventListener('click', async () => {
        setMultiSelectLabel(msRoot);
        closeDropdown(msRoot);

        await callbacks.run(async () => {
            await callbacks.refreshFacets(false);
            await callbacks.loadData();
        });
    });

    setMultiSelectLabel(msRoot);
}

export function initReportFilters(config) {
    ready(() => {
        const root = $(config.rootSelector);
        if (!root) return;

        setupDropdowns(root);

        const form = $('[data-report-filters]', root);
        if (!form) return;

        const exportButton = $('[data-report-export]', root);
        const summary = $('[data-report-summary]', root);
        const searchButton = $('[data-report-search]', root);
        const searchIcon = $('[data-report-search-icon]', root);
        const searchSpinner = $('[data-report-search-spinner]', root);
        const searchLabel = $('[data-report-search-label]', root);
        const clearButton = $('[data-report-clear]', root);
        const baseUrl = form.getAttribute('action') || location.pathname;
        const exportUrl = document.querySelector(`meta[name="${config.exportMeta}"]`)?.content || '';
        const facetsUrl = document.querySelector(`meta[name="${config.facetsMeta}"]`)?.content || '';

        let running = false;

        const buildQuery = (extra = {}) => formQuery(form, extra);

        const setBusy = (busy) => {
            form.setAttribute('aria-busy', busy ? 'true' : 'false');
            if (searchButton) searchButton.disabled = busy;
            if (clearButton) clearButton.disabled = busy;
            searchIcon?.classList.toggle('hidden', busy);
            searchSpinner?.classList.toggle('hidden', !busy);
            if (searchLabel) searchLabel.textContent = busy ? 'Buscando...' : 'Buscar';
            if (busy && summary) summary.textContent = 'Actualizando resultados...';
        };

        const updateExport = () => {
            if (!exportButton || !exportUrl) return;
            const query = buildQuery();
            exportButton.href = query ? `${exportUrl}?${query}` : exportUrl;
        };

        const updateSummary = () => {
            const meta = $('#pagMeta', root);
            const total = meta?.dataset.total || '';
            const page = meta?.dataset.page || '';
            const last = meta?.dataset.last || '';

            if (summary) {
                summary.textContent = total
                    ? `${total} resultados · Página ${page} de ${last}`
                    : '';
            }
        };

        const hookPagination = () => {
            $$(`${config.tableSelector} [data-pagination] a`).forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    run(async () => callbacks.loadData(link.getAttribute('href')));
                });
            });
        };

        const callbacks = {
            async loadData(url = null) {
                const table = $(config.tableSelector, root);
                if (!table) return;

                const extra = config.partialParam ? { partial: 1 } : {};
                const targetUrl = url || `${baseUrl}?${buildQuery(extra)}`;

                table.innerHTML = `
                    <div class="flex items-center justify-center gap-2.5 px-4 py-12 text-[13px] font-medium text-kp-muted">
                        <span class="size-4 animate-spin rounded-full border-2 border-slate-200 border-t-kp-primary" aria-hidden="true"></span>
                        Actualizando resultados...
                    </div>
                `;

                try {
                    const html = await fetchText(targetUrl);
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const fragment = doc.querySelector(config.tableSelector);

                    if (!fragment) {
                        throw new Error('No se encontró la tabla en la respuesta.');
                    }

                    table.outerHTML = fragment.outerHTML;
                    hookPagination();
                    updateSummary();
                    renderActiveFilters(form, root);
                    updateExport();

                    const query = buildQuery();
                    const historyUrl = url || (query ? `${baseUrl}?${query}` : baseUrl);
                    history.replaceState(null, '', historyUrl);
                } catch (error) {
                    console.error('Report load error:', error);
                    table.innerHTML = `
                        <div class="m-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-medium text-red-700">
                            No se pudieron actualizar los resultados. Intenta nuevamente.
                        </div>
                    `;
                }
            },

            async refreshFacets() {
                if (!facetsUrl) return;

                const data = await fetchJson(`${facetsUrl}?${buildQuery()}`);

                config.facets.forEach(({ name, response }) => {
                    renderOptions(form, root.querySelector(`[data-multiselect="${name}"]`), name, data[response] || []);
                });

                $$('[data-multiselect]', root).forEach((msRoot) => setMultiSelectLabel(msRoot));
                renderActiveFilters(form, root);
                updateExport();
            },

            async run(task) {
                if (running) return;
                running = true;
                setBusy(true);

                try {
                    await task();
                } catch (error) {
                    console.error('Report filter error:', error);
                    if (summary) summary.textContent = 'No se pudieron actualizar los filtros.';
                } finally {
                    running = false;
                    setBusy(false);
                    updateSummary();
                    renderActiveFilters(form, root);
                    updateExport();
                }
            },
        };

        const run = (task) => callbacks.run(task);

        $$('[data-multiselect]', root).forEach((msRoot) => initMultiSelect(form, msRoot, callbacks));

        $('[data-report-search]', root)?.addEventListener('click', (event) => {
            event.preventDefault();
            run(async () => {
                await callbacks.refreshFacets();
                await callbacks.loadData();
            });
        });

        $('[data-report-clear]', root)?.addEventListener('click', async (event) => {
            event.preventDefault();
            resetToDefaults(form);
            $$('[data-multiselect]', root).forEach((msRoot) => setMultiSelectLabel(msRoot));
            renderActiveFilters(form, root);

            await run(async () => {
                await callbacks.refreshFacets();
                await callbacks.loadData();
            });
        });

        $$('input[data-default]', form).forEach((field) => {
            field.addEventListener('change', () => {
                run(async () => {
                    await callbacks.refreshFacets();
                    await callbacks.loadData();
                });
            });
        });

        $('input[name="q"]', form)?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;

            event.preventDefault();
            run(async () => {
                await callbacks.refreshFacets();
                await callbacks.loadData();
            });
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            run(async () => {
                await callbacks.refreshFacets();
                await callbacks.loadData();
            });
        });

        hookPagination();
        updateSummary();
        renderActiveFilters(form, root);
        updateExport();
    });
}
