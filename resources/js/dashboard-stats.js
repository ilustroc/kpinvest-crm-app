document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    Chart.register(ChartDataLabels);

    const getCol = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim() || '#00a81c';
    const brandColor = getCol('--brand');
    const borderColor = getCol('--border');

    // Configuración común
    const lineOptions = {
        maintainAspectRatio: false,
        plugins: { datalabels: { display: false }, legend: { display: true } },
        scales: {
            x: { grid: { color: borderColor } },
            y: { grid: { color: borderColor }, beginAtZero: true }
        }
    };

    // 1. Evolución 12 Meses
    const ctx12 = document.getElementById('linePagos12');
    const ch12 = ctx12 ? new Chart(ctx12, {
        type: 'line',
        data: { 
            labels: DASHBOARD_DATA.meses, 
            datasets: [{ label: 'Monto (S/)', data: DASHBOARD_DATA.serieMto, tension: .35, borderColor: brandColor, backgroundColor: brandColor + '15', fill: true, borderWidth: 2 }] 
        },
        options: lineOptions
    }) : null;

    // 2. Evolución Diaria
    const ctxDia = document.getElementById('linePagosDia');
    const chDia = ctxDia ? new Chart(ctxDia, {
        type: 'line',
        data: { 
            labels: DASHBOARD_DATA.dias, 
            datasets: [{ label: 'Monto (S/)', data: DASHBOARD_DATA.serieDia, tension: .35, borderColor: brandColor, borderWidth: 2 }] 
        },
        options: lineOptions
    }) : null;

    // Control de Tabs
    const btn12 = document.getElementById('btnEv12');
    const btnM = document.getElementById('btnEvMes');
    if (btn12 && btnM) {
        btn12.onclick = () => {
            ctx12.classList.remove('d-none'); ctxDia.classList.add('d-none');
            btn12.classList.add('active'); btnM.classList.remove('active');
            ch12.resize();
        };
        btnM.onclick = () => {
            ctx12.classList.add('d-none'); ctxDia.classList.remove('d-none');
            btn12.classList.remove('active'); btnM.classList.add('active');
            chDia.resize();
        };
    }

    // 3. Donut Entidades
    const ctxPie = document.getElementById('pieEntidades');
    if (ctxPie) {
        new Chart(ctxPie, {
            type: 'doughnut',
            data: { labels: DASHBOARD_DATA.entLabels, datasets: [{ data: DASHBOARD_DATA.entData, backgroundColor: [brandColor, '#16a34a', '#22c55e', '#4ade80', '#86efac'] }] },
            options: { maintainAspectRatio: false, plugins: { datalabels: { display: false }, legend: { position: 'bottom' } }, cutout: '65%' }
        });
    }

    // 4. Bar Asesores (CON MONTOS VISIBLES)
    const ctxBar = document.getElementById('barAsesores');
    if (ctxBar && DASHBOARD_DATA.hasTopAses) {
        new Chart(ctxBar, {
            type: 'bar',
            data: { 
                labels: DASHBOARD_DATA.asesLabels, 
                datasets: [{ data: DASHBOARD_DATA.asesData, backgroundColor: brandColor }] 
            },
            options: {
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end', align: 'end',
                        formatter: (val) => 'S/ ' + Intl.NumberFormat('es-PE').format(val),
                        font: { weight: 'bold', size: 10 },
                        color: getCol('--ink')
                    }
                },
                scales: {
                    x: { display: false, beginAtZero: true, grace: '20%' },
                    y: { grid: { display: false } }
                }
            }
        });
    }
});