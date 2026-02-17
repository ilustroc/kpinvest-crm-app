(function () {
  // Tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

  // Ojo password
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-eye');
    if (!btn) return;

    const input = btn.parentElement.querySelector('input[type="password"], input[type="text"]');
    if (!input) return;

    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  }, false);

  // Switch inactivos autosubmit
  (function () {
    const sw = document.getElementById('swInactivos');
    if (!sw) return;
    sw.addEventListener('change', () => {
      const form = sw.closest('form');
      if (form) form.submit();
    });
  })();

  // Ctrl+K focus búsqueda
  (function () {
    const input = document.querySelector('.filters input[name="q"]');
    if (!input) return;
    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        input.focus();
        input.select();
      }
    });
  })();

  // Modal crear usuario: supervisor según rol
  (function () {
    const ME_ROLE = window.ADMIN_ME_ROLE || 'usuario';
    const ME_ID = window.ADMIN_ME_ID ?? null;
    const SUPERVISORES = Array.isArray(window.ADMIN_SUPERVISORES) ? window.ADMIN_SUPERVISORES : [];

    const modalEl = document.getElementById('modalCreateUser');
    if (!modalEl) return;

    const modal = new bootstrap.Modal(modalEl);
    const form = modalEl.querySelector('form');
    const roleSel = modalEl.querySelector('select[name="role"]');
    if (!form || !roleSel) return;

    let supRow = modalEl.querySelector('[data-sup-row]');
    let supSel = modalEl.querySelector('select[name="supervisor_id"]');

    if (!supRow) {
      const ref = roleSel.closest('.row');
      supRow = document.createElement('div');
      supRow.className = 'row g-3 align-items-center';
      supRow.setAttribute('data-sup-row', '');
      supRow.innerHTML = `
        <div class="col-12 col-md-4 text-md-end">
          <label class="form-label mb-0">Supervisor</label>
        </div>
        <div class="col-12 col-md-8">
          <select name="supervisor_id" class="form-select"></select>
        </div>
      `;
      ref.after(supRow);
      supSel = supRow.querySelector('select[name="supervisor_id"]');
    }

    function fillSupervisorOptions() {
      supSel.innerHTML = '<option value="">Selecciona…</option>';
      SUPERVISORES.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.label;
        supSel.appendChild(opt);
      });
    }

    function ensureHiddenSup(id) {
      let hid = form.querySelector('input[type="hidden"][name="supervisor_id"]');
      if (!hid) {
        hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'supervisor_id';
        form.appendChild(hid);
      }
      hid.value = id ?? '';
      supSel.value = '';
      supSel.removeAttribute('required');
    }

    function removeHiddenSup() {
      const hid = form.querySelector('input[type="hidden"][name="supervisor_id"]');
      if (hid) hid.remove();
    }

    function updateSupervisorField() {
      const role = roleSel.value;
      const necesitaSup = (role === 'asesor' || role === 'soporte');

      if (!necesitaSup) {
        supRow.classList.add('d-none');
        supSel.removeAttribute('required');
        supSel.value = '';
        removeHiddenSup();
        return;
      }

      if (ME_ROLE === 'supervisor') {
        supRow.classList.add('d-none');
        ensureHiddenSup(ME_ID);
      } else {
        removeHiddenSup();
        fillSupervisorOptions();
        supRow.classList.remove('d-none');
        supSel.setAttribute('required', 'required');
      }
    }

    modalEl.addEventListener('show.bs.modal', () => {
      form.reset();
      removeHiddenSup();
      roleSel.value = '';
      supRow.classList.add('d-none');
      supSel.removeAttribute('required');

      const first = modalEl.querySelector('input[name="name"]');
      setTimeout(() => first?.focus(), 120);
    });

    roleSel.addEventListener('change', updateSupervisorField);

    window.openCreateUserModal = () => modal.show();
  })();
})();
