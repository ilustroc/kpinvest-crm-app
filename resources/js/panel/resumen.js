// resources/js/panel/resumen.js
import { Chart, registerables } from 'chart.js'
import ChartDataLabels from 'chartjs-plugin-datalabels'

Chart.register(...registerables, ChartDataLabels)

document.addEventListener('DOMContentLoaded', () => {

  /* =========================
  *  AUTOCOMPLETE QUICK SEARCH
  * ========================= */
  const inp = document.getElementById('inpQuick')
  const box = document.getElementById('quickSug')
  const suggestUrl = document.querySelector('meta[name="quick-suggest-url"]')?.content

  if (inp && box && suggestUrl) {
    let timer = null
    let active = -1
    let items = []
    let aborter = null

    const close = () => {
      box.classList.remove('show')
      box.innerHTML = ''
      active = -1
      items = []
    }

    const open = () => box.classList.add('show')

    const render = (data) => {
      items = Array.isArray(data) ? data : []
      active = -1

      if (!items.length) return close()

      box.innerHTML = items.map((it, i) => {
        const dni = it.value ?? ''
        // Limpiamos el nombre para que no repita el DNI si ya viene en el title
        const name = (it.title ?? '').split('»')[1]?.trim() || it.title
        const meta = it.meta ?? ''

        return `
          <div class="quick-item group cursor-pointer border-b border-slate-50 last:border-none" data-i="${i}">
            <div class="flex flex-col w-full">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-emerald-600 font-extrabold text-sm">${dni}</span>
                  <span class="text-slate-300">|</span>
                  <span class="text-slate-900 font-bold text-sm truncate max-w-[200px]">${name}</span>
                </div>
                ${meta ? `<span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50 px-2 py-0.5 rounded-md">${meta}</span>` : ''}
              </div>
            </div>
          </div>
        `
      }).join('')

      open()
    }

    const setActive = (idx) => {
      const nodes = [...box.querySelectorAll('.quick-item')]
      nodes.forEach(n => n.classList.remove('active'))
      if (idx >= 0 && idx < nodes.length) {
        nodes[idx].classList.add('active')
        nodes[idx].scrollIntoView({ block: 'nearest' })
      }
    }

    const fetchSuggest = async () => {
      const q = (inp.value || '').trim()
      if (q.length < 2) return close()

      if (aborter) aborter.abort()
      aborter = new AbortController()

      try {
        const url = new URL(suggestUrl, window.location.origin)
        url.searchParams.set('q', q)

        console.log('[Quick] GET =>', url.toString())

        const res = await fetch(url.toString(), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: aborter.signal,
        })

        console.log('[Quick] status:', res.status, 'content-type:', res.headers.get('content-type'))

        const text = await res.text()
        console.log('[Quick] body preview:', text.slice(0, 120))

        // si no es JSON, cerramos
        if (!(res.headers.get('content-type') || '').includes('application/json')) {
          return close()
        }

        const data = JSON.parse(text)
        render(data)

      } catch (e) {
        console.error('[Quick] error:', e)
        close()
      }
    }

    inp.addEventListener('input', () => {
      clearTimeout(timer)
      timer = setTimeout(fetchSuggest, 200)
    })

    inp.addEventListener('keydown', (e) => {
      if (!box.classList.contains('show')) return

      if (e.key === 'Escape') return close()

      if (e.key === 'ArrowDown') {
        e.preventDefault()
        active = Math.min(active + 1, items.length - 1)
        setActive(active)
      } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        active = Math.max(active - 1, 0)
        setActive(active)
      } else if (e.key === 'Enter') {
        if (active >= 0 && items[active]) {
          e.preventDefault()
          window.location.href = items[active].url
        }
      }
    })

    box.addEventListener('mousedown', (e) => {
      const row = e.target.closest('.quick-item')
      if (!row) return
      const i = Number(row.dataset.i)
      if (Number.isFinite(i) && items[i]) window.location.href = items[i].url
    })

    document.addEventListener('click', (e) => {
      if (e.target === inp || box.contains(e.target)) return
      close()
    })
  }
  
  /* ===== Selector de mes ===== */
  document.getElementById('mesPicker')?.addEventListener('change', (e) => {
    const ym = e.target.value || ''
    const url = new URL(window.location.href)
    url.searchParams.set('mes', ym)
    window.location.assign(url.toString())
  })

  /* ===== Chart: Pagos del mes ===== */
  const el = document.getElementById('chartPagos')
  if (el) {
    const payload = (() => { try { return JSON.parse(el.dataset.chart || '{}') } catch { return {} } })()
    const labels = payload.labels || []
    const data   = payload.data   || []

    const cssVar = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim()
    const BRAND  = cssVar('--brand') || '#00a81c'

    const hexToRgba = (hex, a = 1) => {
      const h = hex.replace('#', '').trim()
      const n = parseInt(h.length === 3 ? h.split('').map(x => x + x).join('') : h, 16)
      const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255
      return `rgba(${r}, ${g}, ${b}, ${a})`
    }

    new Chart(el.getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'S/ por día',
          data,
          borderWidth: 2,
          borderColor: BRAND,
          backgroundColor: hexToRgba(BRAND, 0.18),
          hoverBackgroundColor: hexToRgba(BRAND, 0.28),
          borderRadius: 10
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 250 },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            ticks: { callback: (v) => 'S/ ' + Number(v).toLocaleString() }
          }
        },
        plugins: {
          legend: { display: false },
          datalabels: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) =>
                'S/ ' + Number(ctx.parsed.y ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            }
          }
        }
      }
    })
  }
})