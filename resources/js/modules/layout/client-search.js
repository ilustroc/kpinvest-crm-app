import { $, $$, escapeHtml, ready } from '../../core/dom';
import { checkAuthResponse, toSafeInternalUrl } from '../../core/http';

function highlight(text, query) {
    const raw = String(text ?? '');
    if (!query || !raw) return escapeHtml(raw);

    const q = String(query).trim();
    if (!q) return escapeHtml(raw);

    const escapedQuery = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(escapedQuery, 'ig');

    let result = '';
    let lastIndex = 0;
    let match;

    while ((match = regex.exec(raw)) !== null) {
        result += escapeHtml(raw.slice(lastIndex, match.index));
        result += `<span class="bg-kp-green-soft font-bold text-kp-primary-ink">${escapeHtml(match[0])}</span>`;
        lastIndex = regex.lastIndex;
    }

    result += escapeHtml(raw.slice(lastIndex));
    return result;
}

function setupQuickSearch(form) {
    const input = $('[data-quick-input]', form);
    const suggestions = $('[data-quick-suggestions]', form);

    if (!input || !suggestions || form.dataset.inited === '1') return;

    form.dataset.inited = '1';

    let timer = null;
    let activeIndex = -1;

    const hide = () => {
        suggestions.classList.add('hidden');
        suggestions.innerHTML = '';
        activeIndex = -1;
    };

    const show = () => {
        suggestions.classList.remove('hidden');
    };

    const setActive = (index) => {
        const items = $$('.quick-item', suggestions);
        items.forEach((item, key) => item.classList.toggle('bg-slate-50', key === index));
        activeIndex = index;
    };

    let searchAbortController = null;

    const render = (items, query) => {
        if (!items.length) {
            hide();
            return;
        }

        suggestions.innerHTML = items.map((item, index) => {
            const safeUrl = toSafeInternalUrl(item.url);
            return `
            <a
                href="${escapeHtml(safeUrl)}"
                class="quick-item flex items-center justify-between gap-3 rounded-md px-3 py-2 text-sm text-kp-ink hover:bg-slate-50 ${index === 0 ? 'bg-slate-50' : ''}"
                data-idx="${index}"
            >
                <span class="min-w-0 flex items-center gap-2">
                    <span class="font-mono font-bold">${highlight(item.numdoc || item.dni, query)}</span>
                    <span class="text-kp-muted">/</span>
                    <span class="truncate">${highlight(item.nombre, query)}</span>
                </span>
                <span class="shrink-0 text-xs text-kp-muted">${highlight(item.operacion || '-', query)} - ${escapeHtml(item.cosecha || '-')}</span>
            </a>
        `;
        }).join('');

        $$('.quick-item', suggestions).forEach((item, index) => {
            item.addEventListener('mouseenter', () => setActive(index));
        });

        show();
        activeIndex = 0;
    };

    const fetchSuggestions = (query) => {
        const value = query.trim();
        const url = form.dataset.suggestUrl;

        if (!value || !url) {
            hide();
            return;
        }

        if (searchAbortController) {
            searchAbortController.abort();
        }
        searchAbortController = new AbortController();
        const signal = searchAbortController.signal;

        fetch(`${url}?q=${encodeURIComponent(value)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal,
        })
            .then((response) => {
                if (!checkAuthResponse(response)) return null;
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then((data) => {
                if (data) render(data || [], value);
            })
            .catch((err) => {
                if (err.name === 'AbortError') return;
                hide();
            });
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => fetchSuggestions(input.value), 140);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim()) {
            fetchSuggestions(input.value);
        }
    });

    input.addEventListener('keydown', (event) => {
        const items = $$('.quick-item', suggestions);

        if (event.key === 'ArrowDown' && items.length) {
            event.preventDefault();
            setActive(Math.min(activeIndex + 1, items.length - 1));
        }

        if (event.key === 'ArrowUp' && items.length) {
            event.preventDefault();
            setActive(Math.max(activeIndex - 1, 0));
        }

        if (event.key === 'Enter' && items.length && activeIndex >= 0) {
            event.preventDefault();
            items[activeIndex].click();
        }

        if (event.key === 'Escape') {
            hide();
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-quick-form]')) {
            hide();
        }
    });
}

ready(() => {
    $$('[data-quick-form]').forEach(setupQuickSearch);
});
