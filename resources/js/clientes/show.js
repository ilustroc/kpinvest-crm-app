// public/js/clientes/show.js
document.addEventListener('DOMContentLoaded', () => {
  // Bootstrap requerido
  if (!window.bootstrap) console.warn('Bootstrap no está cargado (bootstrap.*).');

  /* ================== Tooltips ================== */
  try {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  } catch (_) {}

  /* ================== Modal Nota (data-nota / data-nota-json) ================== */
  (() => {
    const modal = document.getElementById('modalNota');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', (ev) => {
      const btn = ev.relatedTarget;
      if (!btn) return;

      let txt = '';
      if (btn.hasAttribute('data-nota-json')) {
        try { txt = JSON.parse(btn.getAttribute('data-nota-json') || '""') || ''; }
        catch { txt = ''; }
      } else if (btn.hasAttribute('data-nota')) {
        txt = btn.getAttribute('data-nota') || '';
      }

      const tgt = modal.querySelector('#notaFull');
      if (tgt) tgt.textContent = String(txt);
    });
  })();

  /* ================== Ver Cronograma (tabla Promesas -> cronModal) ================== */
  (() => {
    const tbl = document.getElementById('tblPromesas');
    const cronModal = document.getElementById('cronModal');
    if (!tbl || !cronModal || !window.bootstrap) return;

    const modal = new bootstrap.Modal(cronModal);
    const tb = document.getElementById('cronTbody');
    const thBalon = document.getElementById('thBalon');

    const fmtFecha = (iso) => {
      if (!iso) return '—';
      const d = new Date(String(iso).split(' ')[0] + 'T00:00:00');
      return isNaN(d) ? '—' : d.toLocaleDateString('es-PE', {day:'2-digit', month:'2-digit', year:'numeric'});
    };
    const fmtMonto = (n) => 'S/ ' + (Number(n)||0).toFixed(2);

    tbl.addEventListener('click', (e) => {
      const tr = e.target.closest('tr[data-open-cronograma]');
      if (!tr) return;

      const ds = tr.getAttribute('data-dataset');
      if (!ds) return;

      let data = null;
      try { data = JSON.parse(ds); } catch { data = null; }
      if (!data || !tb) return;

      const title = cronModal.querySelector('.modal-title');
      if (title) {
        title.textContent =
          'Cronograma — ' + (
            data.tipo === 'convenio_balon' ? 'Convenio [Cuota Balón]' :
            data.tipo === 'convenio'       ? 'Convenio' :
            'Cancelación'
          );
      }

      tb.innerHTML = '';
      const showBalon = (data.tipo === 'convenio_balon');
      thBalon && thBalon.classList.toggle('d-none', !showBalon);

      (data.cuotas || []).forEach((c) => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td class="text-end">${c.nro ?? ''}</td>
          <td>${fmtFecha(c.fecha)}</td>
          <td class="text-end">${fmtMonto(c.monto)}</td>
          ${showBalon ? `<td class="text-center">${(c.es_balon==1)?'<span class="badge text-bg-warning">Balón</span>':''}</td>` : ''}
        `;
        tb.appendChild(row);
      });

      modal.show();
    });
  })();

  /* ================== Selección de cuentas (chkAll + chkOp) ================== */
  const Selection = (() => {
    const chkAll   = document.getElementById('chkAll');
    const chks     = Array.from(document.querySelectorAll('.chkOp'));
    const btnProp  = document.getElementById('btnPropuesta');
    const selCount = document.getElementById('selCount');

    const refresh = () => {
      if (!chks.length) return [];
      const selected = chks.filter(c => c.checked && !c.disabled).map(c => c.value).filter(Boolean);
      if (selCount) selCount.textContent = String(selected.length);
      if (btnProp)  btnProp.disabled = (selected.length === 0);
      return selected;
    };

    if (chkAll) {
      chkAll.addEventListener('change', () => {
        chks.forEach(c => { if(!c.disabled) c.checked = chkAll.checked; });
        refresh();
      });
    }

    chks.forEach(c => c.addEventListener('change', () => {
      const enabled = chks.filter(x => !x.disabled).length;
      const checked = chks.filter(x => x.checked && !x.disabled).length;
      if (chkAll && enabled) chkAll.checked = (checked === enabled);
      refresh();
    }));

    refresh();
    return { refresh };
  })();

  /* ================== Modal Propuesta (cronograma + validación) ================== */
  (() => {
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
    modalProp.addEventListener('show.bs.modal', () => {
      const ops = Selection.refresh();
      if (opsResumen) {
        opsResumen.innerHTML = ops.length
          ? ops.map(o => `<span class="badge rounded-pill text-bg-light border me-1">${o}</span>`).join('')
          : '<span class="text-secondary">Ninguna</span>';
      }
      if (opsHidden) {
        opsHidden.innerHTML = '';
        ops.forEach(op => {
          const i = document.createElement('input');
          i.type = 'hidden'; i.name = 'operaciones[]'; i.value = String(op);
          opsHidden.appendChild(i);
        });
      }
      total?.dispatchEvent(new Event('input'));
    });

    const to2  = n => String(n).padStart(2,'0');
    const fmt2 = n => (Math.round((Number(n)||0)*100)/100).toFixed(2);
    const num  = v => {
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
          <td class="text-center">${to2(i)}</td>
          <td><input type="date" class="form-control form-control-sm cr-fecha"></td>
          <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm cr-monto"></td>
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
      if (suma) suma.classList.toggle('text-danger', !ok);
      if (err)  err.classList.toggle('d-none', ok);
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

      formConv?.classList.toggle('d-none', !isConv);
      formCanc?.classList.toggle('d-none', isConv);

      const req = (el, on)=> el && (on ? el.setAttribute('required','required') : el.removeAttribute('required'));
      req(nro,   isConv);
      req(total, isConv);

      req(document.querySelector('[name="fecha_pago_cancel"]'), !isConv);
      req(document.querySelector('[name="monto_cancel"]'),     !isConv);

      if (tipoTag) tipoTag.textContent = tipoSel?.selectedOptions?.[0]?.textContent?.trim() || 'Convenio';
      if (hintBalon) hintBalon.style.display = 'none';

      if (tbl) renderRows(nro?.value || 1);
    }

    modalProp.addEventListener('show.bs.modal', applyTipoUI);
    tipoSel?.addEventListener('change', applyTipoUI);

    gen?.addEventListener('click', genAuto);
    nro?.addEventListener('change', () => renderRows(nro.value));
    tblEl?.addEventListener('input', e => { if (e.target.matches('.cr-monto, .cr-fecha')) recalc(); });
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
        if (suma) suma.classList.add('text-danger');
        if (err) err.classList.remove('d-none');
        alert('No se puede guardar: el total del cronograma debe coincidir con el Monto convenio.');
        return;
      }

      // índice cuota balón = última fila (si aplica)
      if (cronBalonInput) {
        const rowsCount = rows.length || 0;
        cronBalonInput.value = (t === 'convenio_balon' && rowsCount) ? String(rowsCount) : '';
      }
    }, true);

    // Render inicial
    renderRows(nro?.value || 1);
    applyTipoUI();
  })();

  /* ================== CNA Modal (agrupa por CUENTA) ================== */
  (() => {
    const modal     = document.getElementById('modalCna');
    const opsHidden = document.getElementById('cnaOpsHidden');
    const opsList   = document.getElementById('cnaOpsList');
    const inCuenta  = document.getElementById('cnaCuentaInput');
    const lblCuenta = document.getElementById('cnaCuenta');
    const lblCosech = document.getElementById('cnaCosecha');
    const lblPlant  = document.getElementById('cnaPlantilla');

    if (!modal) return;

    function origenFromCosecha(c){
      c = (c||'').toUpperCase().trim();
      const FAA  = new Set(['BBVA3','BBVA4','BBVA5','BBVA6','CAJAAQP3']);
      const FAA2 = new Set(['BBVA7','BBVA8','CONFIANZA_5']);
      const KPI  = new Set([
        'BBVA1','BBVA2','CAJAAQP1','CAJAAQP2','CAJAAQP4','CAJAAQP5','COMPARTAMOS_1','COMPARTAMOS_2','CONFIANZA','CONFIANZA_2','CONFIANZA_3',
        'CONFIANZA_4','CONFIANZA_6','CONFIANZA_7','CONFIANZA_8','CONFIANZA_9','CONFIANZA_10','CONFIANZA_11','CONFIANZA_12','SEMBRANDO'
      ]);
      if (FAA.has(c))  return {origen:'FONDO ACREENCIA AREQUIPA', serie:'F',  plantilla:'cna_fondo_acreencia_arequipa.docx'};
      if (FAA2.has(c)) return {origen:'ACREENCIA II',            serie:'F2', plantilla:'cna_fondo_acreencia_arequipa_2.docx'};
      if (KPI.has(c))  return {origen:'KP INVEST SAC',           serie:'KPI',plantilla:'cna_kpinvest.docx'};
      return {origen:'(no reconocido)', serie:'—', plantilla:'—'};
    }

    modal.addEventListener('show.bs.modal', (ev) => {
      const btn = ev.relatedTarget;
      if (!btn) return;

      const oper    = btn.getAttribute('data-oper')    || '';
      let   cuenta  = btn.getAttribute('data-cuenta')  || btn.closest('tr')?.getAttribute('data-cuenta') || '';
      const cosecha = btn.getAttribute('data-cosecha') || '';

      if (!cuenta) {
        const tr = document.querySelector(`#tblCuentas tbody tr[data-oper="${oper}"]`);
        cuenta = tr?.getAttribute('data-cuenta') || tr?.querySelector('.genCnaBtn')?.getAttribute('data-cuenta') || '';
      }

      if (inCuenta) inCuenta.value = cuenta;
      if (lblCuenta) lblCuenta.textContent = cuenta || '—';
      if (lblCosech) lblCosech.textContent = cosecha || '—';

      const info = origenFromCosecha(cosecha);
      if (lblPlant) lblPlant.textContent  = `${info.origen} · Serie ${info.serie} · ${info.plantilla}`;

      const rows = Array.from(document.querySelectorAll('#tblCuentas tbody tr'));
      const ops  = rows
        .filter(tr => (tr.getAttribute('data-cuenta') || '') === cuenta)
        .map(tr => tr.getAttribute('data-oper') || tr.querySelector('.genCnaBtn')?.getAttribute('data-oper') || '')
        .filter(Boolean);

      const uniq = [...new Set(ops.length ? ops : [oper].filter(Boolean))];

      if (opsList) {
        opsList.innerHTML = uniq.map(op =>
          `<span class="badge rounded-pill text-bg-light border me-1">${op}</span>`
        ).join('');
      }

      if (opsHidden) {
        opsHidden.innerHTML = '';
        uniq.forEach(op => {
          const i = document.createElement('input');
          i.type = 'hidden'; i.name = 'operaciones[]'; i.value = op;
          opsHidden.appendChild(i);
        });
      }
    });
  })();

  /* ================== Evitar doble submit (data-once) ================== */
  (() => {
    document.querySelectorAll('form[data-once]').forEach(form => {
      let locked = false;

      form.addEventListener('submit', (ev) => {
        if (locked) { ev.preventDefault(); return false; }
        if (!form.checkValidity()) return;

        locked = true;
        form.setAttribute('aria-busy', 'true');

        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
          btn.disabled = true;
          if (btn.tagName === 'BUTTON') {
            btn.dataset.prev = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Enviando...';
          }
        });

        form.addEventListener('keydown', (e) => { if (locked && e.key === 'Enter') e.preventDefault(); });
      }, { capture: true });
    });
  })();

  /* ================== Eliminar Pagos (si existe toggle) ================== */
  (() => {
    const toggle = document.getElementById('toggleDeletePagos');
    if (!toggle) return;

    const table  = document.getElementById('tblPagos');
    const btnDel = document.getElementById('btnDeletePagos');
    const chkAll = document.getElementById('chkAllPagos');

    function updateDeleteBtn() {
      const any = table ? table.querySelectorAll('.chkPago:checked').length > 0 : false;
      if (btnDel) btnDel.disabled = !any;
    }

    function showDeleteCols(on) {
      document.querySelectorAll('.col-del').forEach(el => el.classList.toggle('d-none', !on));
      if (!on) {
        chkAll && (chkAll.checked = false);
        table?.querySelectorAll('.chkPago').forEach(ch => ch.checked = false);
        updateDeleteBtn();
      }
    }

    toggle.addEventListener('change', () => showDeleteCols(toggle.checked));
    chkAll?.addEventListener('change', () => {
      table?.querySelectorAll('.chkPago').forEach(ch => ch.checked = chkAll.checked);
      updateDeleteBtn();
    });

    table?.addEventListener('change', (e) => {
      if (e.target.classList.contains('chkPago')) updateDeleteBtn();
    });

    showDeleteCols(false);
  })();
});