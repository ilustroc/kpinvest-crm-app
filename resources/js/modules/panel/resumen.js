import Chart from 'chart.js/auto';
import { $, ready } from '../../core/dom';

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

    setupMonthPicker(root);
    setupPanelChart(root);
});
