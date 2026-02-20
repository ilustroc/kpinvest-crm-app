document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('login-form');
    if (!form) return;

    const btn = document.getElementById('submitBtn');
    const spn = document.getElementById('spinner');
    const txt = document.getElementById('btn-text');

    form.addEventListener('submit', (e) => {
        if (!form.checkValidity()) {
            e.preventDefault();
        } else if (btn) {
            btn.disabled = true;
            spn?.classList.remove('hidden');
            if (txt) txt.textContent = 'Ingresando...';
        }
    });

    // Toggle Password con SVG dinámico
    const pwd = document.getElementById('password');
    const tgl = document.getElementById('togglePwd');
    if (pwd && tgl) {
        tgl.addEventListener('click', () => {
            const isPwd = pwd.type === 'password';
            pwd.type = isPwd ? 'text' : 'password';
            pwd.focus();
        });
    }

    // Detector Bloq Mayús
    const caps = document.getElementById('caps');
    if (pwd && caps) {
        const checkCaps = (e) => {
            const isOn = e.getModifierState && e.getModifierState('CapsLock');
            caps.classList.toggle('hidden', !isOn);
        };
        pwd.addEventListener('keyup', checkCaps);
        pwd.addEventListener('keydown', checkCaps);
    }
});