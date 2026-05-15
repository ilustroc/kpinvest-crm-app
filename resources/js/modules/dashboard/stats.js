import Chart from 'chart.js/auto';

function getCssValue(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

function formatMoney(value, digits = 0) {
    return 'S/ ' + Intl.NumberFormat('es-PE', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Number(value || 0));
}

function initDashboardChart() {
    const page = document.querySelector('[data-dashboard-page]');
    const jsonNode = document.getElementById('dashboard-json');
    const canvas = document.getElementById('linePagos12');

    if (!page || !jsonNode || !canvas) return;

    const data = JSON.parse(jsonNode.textContent || '{}');
    const brand = getCssValue('--color-kp-green', '#00a81c');
    const border = getCssValue('--color-kp-border', '#e8ecf3');
    const muted = getCssValue('--color-kp-muted', '#6d7b8a');

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.meses || [],
            datasets: [{
                data: data.serieMto || [],
                borderColor: brand,
                borderWidth: 4,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#fff',
                pointBorderColor: brand,
                pointBorderWidth: 2,
                tension: 0.35,
                fill: true,
                backgroundColor: (context) => {
                    const { ctx, chartArea } = context.chart;

                    if (!chartArea) return 'rgba(0, 168, 28, 0.14)';

                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, 'rgba(0, 168, 28, 0.22)');
                    gradient.addColorStop(1, 'rgba(0, 168, 28, 0.02)');

                    return gradient;
                },
            }],
        },
        options: {
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    callbacks: {
                        label: (ctx) => formatMoney(ctx.parsed.y, 2),
                    },
                },
            },
            scales: {
                x: {
                    grid: { color: border },
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

document.addEventListener('DOMContentLoaded', initDashboardChart);
