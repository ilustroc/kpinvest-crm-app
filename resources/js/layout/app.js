(() => {
  const rail = document.getElementById('kpRail');
  const backdrop = document.getElementById('kpBackdrop');
  const btn = document.getElementById('kpMenuBtn');

  if (!rail || !backdrop) return;

  const openRail = () => {
    rail.classList.remove('-translate-x-full');
    backdrop.classList.remove('hidden');
    document.documentElement.classList.add('overflow-hidden');

    setTimeout(() => document.getElementById('inpQuickDni')?.focus(), 80);
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

  // ===== Quick DNI (sidebar) =====
  const frmQuick = document.getElementById('frmQuickDni');
  const inpQuick = document.getElementById('inpQuickDni');

  if (frmQuick && inpQuick) {
    const tpl = frmQuick.getAttribute('data-show-url'); // .../__DNI__

    // solo números mientras escribe
    inpQuick.addEventListener('input', () => {
      inpQuick.value = (inpQuick.value || '').replace(/\D/g, '').slice(0, 8);
      inpQuick.classList.remove('border-rose-400');
    });

    frmQuick.addEventListener('submit', (e) => {
      e.preventDefault();
      if (!tpl) return;

      const dni = (inpQuick.value || '').replace(/\D/g, '').slice(0, 8);
      inpQuick.value = dni;

      if (dni.length !== 8) {
        inpQuick.classList.add('border-rose-400');
        inpQuick.focus();
        return;
      }

      // (opcional) cierra sidebar en móvil para que no se vea feo
      if (window.innerWidth < 1024) closeRail();

      window.location.href = tpl.replace('__DNI__', dni);
    });
  }
})();