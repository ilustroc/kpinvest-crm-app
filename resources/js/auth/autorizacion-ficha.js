document.addEventListener('DOMContentLoaded', () => {
  const fmt = (n)=> (Math.round((Number(n)||0)*100)/100).toFixed(2);

  document.querySelectorAll('.js-ver-ficha').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const tipo = (btn.dataset.tipo || '').toLowerCase();

      const setRaw = (id, v)=>{ const el = document.getElementById(id); if(el) el.textContent = v || '—'; };
      const set = (id, v)=>{ const el = document.getElementById('t_'+id); if(el) el.textContent = v || '—'; };

      // Encabezado
      setRaw('f_dni',  btn.dataset.dni || '—');
      setRaw('f_op',   btn.dataset.operacion || '—');
      setRaw('t_fecha',btn.dataset.fecha || '—');

      // Datos generales
      set('tipo',   tipo ? (tipo==='cancelacion' ? 'Cancelación' : 'Convenio') : '—');
      set('asesor', btn.dataset.asesor);
      set('titular',btn.dataset.titular);
      set('deuda',  btn.dataset.deuda);
      set('neg',    btn.dataset.negociado);

      // Notas (robusto)
      const notaGen = [btn.dataset.notaGen, btn.dataset.detalle, btn.dataset.nota]
        .map(v => (v||'').trim()).find(Boolean) || '';
      const notaSup = [btn.dataset.notaSup, btn.dataset.notaPreaprobacion]
        .map(v => (v||'').trim()).find(Boolean) || '';

      const ngWrap = document.getElementById('nota_general_wrap');
      const nsWrap = document.getElementById('nota_sup_wrap');

      if (ngWrap) {
        if (notaGen) { ngWrap.style.display='block'; document.getElementById('nota_general_txt').textContent = notaGen; }
        else { ngWrap.style.display='none'; }
      }
      if (nsWrap) {
        if (notaSup) { nsWrap.style.display='block'; document.getElementById('nota_sup_txt').textContent = notaSup; }
        else { nsWrap.style.display='none'; }
      }

      // ===== Acordeón por cuenta =====
      const acc = document.getElementById('acc_cuentas');
      if (acc) {
        acc.innerHTML = '';
        let cuentas = [];
        try {
          const raw = btn.getAttribute('data-cuentas');
          cuentas = raw ? JSON.parse(raw) : [];
        } catch(e) { cuentas = []; }

        if (!cuentas.length) {
          acc.innerHTML = '<div class="text-secondary small">No se encontraron cuentas asociadas.</div>';
        } else {
          cuentas.forEach((c, idx)=>{
            const id = 'accItem_'+idx;
            const anioCastigo = c && c.fecha_castigo ? String(c.fecha_castigo).slice(0,4) : '—';

            acc.insertAdjacentHTML('beforeend', `
              <div class="accordion-item">
                <h2 class="accordion-header" id="${id}_h">
                  <button class="accordion-button ${idx>0?'collapsed':''}" type="button"
                          data-bs-toggle="collapse" data-bs-target="#${id}_c"
                          aria-expanded="${idx===0?'true':'false'}" aria-controls="${id}_c">
                    Operación ${c?.operacion || '—'} · ${c?.entidad || '—'} · ${c?.producto || '—'} · ${c?.cosecha || '—'}
                  </button>
                </h2>
                <div id="${id}_c" class="accordion-collapse collapse ${idx===0?'show':''}"
                    aria-labelledby="${id}_h" data-bs-parent="#acc_cuentas">
                  <div class="accordion-body p-2">
                    <table class="table table-sm mb-0">
                      <tbody>
                        <tr><th style="width:220px">Número de Operación</th><td>${c?.operacion || '—'}</td></tr>
                        <tr><th>Año Castigo</th><td>${anioCastigo}</td></tr>
                        <tr><th>Entidad</th><td>${c?.entidad || '—'}</td></tr>
                        <tr><th>Producto</th><td>${c?.producto || '—'}</td></tr>
                        <tr><th>Cosecha</th><td>${c?.cosecha || '—'}</td></tr>
                        <tr><th>Capital</th><td>S/ ${fmt(c?.saldo_capital)}</td></tr>
                        <tr><th>Deuda Total</th><td>S/ ${fmt(c?.deuda_total)}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            `);
          });
        }
      }

      // ===== Cronograma (no aplica para cancelación)
      const cronoWrap  = document.getElementById('crono_wrap');
      const cronoBody  = document.getElementById('crono_body');
      const cronoTotal = document.getElementById('crono_total');
      const titulo     = document.getElementById('crono_titulo');

      if (!cronoWrap || !cronoBody || !cronoTotal || !titulo) return;

      let crono = [];
      try { crono = JSON.parse(btn.getAttribute('data-crono') || '[]'); } catch(_) { crono = []; }

      if (tipo === 'cancelacion') {
        cronoWrap.classList.add('d-none');
        return;
      }

      const hasBalon = (btn.dataset.hasbalon === '1') ||
                       crono.some(r => r?.es_balon === true || r?.es_balon === 1 || r?.es_balon === '1');

      titulo.textContent = hasBalon ? 'Cronograma de cuotas (con balón)' : 'Cronograma de cuotas';

      cronoBody.innerHTML = '';
      let sum = 0;

      crono.forEach(r=>{
        sum += Number(r.monto) || 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="text-center">${String(r.nro ?? '').padStart(2,'0')}</td>
          <td class="text-center">${r.fecha || '—'}</td>
          <td class="text-end">${fmt(r.monto)}</td>
          <td class="text-center">${(r.es_balon ? 'BALÓN' : '')}</td>
        `;
        cronoBody.appendChild(tr);
      });

      cronoTotal.textContent = fmt(sum);
      cronoWrap.classList.remove('d-none');
    });
  });
});