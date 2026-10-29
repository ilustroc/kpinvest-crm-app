import { ready } from '../../core/dom';

ready(() => {
    const menus = [...document.querySelectorAll('details.kp-user-menu')];

    if (!menus.length) {
        return;
    }

    document.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            if (menu.open && !menu.contains(event.target)) {
                menu.open = false;
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        menus.forEach((menu) => {
            if (!menu.open) {
                return;
            }

            menu.open = false;
            menu.querySelector('summary')?.focus();
        });
    });
});
