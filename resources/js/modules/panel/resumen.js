import Chart from 'chart.js/auto';
import { $, $$, escapeHtml, ready } from '../../core/dom';

function getCssValue(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

function formatMoney(value, digits = 0) {
    return 'S/ ' + Intl.NumberFormat('es-PE', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Number(value || 0));
}

function hexToRgba(hex, alpha = 1) {
    const normalized = hex.replace('#', '').trim();
    const full = normalized.length === 3
        ? normalized.split('').map((value) => value + value).join('')
        : normalized;
    const parsed = parseInt(full, 16);
    const red = (parsed >> 16) & 255;
    const green = (parsed >> 8) & 255;
    const blue = parsed & 255;

    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}

function highlight(text, query) {
    if (!query) return escapeHtml(text || '');

    const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escapedQuery})`, 'ig');

    return escapeHtml(text || '').replace(regex, '<span class="font-bold bg-kp-green-soft text-kp-green-dark">$1</span>');
}

function setupQuickSearch(root) {
    const form = $('[data-quick-form]', root);
    const input = $('[data-quick-input]', root);
    const suggestions = $('[data-quick-suggestions]', root);

    if (!form || !input || !suggestions || form.dataset.inited === '1') return;

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

    const render = (items, query) => {
        if (!items.length) {
            hide();
            return;
        }

        suggestions.innerHTML = items.map((item, index) => `
            <a
                href="${escapeHtml(item.url)}"
                class="quick-item flex items-center justify-between gap-3 rounded-md px-3 py-2 text-sm text-kp-ink hover:bg-slate-50 ${index === 0 ? 'bg-slate-50' : ''}"
                data-idx="${index}"
            >
                <span class="min-w-0 flex items-center gap-2">
                    <span class="font-mono font-bold">${highlight(item.dni, query)}</span>
                    <span class="text-kp-muted">/</span>
                    <span class="truncate">${highlight(item.nombre, query)}</span>
                </span>
                <span class="shrink-0 text-xs text-kp-muted">${highlight(item.operacion || '-', query)} - ${escapeHtml(item.cosecha || '-')}</span>
            </a>
        `).join('');

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

        fetch(`${url}?q=${encodeURIComponent(value)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => response.json())
            .then((data) => render(data || [], value))
            .catch(hide);
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

function setupMonthPicker(root) {
    const picker = $('[data-month-picker]', root);

    picker?.addEventListener('change', (event) => {
        const month = event.target.value || '';
        const url = new URL(window.location.href);
        url.searchParams.set('mes', month);
        window.location.assign(url.toString());
    });
}

function setupPanelChart(root) {
    const jsonNode = $('#panel-chart-json', root);
    const canvas = $('#chartPagos', root);

    if (!jsonNode || !canvas) return;

    const payload = JSON.parse(jsonNode.textContent || '{}');
    const labels = payload.labels || [];
    const data = payload.data || [];
    const brand = getCssValue('--color-kp-green', '#00a81c');
    const border = getCssValue('--color-kp-border', '#e8ecf3');
    const muted = getCssValue('--color-kp-muted', '#6d7b8a');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'S/ por dia',
                data,
                borderWidth: 2,
                borderColor: brand,
                backgroundColor: hexToRgba(brand, 0.16),
                hoverBackgroundColor: hexToRgba(brand, 0.28),
                borderRadius: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 250 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    callbacks: {
                        label: (context) => formatMoney(context.parsed.y, 2),
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: muted,
                        font: { size: 11, weight: '600' },
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: border },
                    ticks: {
                        color: muted,
                        callback: (value) => formatMoney(value, 0),
                    },
                },
            },
        },
    });
}

ready(() => {
    const root = $('[data-module="panel-resumen"]');

    if (!root) return;

    setupQuickSearch(root);
    setupMonthPicker(root);
    setupPanelChart(root);
});
