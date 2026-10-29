const rail = document.getElementById('rail');
const backdrop = document.getElementById('backdrop');
const mobileToggle = document.querySelector('button[data-toggle-rail]');
const collapseButtons = [...document.querySelectorAll('[data-sidebar-collapse]')];
const collapseKey = 'kp.meridian.sidebar.collapsed';

function setCollapsed(collapsed) {
    if (!rail || !collapseButtons.length) return;

    rail.classList.toggle('is-collapsed', collapsed);

    collapseButtons.forEach((button) => {
        button.setAttribute('aria-expanded', String(!collapsed));
        button.setAttribute('aria-label', collapsed ? 'Expandir menú lateral' : 'Colapsar menú lateral');
    });

    try {
        window.localStorage.setItem(collapseKey, collapsed ? '1' : '0');
    } catch (error) {
        // El estado visual sigue funcionando aunque localStorage esté bloqueado.
    }
}

function toggleMobileRail(forceOpen = null) {
    if (!rail || !backdrop) return;

    const isOpen = !rail.classList.contains('-translate-x-full');
    const shouldOpen = forceOpen === null ? !isOpen : forceOpen;

    rail.classList.toggle('-translate-x-full', !shouldOpen);
    backdrop.classList.toggle('hidden', !shouldOpen);
    backdrop.setAttribute('aria-hidden', String(!shouldOpen));
    mobileToggle?.setAttribute('aria-expanded', String(shouldOpen));
}

if (collapseButtons.length && rail) {
    const saved = (() => {
        try {
            return window.localStorage.getItem(collapseKey);
        } catch (error) {
            return null;
        }
    })();

    setCollapsed(saved === '1');

    collapseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setCollapsed(!rail.classList.contains('is-collapsed'));
        });
    });
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle-rail]');
    if (toggle) {
        event.preventDefault();
        toggleMobileRail();
        return;
    }

    if (event.target.closest('#rail a')) {
        toggleMobileRail(false);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && backdrop && !backdrop.classList.contains('hidden')) {
        toggleMobileRail(false);
        mobileToggle?.focus();
    }
});
