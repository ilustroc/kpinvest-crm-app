import { Chart, registerables } from 'chart.js'
import ChartDataLabels from 'chartjs-plugin-datalabels'

Chart.register(...registerables, ChartDataLabels)

if (!window.__dash_inited) {
  window.__dash_inited = true

  document.addEventListener('DOMContentLoaded', () => {
    const d = window.DASHBOARD_DATA || {}

    const root = getComputedStyle(document.documentElement)
    const BRAND = root.getPropertyValue('--kp-brand')?.trim() || '#00a81c'
    const BRAND_700 = root.getPropertyValue('--kp-brand-700')?.trim() || '#008517'
    const ACCENT = root.getPropertyValue('--kp-accent')?.trim() || '#0b4ea2'

    const fmtMoney = (n) =>
      new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        .format(Number(n || 0))

    // ===== KPI icons (bonito como admin)
    const kpiIcons = {
      promesas: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
        <path d="M4 4h16v16H4z"></path><path d="M8 8h8"></path><path d="M8 12h8"></path><path d="M8 16h6"></path>
      </svg>`,
      mto_neg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
        <path d="M12 1v22"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"></path>
      </svg>`,
      pagos: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
        <path d="M20 7H4v10h16z"></path><path d="M16 11a4 4 0 0 1-8 0"></path>
      </svg>`,
      mto_pag: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
        <path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7"></path>
        <path d="M12 3v13"></path><path d="M7 8l5-5 5 5"></path>
      </svg>`,
    }

    document.querySelectorAll('[data-kpi]').forEach(card => {
      const key = card.getAttribute('data-kpi')
      const ic = card.querySelector('.dash-kpi-ic')
      if (!ic) return

      ic.innerHTML = kpiIcons[key] || ''

      // pinta distinto por tipo
      if (key === 'mto_neg' || key === 'mto_pag') {
        ic.classList.remove('bg-emerald-500/10','text-emerald-700')
        ic.classList.add('bg-blue-500/10','text-blue-700')
      }
    })

    // ===== defaults Chart (tipografía y grillas como pro)
    Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui'
    Chart.defaults.color = '#334155'
    Chart.defaults.plugins.legend.labels.usePointStyle = true

    // ====== Evolución (1 canvas, cambia dataset)
    const lineEl = document.getElementById('linePagos')
    const evSub = document.getElementById('evSub')

    const makeGradient = (ctx) => {
      const g = ctx.createLinearGradient(0, 0, 0, 320)
      g.addColorStop(0, `${BRAND}33`)
      g.addColorStop(1, `${BRAND}05`)
      return g
    }

    const lineChart = lineEl ? new Chart(lineEl, {
      type: 'line',
      data: {
        labels: d.meses || [],
        datasets: [{
          label: 'Pagos',
          data: d.serieMto || [],
          tension: 0.35,
          borderColor: BRAND,
          backgroundColor: (ctx) => makeGradient(ctx.chart.ctx),
          fill: true,
          pointRadius: 2,
          pointHoverRadius: 4,
          borderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          datalabels: { display: false },
          tooltip: {
            callbacks: { label: (c) => `S/ ${fmtMoney(c.parsed.y)}` }
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
          },
          y: {
            ticks: { callback: (v) => `S/ ${fmtMoney(v)}` }
          }
        }
      }
    }) : null

    const setLineMode = (mode) => {
      if (!lineChart) return

      const is12 = mode === '12'
      const labels = is12 ? (d.meses || []) : (d.dias || [])
      const data = is12 ? (d.serieMto || []) : (d.serieDia || [])

      lineChart.data.labels = labels
      lineChart.data.datasets[0].data = data
      lineChart.data.datasets[0].borderColor = is12 ? BRAND : ACCENT
      lineChart.data.datasets[0].backgroundColor = (ctx) => {
        const base = is12 ? BRAND : ACCENT
        const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 320)
        g.addColorStop(0, `${base}33`)
        g.addColorStop(1, `${base}05`)
        return g
      }

      evSub && (evSub.textContent = is12 ? 'Monto total (12 meses)' : 'Monto total (este mes)')
      lineChart.update()
    }

    const btn12 = document.getElementById('btnEv12')
    const btnMes = document.getElementById('btnEvMes')

    const setActiveBtn = (is12) => {
      btn12?.classList.toggle('is-active', is12)
      btnMes?.classList.toggle('is-active', !is12)
    }

    btn12?.addEventListener('click', () => { setActiveBtn(true); setLineMode('12') })
    btnMes?.addEventListener('click', () => { setActiveBtn(false); setLineMode('mes') })
    setActiveBtn(true)
    setLineMode('12')

    // ====== Pie entidades
    const pieEl = document.getElementById('pieEntidades')
    if (pieEl) {
      const palette = [BRAND, ACCENT, '#0ea5e9', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#64748b']

      new Chart(pieEl, {
        type: 'doughnut',
        data: {
          labels: d.entLabels || [],
          datasets: [{
            data: d.entData || [],
            backgroundColor: (d.entData || []).map((_, i) => `${palette[i % palette.length]}cc`),
            borderColor: '#fff',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: { position: 'bottom' },
            datalabels: {
              color: '#fff',
              font: { weight: '800' },
              formatter: (value, ctx) => {
                const arr = ctx.chart.data.datasets[0].data || []
                const total = arr.reduce((a,b)=>a+Number(b||0),0)
                if (!total) return ''
                const p = (Number(value||0)/total)*100
                return p >= 8 ? `${p.toFixed(0)}%` : ''
              }
            },
            tooltip: { callbacks: { label: (c) => `${c.label}: S/ ${fmtMoney(c.parsed)}` } }
          }
        }
      })
    }

    // ====== Bar asesores
    if (d.hasTopAses && document.getElementById('barAsesores')) {
      const wrap = document.getElementById('asesWrap')
      const n = (d.asesLabels || []).length
      if (wrap && n) wrap.style.height = `${Math.max(360, 120 + n * 28)}px`

      new Chart(document.getElementById('barAsesores'), {
        type: 'bar',
        data: {
          labels: d.asesLabels || [],
          datasets: [{
            data: d.asesData || [],
            backgroundColor: `${BRAND}cc`,
            borderRadius: 10
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            datalabels: {
              anchor: 'end',
              align: 'right',
              color: '#0f172a',
              font: { weight: '700' },
              formatter: (v) => `S/ ${fmtMoney(v)}`
            },
            tooltip: { callbacks: { label: (c) => `S/ ${fmtMoney(c.parsed.x)}` } }
          },
          scales: {
            y: { grid: { display: false } },
            x: { ticks: { callback: (v) => `S/ ${fmtMoney(v)}` } }
          }
        }
      })
    }
  })
}