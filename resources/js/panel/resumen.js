document.addEventListener('DOMContentLoaded', () => {

  /* ====================== AUTOCOMPLETE ====================== */
  (function(){
    const frm = document.getElementById('frmQuick');
    const inp = document.getElementById('inpQuick');
    const sug = document.getElementById('quickSug');
    if (!frm || !inp || !sug) return;

    let timer = null, idx = -1;

    const meta = document.querySelector('meta[name="clientes-suggest-url"]');
    const SUG_URL = meta?.content || '';

    function hide(){ sug.classList.remove('show'); sug.innerHTML=''; idx=-1; }
    function show(){ if(!sug.classList.contains('show')) sug.classList.add('show'); }

    const escRx = s => s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
    function mark(txt, q){
      if(!q) return txt;
      const rx = new RegExp('('+escRx(q)+')','ig');
      return String(txt||'').replace(rx,'<span class="quick-hl">$1</span>');
    }

    function setActive(i){
      const items = [...sug.querySelectorAll('.quick-item')];
      items.forEach((a,k)=>a.classList.toggle('active', k===i));
      idx = i;
    }

    function render(items, q){
      if(!items.length){ hide(); return; }

      sug.innerHTML = items.map((it,i)=>`
        <a href="${it.url}" class="quick-item ${i===0?'active':''}" data-idx="${i}">
          <div class="quick-left">
            <span class="quick-dni">${mark(it.dni,q)}</span>
            <span class="quick-sep">»</span>
            <span class="quick-name">${mark(it.nombre,q)}</span>
          </div>
          <div class="quick-meta">${mark(it.operacion||'—',q)} · ${it.cosecha||'—'}</div>
        </a>
      `).join('');

      sug.querySelectorAll('.quick-item').forEach((a,i)=>{
        a.addEventListener('mouseenter',()=>setActive(i));
      });

      show(); idx=0;
    }

    function fetchSuggest(q){
      if (!SUG_URL) { hide(); return; }
      if (!q || q.trim().length < 1){ hide(); return; }

      fetch(SUG_URL + '?q=' + encodeURIComponent(q.trim()), {
        headers:{ 'X-Requested-With':'XMLHttpRequest' }
      })
        .then(r => r.json())
        .then(data => render(data, q))
        .catch(() => hide());
    }

    inp.addEventListener('input', ()=>{
      clearTimeout(timer);
      timer = setTimeout(()=>fetchSuggest(inp.value), 140);
    });
    inp.addEventListener('focus', ()=>{ if(inp.value.trim()) fetchSuggest(inp.value); });

    inp.addEventListener('keydown', (e)=>{
      const items = [...sug.querySelectorAll('.quick-item')];
      if(e.key==='ArrowDown' && items.length){ e.preventDefault(); setActive(Math.min(idx+1, items.length-1)); }
      if(e.key==='ArrowUp'   && items.length){ e.preventDefault(); setActive(Math.max(idx-1, 0)); }
      if(e.key==='Enter'     && items.length && idx>=0){ e.preventDefault(); items[idx].click(); }
      if(e.key==='Escape'){ hide(); }
    });

    document.addEventListener('click', (e)=>{ if(!e.target.closest('#frmQuick')) hide(); });
  })();


  /* ====================== SELECTOR DE MES ====================== */
  document.getElementById('mesPicker')?.addEventListener('change', (e)=>{
    const ym = e.target.value || '';
    const url = new URL(window.location.href);
    url.searchParams.set('mes', ym);
    window.location.assign(url.toString());
  });


  /* ====================== CHART: PAGOS DEL MES ====================== */
  (function(){
    const el = document.getElementById('chartPagos');
    if(!el) return;

    if (!window.Chart) {
      console.warn('Chart.js no está cargado');
      return;
    }

    const payload = (()=>{ try{ return JSON.parse(el.dataset.chart||'{}'); }catch(_){ return {}; }})();
    const labels = payload.labels || [];
    const data   = payload.data   || [];

    const css    = (v)=>getComputedStyle(document.documentElement).getPropertyValue(v).trim();
    const ACCENT = css('--accent') || '#0b4ea2';

    const hexToRgba = (hex, a=1)=>{
      const h = hex.replace('#','').trim();
      const bigint = parseInt(h.length===3 ? h.split('').map(x=>x+x).join('') : h, 16);
      const r=(bigint>>16)&255, g=(bigint>>8)&255, b=bigint&255;
      return `rgba(${r}, ${g}, ${b}, ${a})`;
    };

    new Chart(el.getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'S/ por día',
          data,
          borderWidth: 2,
          borderColor: ACCENT,
          backgroundColor: hexToRgba(ACCENT, .15),
          hoverBackgroundColor: hexToRgba(ACCENT, .25),
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 250 },
        scales: {
          x: { grid: { display:false } },
          y: {
            beginAtZero:true,
            ticks: { callback:(v)=>'S/ '+Number(v).toLocaleString() }
          }
        },
        plugins: {
          legend: { display:false },
          tooltip: {
            callbacks: {
              label: (ctx)=> 'S/ ' + Number(ctx.parsed.y ?? 0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})
            }
          }
        }
      }
    });
  })();

});