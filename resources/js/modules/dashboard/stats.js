import Chart from 'chart.js/auto';
import { formatMoney } from '../../core/money';

function getCssValue(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

function initDashboardChart() {
    const page = document.querySelector('[data-dashboard-page]');
    const jsonNode = document.getElementById('dashboard-json');
    const canvas = document.getElementById('linePagos12');
    const loading = page?.querySelector('[data-dashboard-chart-loading]');

    if (!page || !jsonNode || !canvas) return;

    const data = JSON.parse(jsonNode.textContent || '{}');
    const brand = getCssValue('--color-kp-green', '#00a81c');
    const navy = getCssValue('--color-kp-navy', '#062b60');
    const border = getCssValue('--color-kp-border', '#e4e7ec');
    const muted = getCssValue('--color-kp-muted', '#667085');

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.meses || [],
            datasets: [{
                data: data.serieMto || [],
                borderColor: brand,
                borderWidth: 3,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBorderWidth: 2,
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: brand,
                tension: 0.35,
                fill: true,
                backgroundColor: (context) => {
                    const { ctx, chartArea } = context.chart;

                    if (!chartArea) return 'rgba(0, 168, 28, 0.12)';

                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, 'rgba(0, 168, 28, 0.20)');
                    gradient.addColorStop(1, 'rgba(0, 168, 28, 0.01)');

                    return gradient;
                },
            }],
        },
        options: {
            maintainAspectRatio: false,
            animation: { duration: 550 },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    backgroundColor: navy,
                    titleFont: { family: 'Inter', size: 12, weight: '600' },
                    bodyFont: { family: 'Inter', size: 12, weight: '500' },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: (ctx) => formatMoney(ctx.parsed.y, 'PEN'),
                    },
                },
            },
            scales: {
                x: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: {
                        color: muted,
                        font: { family: 'Inter', size: 10, weight: '600' },
                        maxRotation: 0,
                        autoSkipPadding: 16,
                    },
                },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: {
                        color: border,
                        drawTicks: false,
                        borderDash: [4, 4],
                    },
                    ticks: {
                        color: muted,
                        padding: 8,
                        maxTicksLimit: 4,
                        font: { family: 'Inter', size: 9, weight: '500' },
                        callback: (value) => formatMoney(value, 'PEN'),
                    },
                },
            },
        },
    });

    requestAnimationFrame(() => loading?.classList.add('hidden'));
}

document.addEventListener('DOMContentLoaded', initDashboardChart);
