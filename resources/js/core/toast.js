import { $$, escapeHtml, ready } from './dom';

const variants = {
    success: 'border-l-emerald-500',
    danger: 'border-l-red-500',
    warning: 'border-l-amber-500',
    info: 'border-l-sky-500',
};

function closeToast(toast) {
    if (!toast) return;

    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-10px)';
    setTimeout(() => toast.remove(), 300);
}

function logoUrl() {
    return document.querySelector('link[rel="icon"]')?.href || '/assets/img/logo-superior.png';
}

function setupRenderedToast(toast) {
    if (!toast || toast.dataset.toastReady === '1') return;

    toast.dataset.toastReady = '1';

    toast.querySelector('[data-toast-close]')?.addEventListener('click', () => closeToast(toast));

    const timeout = Number.parseInt(toast.dataset.toastTimeout || '0', 10);

    if (timeout > 0) {
        setTimeout(() => closeToast(toast), timeout);
    }
}

export function notify(message, variant = 'info') {
    window.dispatchEvent(new CustomEvent('kp:notify', {
        detail: { message, variant },
    }));
}

function renderToast(message, variant = 'info', timeout = 5000) {
    const toast = document.createElement('div');

    toast.setAttribute('role', 'alert');
    toast.setAttribute('data-toast', '');
    toast.setAttribute('data-toast-timeout', String(timeout));
    toast.className = `fixed right-4 top-4 z-[100] flex w-full max-w-sm items-center gap-3.5 border border-kp-border border-l-4 bg-white p-4 transition-all duration-300 ${variants[variant] || variants.info}`;
    toast.innerHTML = `
        <img src="${escapeHtml(logoUrl())}" alt="KP Invest" class="h-7 w-auto shrink-0">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold leading-snug text-kp-ink">${escapeHtml(message)}</p>
        </div>
        <button type="button" data-toast-close class="shrink-0 rounded-md p-1 text-kp-muted transition-colors hover:bg-slate-100 hover:text-kp-ink focus:outline-none">
            <span class="sr-only">Cerrar</span>
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    `;

    document.body.appendChild(toast);
    setupRenderedToast(toast);
}

ready(() => {
    $$('[data-toast]').forEach(setupRenderedToast);

    window.addEventListener('kp:notify', (event) => {
        renderToast(event.detail?.message || '', event.detail?.variant || 'info');
    });
});
