(function () {
  const form = document.getElementById('login-form');
  if (!form) return;

  const btn = document.getElementById('submitBtn');
  const spn = btn?.querySelector('.spinner-border');
  const txt = btn?.querySelector('.btn-text');

  form.addEventListener('submit', function (e) {
    if (!form.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    } else if (btn) {
      btn.disabled = true;
      spn?.classList.remove('d-none');
      if (txt) txt.textContent = 'Ingresando...';
    }
    form.classList.add('was-validated');
  });

  const pwd = document.getElementById('password');
  const tgl = document.getElementById('togglePwd');
  if (pwd && tgl) {
    tgl.addEventListener('click', () => {
      const show = pwd.type === 'password';
      pwd.type = show ? 'text' : 'password';
      tgl.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
      tgl.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
      pwd.focus();
    });
  }

  const caps = document.getElementById('caps');
  if (pwd && caps) {
    function setCaps(e) {
      const on = e.getModifierState && e.getModifierState('CapsLock');
      caps.classList.toggle('d-none', !on);
    }
    pwd.addEventListener('keyup', setCaps);
    pwd.addEventListener('keydown', setCaps);
  }
})();
