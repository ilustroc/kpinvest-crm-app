// resources/js/clientes/show/modal-nota.js
export function initModalNota() {
  const modal = document.getElementById('modalNota');
  if (!modal) return;

  modal.addEventListener('show.bs.modal', (ev) => {
    const btn = ev.relatedTarget;
    if (!btn) return;

    let txt = '';
    if (btn.hasAttribute('data-nota-json')) {
      try {
        txt = JSON.parse(btn.getAttribute('data-nota-json') || '""') || '';
      } catch {
        txt = '';
      }
    } else if (btn.hasAttribute('data-nota')) {
      txt = btn.getAttribute('data-nota') || '';
    }

    const tgt = modal.querySelector('#notaFull');
    if (tgt) tgt.textContent = String(txt);
  });
}