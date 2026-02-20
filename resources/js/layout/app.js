(() => {
  const rail = document.getElementById('kpRail');
  const backdrop = document.getElementById('kpBackdrop');
  const btn = document.getElementById('kpMenuBtn');

  if (!rail || !backdrop) return;

  const openRail = () => {
    rail.classList.remove('-translate-x-full');
    backdrop.classList.remove('hidden');
    document.documentElement.classList.add('overflow-hidden');
  };

  const closeRail = () => {
    rail.classList.add('-translate-x-full');
    backdrop.classList.add('hidden');
    document.documentElement.classList.remove('overflow-hidden');
  };

  btn?.addEventListener('click', openRail);
  backdrop.addEventListener('click', closeRail);

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeRail();
  });

  // cerrar rail al navegar (solo móvil)
  document.querySelectorAll('[data-close-rail]').forEach(a => {
    a.addEventListener('click', () => {
      if (window.innerWidth < 1024) closeRail();
    });
  });

  // acordeones
  const setChev = (key, open) => {
    const chev = document.querySelector(`[data-acc-chev="${key}"]`);
    if (!chev) return;
    chev.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
  };

  document.querySelectorAll('[data-acc-btn]').forEach(btnAcc => {
    const key = btnAcc.getAttribute('data-acc-btn');
    const panel = document.querySelector(`[data-acc-panel="${key}"]`);
    if (!key || !panel) return;

    const initOpen = btnAcc.getAttribute('aria-expanded') === 'true';
    setChev(key, initOpen);

    btnAcc.addEventListener('click', () => {
      const willOpen = panel.classList.contains('hidden');
      panel.classList.toggle('hidden');
      btnAcc.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      setChev(key, willOpen);
    });
  });
})();