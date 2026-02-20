(() => {
  const boot = document.getElementById('adminBoot');
  if (!boot) return;

  const ME_ROLE = (boot.dataset.meRole || 'usuario').trim().toLowerCase();
  const ME_ID = boot.dataset.meId || '';
  const SUPERVISORES = safeJson(boot.dataset.supervisores, []);
  const PW_ACTION_TPL = boot.dataset.pwActionTemplate || '';

  // ---------- Helpers ----------
  function safeJson(s, fallback) {
    try { return JSON.parse(s || ''); } catch { return fallback; }
  }

  function qs(sel, root = document) { return root.querySelector(sel); }
  function qsa(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }

  // ---------- Confirm forms ----------
  qsa('form[data-confirm]').forEach(f => {
    f.addEventListener('submit', (e) => {
      const msg = f.getAttribute('data-confirm') || '¿Confirmas?';
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // ---------- Ctrl+K focus search ----------
  const search = qs('#adminSearch');
  if (search) {
    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        search.focus();
        search.select();
      }
    });
  }

  // ---------- Auto submit switch ----------
  const sw = qs('#swInactivos');
  if (sw) {
    sw.addEventListener('change', () => {
      const form = sw.closest('form');
      if (form) form.submit();
    });
  }

  // ---------- Eye toggle ----------
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-eye]');
    if (!btn) return;

    const wrap = btn.closest('.relative');
    const input = wrap ? wrap.querySelector('input') : null;
    if (!input) return;

    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';

    const openI = btn.querySelector('[data-eye-open]');
    const offI  = btn.querySelector('[data-eye-off]');
    if (openI && offI) {
      openI.classList.toggle('hidden', show);
      offI.classList.toggle('hidden', !show);
    }
  });

  // ---------- Modal system ----------
  const modals = {
    pw: qs('#modalPw'),
    createUser: qs('#modalCreateUser'),
  };

  function openModal(el) {
    if (!el) return;
    el.classList.remove('hidden');
    el.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('overflow-hidden');
  }

  function closeModal(el) {
    if (!el) return;
    el.classList.add('hidden');
    el.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('overflow-hidden');
  }

  // Close handlers (backdrop / X)
  qsa('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.kp-modal');
      closeModal(modal);
    });
  });

  // Esc
  window.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    Object.values(modals).forEach(m => m && !m.classList.contains('hidden') && closeModal(m));
  });

  // Open modal buttons
  qsa('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.getAttribute('data-modal-open');
      if (!key) return;

      if (key === 'pw') {
        openPasswordModal(btn);
        return;
      }

      if (key === 'createUser') {
        prepCreateUserModal();
        openModal(modals.createUser);
        setTimeout(() => qs('#createUserForm input[name="name"]')?.focus(), 30);
        return;
      }
    });
  });

  // ---------- Password modal (reusable) ----------
  const pwForm = qs('#pwForm');
  const pwUserName = qs('#pwUserName');
  const pw1 = qs('#pw1');
  const pw2 = qs('#pw2');

  function openPasswordModal(btn) {
    const id = btn.getAttribute('data-user-id');
    const name = btn.getAttribute('data-user-name') || '—';

    if (pwUserName) pwUserName.textContent = name;

    if (pwForm && PW_ACTION_TPL && id) {
      pwForm.action = PW_ACTION_TPL.replace('__ID__', id);
    }

    if (pw1) pw1.value = '';
    if (pw2) pw2.value = '';

    openModal(modals.pw);
    setTimeout(() => pw1?.focus(), 30);
  }

  // ---------- Create user: supervisor logic ----------
  const createForm = qs('#createUserForm');
  const roleSel = qs('#createRole');
  const supRow = qs('#createSupervisorRow');
  const supSel = qs('#createSupervisor');

  function ensureHiddenSupervisor(id) {
    if (!createForm) return;
    let hid = createForm.querySelector('input[type="hidden"][name="supervisor_id"]');
    if (!hid) {
      hid = document.createElement('input');
      hid.type = 'hidden';
      hid.name = 'supervisor_id';
      createForm.appendChild(hid);
    }
    hid.value = id || '';
  }

  function removeHiddenSupervisor() {
    if (!createForm) return;
    const hid = createForm.querySelector('input[type="hidden"][name="supervisor_id"]');
    if (hid) hid.remove();
  }

  function fillSupervisorOptions() {
    if (!supSel) return;
    supSel.innerHTML = '';

    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = 'Selecciona…';
    supSel.appendChild(opt0);

    SUPERVISORES.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.id;

      const label =
        (s.label && String(s.label).trim()) ||
        (s.name ? `${s.name}${s.email ? ` (${s.email})` : ''}` : '') ||
        (s.email ? s.email : `Supervisor #${s.id}`);

      opt.textContent = label;
      supSel.appendChild(opt);
    });
  }

  function updateSupervisorField() {
    if (!roleSel || !supRow || !supSel) return;
    const role = roleSel.value;
    const needs = (role === 'asesor' || role === 'soporte');

    if (!needs) {
      supRow.classList.add('hidden');
      supSel.required = false;
      supSel.value = '';
      removeHiddenSupervisor();
      return;
    }

    // Si yo soy supervisor, el supervisor_id es mi ID (oculto)
    if (ME_ROLE === 'supervisor') {
      supRow.classList.add('hidden');
      supSel.required = false;
      supSel.value = '';
      ensureHiddenSupervisor(ME_ID);
      return;
    }

    // Admin -> elegir supervisor
    removeHiddenSupervisor();
    fillSupervisorOptions();
    supRow.classList.remove('hidden');
    supSel.required = true;
  }

  function prepCreateUserModal() {
    if (!createForm) return;
    createForm.reset();
    removeHiddenSupervisor();
    if (supRow) supRow.classList.add('hidden');
    if (supSel) { supSel.required = false; supSel.value = ''; }
    if (roleSel) roleSel.value = '';
  }

  roleSel?.addEventListener('change', updateSupervisorField);

})();