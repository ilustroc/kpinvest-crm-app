document.addEventListener('DOMContentLoaded', () => {
  (function(){
    const money = (v)=> Number(String(v ?? 0).replaceAll(',','')).toLocaleString('es-PE', {minimumFractionDigits:2, maximumFractionDigits:2});
    const $ = (id)=> document.getElementById(id);
    const tbody = $('cna_pagos_tbody');

    const meta = document.querySelector('meta[name="autz-pagos-url"]');
    const RUTA_PAGOS = meta?.content || ''; // debe venir con __DNI__

    const toDMY = (val) => {
      if (!val) return '—';
      const s = String(val).trim();
      const datePart = s.split(' ')[0];
      const sep = datePart.includes('-') ? '-' : '/';
      const parts = datePart.split(sep);
      if (parts.length === 3) {
        const [y,m,d] = parts;
        if (y && m && d) return `${d.padStart(2,'0')}/${m.padStart(2,'0')}/${y}`;
      }
      return s;
    };

    function setRowsEmpty(){
      if (!tbody) return;
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Sin pagos registrados.</td></tr>';
    }

    function addPagoRow(p){
      const tr = document.createElement('tr');
      const fechaStr = p.fecha ? new Date(p.fecha).toLocaleDateString('es-PE') : '—';
      tr.innerHTML = `
        <td class="text-nowrap">${p.operacion ?? p.oper ?? '—'}</td>
        <td class="text-nowrap">${fechaStr}</td>
        <td class="text-end text-nowrap">${money(p.monto_pagado ?? p.monto ?? 0)}</td>
        <td class="text-nowrap">${p.gestor ?? '—'}</td>
        <td class="text-nowrap">${p.entidad ?? '—'}</td>
        <td class="text-nowrap">${p.cosecha ?? '—'}</td>
        <td class="text-nowrap">${p.cuenta_recaudo ?? p.cuenta ?? '—'}</td>
      `;
      tbody.appendChild(tr);
    }

    document.querySelectorAll('.js-ver-cna').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const dni = (btn.dataset.dni || '').trim();

        $('cna_dni').textContent    = dni || '—';
        $('cna_carta').textContent  = btn.dataset.nrocarta || '—';

        $('cna_fecha').textContent       = toDMY(btn.dataset.fecha);

        let ops = [];
        try { ops = JSON.parse(btn.getAttribute('data-operaciones')||'[]'); } catch(_){}
        $('cna_ops').textContent         = ops.length ? ops.join(', ') : '—';

        $('cna_fecha_pago').textContent   = toDMY(btn.dataset.fechaPago);
        $('cna_monto_pagado').textContent = money(btn.dataset.montoPagado);
        $('cna_obs').textContent          = (btn.dataset.observacion || '—');

        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-3">Cargando…</td></tr>';

        let total = 0;

        try{
          if (!RUTA_PAGOS || !dni) throw new Error('Ruta de pagos o DNI vacío');
          const url = RUTA_PAGOS.replace('__DNI__', encodeURIComponent(dni));
          const r   = await fetch(url, { headers: { 'Accept': 'application/json' } });
          if (!r.ok) throw new Error('HTTP '+r.status);

          const j   = await r.json();
          const arr = Array.isArray(j.pagos) ? j.pagos : [];

          if (!arr.length) {
            setRowsEmpty();
          } else {
            tbody.innerHTML = '';
            arr.forEach(p=>{
              total += Number(String(p.monto_pagado ?? p.monto ?? 0).replaceAll(',','')) || 0;
              addPagoRow(p);
            });
          }
        }catch(e){
          console.error('CNA pagos fetch error:', e);
          if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">Error cargando pagos.</td></tr>';
        }

        $('cna_total_pagos').textContent = 'S/ ' + money(total);
      });
    });
  })();
});