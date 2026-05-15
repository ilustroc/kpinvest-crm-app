import { $$ } from './dom';

export function closeDropdown(dropdown) {
    dropdown?.querySelector('[data-dropdown-panel]')?.classList.add('hidden');
    dropdown?.querySelector('[data-dropdown-button]')?.setAttribute('aria-expanded', 'false');
}

export function closeDropdowns(except = null) {
    $$('[data-dropdown]').forEach((dropdown) => {
        if (dropdown !== except) closeDropdown(dropdown);
    });
}

export function setupDropdowns(root = document) {
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-dropdown-button]');

        if (button) {
            const dropdown = button.closest('[data-dropdown]');
            const panel = dropdown?.querySelector('[data-dropdown-panel]');
            if (!dropdown || !panel) return;

            event.preventDefault();
            const isHidden = panel.classList.contains('hidden');
            closeDropdowns(dropdown);
            panel.classList.toggle('hidden', !isHidden);
            button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            return;
        }

        if (!event.target.closest('[data-dropdown]')) {
            closeDropdowns();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeDropdowns();
    });
}
