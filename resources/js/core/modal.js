export function openModal(modal) {
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    const firstInput = modal.querySelector('input, select, textarea, button');
    setTimeout(() => firstInput?.focus(), 50);
}

export function closeModal(modal) {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
}

export function setupModals(root = document) {
    root.addEventListener('click', (event) => {
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
