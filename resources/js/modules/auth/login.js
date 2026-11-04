import { $, $$, ready } from '../../core/dom';

function setFieldState(field, valid) {
    field.setAttribute('aria-invalid', valid ? 'false' : 'true');

    const message = document.querySelector(`[data-error-for="${field.name}"]`);

    if (message) {
        message.classList.toggle('hidden', valid);
    }
}

function validateField(field) {
    const valid = field.checkValidity();

    setFieldState(field, valid);

    return valid;
}

function setupLogin(root) {
    const form = $('[data-login-form]', root);

    if (!form || form.dataset.inited === '1') return;

    form.dataset.inited = '1';

    const button = $('#submitBtn', root);
    const spinner = $('[data-login-spinner]', root);
    const buttonText = button?.querySelector('.btn-text');
    const password = $('#password', root);
    const togglePassword = $('#togglePwd', root);
    const caps = $('#caps', root);

    $$('input[required]', form).forEach((field) => {
        field.addEventListener('input', () => validateField(field));
        field.addEventListener('blur', () => validateField(field));
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            if (button) {
                button.disabled = false;
            }
            spinner?.classList.add('hidden');
            if (buttonText) {
                buttonText.textContent = 'Iniciar sesión';
            }
        }
    });

    form.addEventListener('submit', (event) => {
        const fields = $$('input[required]', form);
        const valid = fields.every((field) => validateField(field));

        if (!valid) {
            event.preventDefault();
            event.stopPropagation();
            fields.find((field) => !field.checkValidity())?.focus();
            return;
        }

        if (button) {
            button.disabled = true;
        }

        spinner?.classList.remove('hidden');

        if (buttonText) {
            buttonText.textContent = 'Ingresando...';
        }
    });

    togglePassword?.addEventListener('click', () => {
        if (!password) return;

        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';

        const eyeIcon = togglePassword.querySelector('.eye-icon');
        const eyeOffIcon = togglePassword.querySelector('.eye-off-icon');

        if (eyeIcon && eyeOffIcon) {
            eyeIcon.classList.toggle('hidden', show);
            eyeOffIcon.classList.toggle('hidden', !show);
        } else {
            const showLabel = togglePassword.dataset.showLabel || 'Mostrar';
            const hideLabel = togglePassword.dataset.hideLabel || 'Ocultar';
            togglePassword.textContent = show ? hideLabel : showLabel;
        }

        togglePassword.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        togglePassword.setAttribute('title', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        password.focus();
    });

    if (password && caps) {
        const setCaps = (event) => {
            const enabled = event.getModifierState && event.getModifierState('CapsLock');
            caps.classList.toggle('hidden', !enabled);
        };

        password.addEventListener('keyup', setCaps);
        password.addEventListener('keydown', setCaps);
    }
}

ready(() => {
    const root = $('[data-module="auth-login"]');

    if (root) {
        setupLogin(root);
    }
});
