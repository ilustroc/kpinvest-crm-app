// resources/js/clientes/show/ui/modal.js

function isHidden(el) {
  return el.classList.contains('hidden') || el.getAttribute('aria-hidden') === 'true';
}

export function openModal(modalEl) {
  if (!modalEl) return;

  modalEl.classList.remove('hidden');
  // Si tu modal usa flex para centrar:
  modalEl.classList.add('flex');
  modalEl.setAttribute('aria-hidden', 'false');
  document.body.classList.add('overflow-hidden');

  // evento propio (para reemplazar show.bs.modal)
  modalEl.dispatchEvent(new CustomEvent('modal:open', { bubbles: true }));
}

export function closeModal(modalEl) {
  if (!modalEl) return;

  modalEl.classList.add('hidden');
  modalEl.classList.remove('flex');
  modalEl.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('overflow-hidden');

  modalEl.dispatchEvent(new CustomEvent('modal:close', { bubbles: true }));
}

/**
 * Soporta:
 *  - data-modal-open="#modalPropuesta"
 *  - (compat) data-bs-toggle="modal" data-bs-target="#modalPropuesta"
 *
 * Dentro del modal:
 *  - data-modal-close (botones)
 *  - data-modal-overlay (fondo)
 */
export function bindModalTriggers(root = document) {
  // Openers
  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-modal-open], [data-bs-toggle="modal"][data-bs-target]');
    if (!btn) return;

    const target =
      btn.getAttribute('data-modal-open') ||
      btn.getAttribute('data-bs-target');

    if (!target) return;

    const modalEl = document.querySelector(target);
    if (!modalEl) return;

    e.preventDefault();
    openModal(modalEl);
  });

  // Closers (overlay o botón)
  root.addEventListener('click', (e) => {
    const overlay = e.target.closest('[data-modal-overlay]');
    const closer  = e.target.closest('[data-modal-close]');
    if (!overlay && !closer) return;

    const modalEl = e.target.closest('[data-modal]');
    if (!modalEl) return;

    e.preventDefault();
    closeModal(modalEl);
  });

  // ESC
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    const openModals = [...document.querySelectorAll('[data-modal]')].filter(m => !isHidden(m));
    const top = openModals.at(-1);
    if (top) closeModal(top);
  });
}