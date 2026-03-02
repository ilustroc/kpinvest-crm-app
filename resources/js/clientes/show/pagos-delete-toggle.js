// resources/js/clientes/show/pagos-delete-toggle.js
export function initPagosDeleteToggle() {
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
    document.querySelectorAll('.col-del').forEach((el) => el.classList.toggle('d-none', !on));
    if (!on) {
      chkAll && (chkAll.checked = false);
      table?.querySelectorAll('.chkPago').forEach((ch) => (ch.checked = false));
      updateDeleteBtn();
    }
  }

  toggle.addEventListener('change', () => showDeleteCols(toggle.checked));

  chkAll?.addEventListener('change', () => {
    table?.querySelectorAll('.chkPago').forEach((ch) => (ch.checked = chkAll.checked));
    updateDeleteBtn();
  });

  table?.addEventListener('change', (e) => {
    if (e.target.classList.contains('chkPago')) updateDeleteBtn();
  });

  showDeleteCols(false);
}