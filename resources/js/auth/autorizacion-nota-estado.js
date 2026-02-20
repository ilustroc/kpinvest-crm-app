document.addEventListener('DOMContentLoaded', () => {
  // ============== Modal de NOTA (Pre-aprobar / Aprobar) ==============
  (function () {
    const frm     = document.getElementById('formNotaEstado');
    const title   = document.getElementById('modalNotaEstadoTitulo');
    const txt     = document.getElementById('notaEstadoTxt');
    const modalEl = document.getElementById('modalNotaEstado');

    if (!frm || !title || !txt || !modalEl) return;

    let modal;
    function ensureModal() {
      if (!window.bootstrap) {
        console.warn('Bootstrap no está cargado (bootstrap.Modal).');
        return null;
      }
      if (!modal) modal = new bootstrap.Modal(modalEl);
      return modal;
    }

    document.querySelectorAll('.js-open-nota').forEach(btn => {
      btn.addEventListener('click', () => {
        frm.setAttribute('action', btn.dataset.action || '#');
        title.textContent = btn.dataset.title || 'Agregar nota';
        txt.value = '';
        const m = ensureModal();
        if (!m) return;
        m.show();
        setTimeout(() => txt.focus(), 120);
      });
    });
  })();

  // =========================== Rechazo (modal) =========================
  document.querySelectorAll('.js-open-rechazo').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const form = document.getElementById('formRechazo');
      if (!form) return;
      form.setAttribute('action', btn.dataset.action || '#');
      setTimeout(()=> document.getElementById('motivoTxt')?.focus(), 150);
    });
  });
});