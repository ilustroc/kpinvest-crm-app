// resources/js/clientes/show/cronograma-modal.js
export function initCronogramaModal() {
  const tbl = document.getElementById('tblPromesas');
  const cronModal = document.getElementById('cronModal');
  if (!tbl || !cronModal || !window.bootstrap) return;

  const modal = new bootstrap.Modal(cronModal);
  const tb = document.getElementById('cronTbody');
  const thBalon = document.getElementById('thBalon');

  const fmtFecha = (iso) => {
    if (!iso) return '—';
    const d = new Date(String(iso).split(' ')[0] + 'T00:00:00');
    return isNaN(d)
      ? '—'
      : d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  const fmtMonto = (n) => 'S/ ' + (Number(n) || 0).toFixed(2);

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
}