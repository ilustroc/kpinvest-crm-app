// resources/js/clientes/show/cartas-dropdown.js
export function initCartasDropdown() {
  const roots = Array.from(document.querySelectorAll('[data-dd="ccd"]'));
  if (!roots.length) return;

  const close = (root) => {
    const btn = root.querySelector('[data-dd-btn]');
    const menu = root.querySelector('[data-dd-menu]');
    if (!btn || !menu) return;
    menu.classList.remove('show');
    btn.setAttribute('aria-expanded', 'false');
  };

  const toggle = (root) => {
    const btn = root.querySelector('[data-dd-btn]');
    const menu = root.querySelector('[data-dd-menu]');
    if (!btn || !menu) return;

    const isOpen = menu.classList.contains('show');
    roots.forEach(r => close(r)); // cierra otros
    if (!isOpen) {
      menu.classList.add('show');
      btn.setAttribute('aria-expanded', 'true');
    }
  };

  roots.forEach((root) => {
    const btn = root.querySelector('[data-dd-btn]');
    const menu = root.querySelector('[data-dd-menu]');
    if (!btn || !menu) return;

    // estado inicial cerrado
    menu.classList.remove('show');
    btn.setAttribute('aria-expanded', 'false');

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggle(root);
    });

    // cerrar al elegir una carta
    menu.addEventListener('click', (e) => {
      const a = e.target.closest('[data-dd-item]');
      if (a) close(root);
    });
  });

  // click afuera cierra
  document.addEventListener('click', (e) => {
    roots.forEach((root) => {
      if (!root.contains(e.target)) close(root);
    });
  });

  // ESC cierra
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') roots.forEach(r => close(r));
  });
}