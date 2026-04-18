document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const jsonNode = document.getElementById('dashboard-json');
    const canvas = document.getElementById('linePagos12');

    if (!jsonNode || !canvas) return;

    const data = JSON.parse(jsonNode.textContent || '{}');

    const getCol = (name, fallback) =>
        getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

    const brand = getCol('--brand', '#00a81c');
    const border = getCol('--border', '#e5e7eb');
    const muted = getCol('--muted', '#667085');

    const formatMoney = (value, digits = 0) =>
        'S/ ' + Intl.NumberFormat('es-PE', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        }).format(Number(value || 0));

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
                tension: .35,
                fill: true,
                backgroundColor: (context) => {
                    const chart = context.chart;
                    const { ctx, chartArea } = chart;

                    if (!chartArea) return 'rgba(0, 168, 28, 0.14)';

                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, 'rgba(0, 168, 28, 0.22)');
                    gradient.addColorStop(1, 'rgba(0, 168, 28, 0.02)');
                    return gradient;
                }
            }]
        },
        options: {
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    callbacks: {
                        label: (ctx) => formatMoney(ctx.parsed.y, 2)
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: border },
                    ticks: {
                        color: muted,
                        font: { size: 11, weight: '600' }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: border },
                    ticks: {
                        color: muted,
                        callback: (value) => formatMoney(value, 0)
                    }
                }
            }
        }
    });
});