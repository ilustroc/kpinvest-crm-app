// resources/js/clientes/show/propuesta-modal.js

export function initPropuestaModal({ selection }) {
  const tipoSel   = document.getElementById('tipoPropuesta');
  const tipoTag   = document.getElementById('modalTipoTag');
  const formConv  = document.getElementById('formConvenio');
  const formCanc  = document.getElementById('formCancelacion');
  const hintBalon = document.getElementById('hintBalon');
  const err       = document.getElementById('cvErr');

  const nro   = document.getElementById('cvNro');
  const total = document.getElementById('cvTotal');
  const cuota = document.getElementById('cvCuota');
  const fIni  = document.getElementById('cvFechaIni');
  const gen   = document.getElementById('cvGen');
  const tblEl = document.getElementById('tblCrono');
  const suma  = document.getElementById('cvSuma');
  const hid   = document.getElementById('cvHidden');
  const hintDia = document.getElementById('cvHintDia');
  const btnGuardar = document.querySelector('#formPropuesta button[type="submit"]');
  const cronBalonInput = document.getElementById('cronBalon');

  const tbl = tblEl?.querySelector('tbody');
  const modalProp = document.getElementById('modalPropuesta');
  if (!modalProp) return;

  // chips/hidden de operaciones al abrir
  const opsResumen = document.getElementById('opsResumen');
  const opsHidden  = document.getElementById('opsHidden');

  const cls = (el, on, klass) => el && el.classList.toggle(klass, !!on);

  // reemplaza: show.bs.modal
  modalProp.addEventListener('modal:open', () => {
    const ops = selection?.refresh ? selection.refresh() : [];

    if (opsResumen) {
      opsResumen.innerHTML = ops.length
        ? ops.map(o => `
            <span class="inline-flex items-center px-2 py-0.5 rounded-full
                         text-[11px] font-black bg-white/70 border border-slate-200/70 mr-1">
              ${o}
            </span>
          `).join('')
        : '<span class="text-slate-500 text-sm">Ninguna</span>';
    }

    if (opsHidden) {
      opsHidden.innerHTML = '';
      ops.forEach(op => {
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = 'operaciones[]'; i.value = String(op);
        opsHidden.appendChild(i);
      });
    }

    applyTipoUI();
    total?.dispatchEvent(new Event('input'));
  });

  const to2  = (n) => String(n).padStart(2,'0');
  const fmt2 = (n) => (Math.round((Number(n)||0)*100)/100).toFixed(2);
  const num  = (v) => {
    const s = String(v ?? '').replace(/[^\d,.\-]/g,'').replace(/,/g,'');
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  };

  function addMonthsNoOverflow(base, months){
    const d = new Date(base);
    const day = d.getDate();
    d.setMonth(d.getMonth() + months);
    if (d.getDate() !== day) d.setDate(0);
    return d;
  }

  function renderRows(nBase){
    if (!tbl) return;
    const n = Math.max(1, parseInt(nBase || '1', 10));
    tbl.innerHTML = '';

    for (let i=1; i<=n; i++){
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="text-center text-xs font-black text-slate-500">${to2(i)}</td>

        <td>
          <input type="date"
            class="cr-fecha w-full rounded-xl border border-slate-200/70 bg-white/70
                   px-3 py-2 text-sm shadow-sm outline-none transition
                   focus:border-emerald-500/50 focus:ring-4 focus:ring-emerald-500/15" />
        </td>

        <td>
          <input type="number" step="0.01" min="0.01"
            class="cr-monto w-full rounded-xl border border-slate-200/70 bg-white/70
                   px-3 py-2 text-sm shadow-sm outline-none transition
                   focus:border-emerald-500/50 focus:ring-4 focus:ring-emerald-500/15" />
        </td>
      `;
      tbl.appendChild(tr);
    }

    recalc();
  }

  function recalc(){
    if (!tbl) return;

    const rows = [...tbl.querySelectorAll('tr')];
    const convenio = num(total?.value);

    let s = 0;
    rows.forEach(tr => s += num(tr.querySelector('.cr-monto')?.value));

    if (suma) suma.textContent = fmt2(s);

    // hidden inputs
    if (hid){
      hid.innerHTML = '';
      rows.forEach(tr => {
        const f = tr.querySelector('.cr-fecha')?.value || '';
        const m = tr.querySelector('.cr-monto')?.value || '';
        hid.insertAdjacentHTML('beforeend', `<input type="hidden" name="cron_fecha[]" value="${f}">`);
        hid.insertAdjacentHTML('beforeend', `<input type="hidden" name="cron_monto[]" value="${m}">`);
      });
    }

    const ok = Math.abs(s - convenio) <= 0.01;

    if (btnGuardar) btnGuardar.disabled = !ok;

    // Tailwind classes en vez de text-danger / d-none
    if (suma) {
      suma.classList.toggle('text-rose-600', !ok);
      suma.classList.toggle('text-slate-700', ok);
    }
    cls(err, ok, 'hidden');
  }

  function genAuto(){
    if (!tbl) return;
    const n = Math.max(1, parseInt(nro?.value || '0', 10));
    if (!n) return;

    renderRows(n);

    const start = fIni?.value ? new Date(fIni.value + 'T00:00:00') : null;
    const convenio   = num(total?.value);
    const montoCuota = num(cuota?.value);
    const rows = [...tbl.querySelectorAll('tr')];

    rows.forEach((tr, idx) => {
      const f = tr.querySelector('.cr-fecha');
      const m = tr.querySelector('.cr-monto');

      if (start){
        const d = addMonthsNoOverflow(start, idx);
        f.valueAsDate = d;
      }

      const val = (montoCuota > 0) ? montoCuota : (convenio / rows.length);
      m.value = fmt2(val);
    });

    recalc();
  }

  function applyTipoUI(){
    const t = (tipoSel?.value || '').toLowerCase();
    const isConv = (t === 'convenio' || t === 'convenio_balon');

    // Tailwind: hidden
    cls(formConv, !isConv, 'hidden');
    cls(formCanc,  isConv, 'hidden');

    const req = (el, on)=> el && (on ? el.setAttribute('required','required') : el.removeAttribute('required'));
    req(nro,   isConv);
    req(total, isConv);

    req(document.querySelector('[name="fecha_pago_cancel"]'), !isConv);
    req(document.querySelector('[name="monto_cancel"]'),     !isConv);

    if (tipoTag) tipoTag.textContent = tipoSel?.selectedOptions?.[0]?.textContent?.trim() || 'Convenio';

    if (hintBalon) hintBalon.classList.add('hidden');

    if (tbl) renderRows(nro?.value || 1);
  }

  // listeners
  tipoSel?.addEventListener('change', applyTipoUI);
  gen?.addEventListener('click', genAuto);
  nro?.addEventListener('change', () => renderRows(nro.value));
  tblEl?.addEventListener('input', (e) => {
    if (e.target.matches('.cr-monto, .cr-fecha')) recalc();
  });
  total?.addEventListener('input', recalc);

  fIni?.addEventListener('change', () => {
    const v = fIni.value;
    if (!hintDia) return;
    if (!v){ hintDia.textContent = 'Día de pago: —'; return; }
    const d = new Date(v + 'T00:00:00');
    hintDia.textContent = `Día de pago: ${d.getDate()} de cada mes`;
  });

  document.getElementById('formPropuesta')?.addEventListener('submit', (e) => {
    const t = (tipoSel?.value || '').toLowerCase();
    const isConv = (t === 'convenio' || t === 'convenio_balon');
    if (!isConv) return;

    const convenio = num(total?.value);
    const rows = tbl ? [...tbl.querySelectorAll('tr')] : [];
    let s = 0;
    rows.forEach(tr => s += num(tr.querySelector('.cr-monto')?.value));

    const ok = Math.abs(s - convenio) <= 0.01;
    if (!ok) {
      e.preventDefault();
      e.stopPropagation();
      if (suma) suma.classList.add('text-rose-600');
      if (err) err.classList.remove('hidden');
      alert('No se puede guardar: el total del cronograma debe coincidir con el Monto convenio.');
      return;
    }

    // índice cuota balón = última fila (si aplica)
    if (cronBalonInput) {
      const rowsCount = rows.length || 0;
      cronBalonInput.value = (t === 'convenio_balon' && rowsCount) ? String(rowsCount) : '';
    }
  }, true);

  // init
  renderRows(nro?.value || 1);
  applyTipoUI();
}