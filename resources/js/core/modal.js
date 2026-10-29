function focusableElements(modal) {
    return Array.from(modal.querySelectorAll([
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(','))).filter((element) => element.offsetParent !== null && element.getAttribute('aria-hidden') !== 'true');
}

function activeModal() {
    return document.querySelector('[data-modal]:not(.hidden)');
}

export function openModal(modal) {
    if (!modal) return;

    modal._returnFocus = document.activeElement;
    modal._restoreOverflow = document.body.style.overflow;

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    const firstInput = modal.querySelector('[autofocus]') || focusableElements(modal)[0];
    setTimeout(() => firstInput?.focus(), 50);
}

export function closeModal(modal) {
    if (!modal) return;

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');

    if (!activeModal()) {
        document.body.style.overflow = modal._restoreOverflow || '';
    }

    const returnFocus = modal._returnFocus;
    modal._returnFocus = null;

    if (returnFocus?.isConnected) {
        setTimeout(() => returnFocus.focus(), 50);
    }
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
        const modal = activeModal();
        if (!modal) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(modal);
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = focusableElements(modal);
        if (!focusable.length) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const current = document.activeElement;

        if (event.shiftKey && (current === first || !modal.contains(current))) {
            event.preventDefault();
            last.focus();
            return;
        }

        if (!event.shiftKey && (current === last || !modal.contains(current))) {
            event.preventDefault();
            first.focus();
        }
    });

    const autoOpen = root.querySelector('[data-modal-auto-open]');
    if (autoOpen) {
        const modal = document.querySelector(autoOpen.getAttribute('data-modal-auto-open'));
        if (modal) openModal(modal);
    }
}
