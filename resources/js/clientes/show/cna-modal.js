// resources/js/clientes/show/cna-modal.js
export function initCnaModal() {
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
}