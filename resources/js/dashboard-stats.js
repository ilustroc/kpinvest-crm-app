// public/js/dashboard-stats.js
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Chart === 'undefined') return;

  // Plugin opcional (no revienta si no está)
  if (typeof ChartDataLabels !== 'undefined') {
    Chart.register(ChartDataLabels);
  }

  const cssVar = (v, fallback = '') =>
    getComputedStyle(document.documentElement).getPropertyValue(v).trim() || fallback;

  const BRAND  = cssVar('--brand', '#00a81c');
  const BORDER = cssVar('--border', '#e8ecf3');
  const INK    = cssVar('--ink', '#151a23');

  const D = window.DASHBOARD_DATA || {};
  const money = (v) =>
    'S/ ' + Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const lineCommon = {
    maintainAspectRatio: false,
    animation: { duration: 250 },
    plugins: {
      legend: { display: false },
      datalabels: { display: false },
      tooltip: { callbacks: { label: (ctx) => money(ctx.parsed?.y ?? 0) } }
    },
    scales: {
      x: { grid: { display: false } },
      y: {
        beginAtZero: true,
        grid: { color: BORDER },
        ticks: { callback: (v) => money(v).replace('.00', '') }
      }
    }
  };

  // ===== Evolución 12 meses
  const el12  = document.getElementById('linePagos12');
  const elDia = document.getElementById('linePagosDia');

  const ch12 = el12 ? new Chart(el12, {
    type: 'line',
    data: {
      labels: D.meses || [],
      datasets: [{
        label: 'Monto (S/)',
        data: D.serieMto || [],
        tension: 0.35,
        borderColor: BRAND,
        backgroundColor: BRAND + '15',
        borderWidth: 2,
        fill: true,
        pointRadius: 2
      }]
    },
    options: lineCommon
  }) : null;

  // ===== Evolución diaria
  const chDia = elDia ? new Chart(elDia, {
    type: 'line',
    data: {
      labels: D.dias || [],
      datasets: [{
        label: 'Monto diario (S/)',
        data: D.serieDia || [],
        tension: 0.35,
        borderColor: BRAND,
        backgroundColor: BRAND + '10',
        borderWidth: 2,
        fill: true,
        pointRadius: 2
      }]
    },
    options: lineCommon
  }) : null;

  // Tabs 12 / mes (con resize correcto)
  const btn12 = document.getElementById('btnEv12');
  const btnM  = document.getElementById('btnEvMes');

  const showChart = (mode) => {
    if (!el12 || !elDia) return;

    const isMes = (mode === 'mes');
    el12.classList.toggle('d-none', isMes);
    elDia.classList.toggle('d-none', !isMes);

    btn12?.classList.toggle('active', !isMes);
    btnM?.classList.toggle('active', isMes);

    requestAnimationFrame(() => {
      if (isMes) chDia?.resize();
      else ch12?.resize();
    });
  };

  btnM?.addEventListener('click', () => showChart('mes'));
  btn12?.addEventListener('click', () => showChart('12'));

  // ===== Donut Entidades
  const pie = document.getElementById('pieEntidades');
  if (pie) {
    new Chart(pie, {
      type: 'doughnut',
      data: {
        labels: D.entLabels || [],
        datasets: [{
          data: D.entData || [],
          backgroundColor: [BRAND, '#16a34a', '#22c55e', '#4ade80', '#86efac']
        }]
      },
      options: {
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          datalabels: { display: false },
          legend: { position: 'bottom' },
          tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${money(ctx.parsed)}` } }
        }
      }
    });
  }

  // ===== Top Asesores (altura dinámica)
  const bar = document.getElementById('barAsesores');
  if (bar && D.hasTopAses) {
    const wrap = document.querySelector('.chart-wrap--ases');
    const n = (D.asesLabels || []).length;
    if (wrap) wrap.style.height = Math.max(320, n * 36) + 'px';

    new Chart(bar, {
      type: 'bar',
      data: {
        labels: D.asesLabels || [],
        datasets: [{
          label: 'Monto recaudado',
          data: D.asesData || [],
          backgroundColor: BRAND,
          borderRadius: 6,
          barThickness: 20
        }]
      },
      options: {
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
          legend: { display: false },
          datalabels: (typeof ChartDataLabels !== 'undefined') ? {
            anchor: 'end',
            align: 'end',
            offset: 5,
            formatter: (val) => money(val),
            font: { weight: 'bold', size: 11 },
            color: INK
          } : { display: false },
          tooltip: { callbacks: { label: (ctx) => money(ctx.parsed?.x ?? 0) } }
        },
        scales: {
          x: { display: false, beginAtZero: true, grace: '25%' },
          y: { grid: { display: false }, ticks: { font: { size: 11, weight: '600' } } }
        }
      }
    });
  }
});