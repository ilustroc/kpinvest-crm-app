function openModal(modal) {
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    const firstInput = modal.querySelector('input, select, textarea, button');
    setTimeout(() => firstInput?.focus(), 50);
}

function closeModal(modal) {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
}

function setupModals() {
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-modal-open]');
        if (opener) {
            const modal = document.querySelector(opener.getAttribute('data-modal-open'));
            if (modal) openModal(modal);
            return;
        }

        const closer = event.target.closest('[data-modal-close]');
        if (closer) {
            const modal = closer.closest('[data-modal]');
            if (modal) closeModal(modal);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('[data-modal]:not(.hidden)').forEach(closeModal);
    });
}

function setupPasswordToggles() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-password-toggle]');
        if (!button) return;

        const wrapper = button.closest('[data-password-field]');
        const input = wrapper?.querySelector('input[type="password"], input[type="text"]');
        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Ocultar' : 'Ver';
    });
}

function setupInactiveSwitch() {
    const sw = document.getElementById('swInactivos');
    if (!sw) return;

    sw.addEventListener('change', () => {
        sw.closest('form')?.submit();
    });
}

function setupSearchShortcut() {
    const input = document.querySelector('[data-admin-search]');
    if (!input) return;

    window.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            input.focus();
            input.select();
        }
    });
}

function setupSupervisorField() {
    const form = document.querySelector('[data-create-user-form]');
    if (!form) return;

    const role = form.querySelector('select[name="role"]');
    const row = form.querySelector('[data-supervisor-row]');
    const select = form.querySelector('select[name="supervisor_id"]');
    const hidden = form.querySelector('input[type="hidden"][name="supervisor_id"]');
    const currentRole = form.getAttribute('data-current-role');

    if (!role || !row || (!select && !hidden)) return;

    function refresh() {
        const needsSupervisor = ['asesor', 'soporte'].includes(role.value);

        if (!needsSupervisor) {
            row.classList.add('hidden');
            select?.removeAttribute('required');
            if (select) select.value = '';
            if (hidden) hidden.value = '';
            return;
        }

        if (currentRole === 'supervisor') {
            row.classList.add('hidden');
            select?.removeAttribute('required');
            return;
        }

        row.classList.remove('hidden');
        select?.setAttribute('required', 'required');
    }

    role.addEventListener('change', refresh);
    refresh();
}

function initAdminUsers() {
    if (!document.querySelector('[data-admin-users-page]')) return;

    setupModals();
    setupPasswordToggles();
    setupInactiveSwitch();
    setupSearchShortcut();
    setupSupervisorField();
}

document.addEventListener('DOMContentLoaded', initAdminUsers);
