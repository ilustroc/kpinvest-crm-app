// resources/js/clientes/show/once-forms.js
export function initOnceForms() {
  document.querySelectorAll('form[data-once]').forEach((form) => {
    let locked = false;

    form.addEventListener('submit', (ev) => {
      if (locked) { ev.preventDefault(); return false; }
      if (!form.checkValidity()) return;

      locked = true;
      form.setAttribute('aria-busy', 'true');

      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
        btn.disabled = true;
        if (btn.tagName === 'BUTTON') {
          btn.dataset.prev = btn.innerHTML;
          btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Enviando...';
        }
      });

      form.addEventListener('keydown', (e) => {
        if (locked && e.key === 'Enter') e.preventDefault();
      });
    }, { capture: true });
  });
}