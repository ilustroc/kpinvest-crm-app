export function formQuery(form, extra = {}) {
    const params = new URLSearchParams();
    const formData = new FormData(form);

    for (const [key, value] of formData.entries()) {
        if (value !== null && value !== '') {
            params.append(key, value);
        }
    }

    Object.entries(extra).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, value);
        }
    });

    return params.toString();
}

export function resetToDefaults(form) {
    form.querySelectorAll('[data-default]').forEach((field) => {
        field.value = field.getAttribute('data-default') || '';
    });

    form.querySelectorAll('input[type="checkbox"]').forEach((field) => {
        field.checked = false;
    });

    form.querySelectorAll('input[type="search"], input[name="q"]').forEach((field) => {
        field.value = '';
    });
}

export function initSubmitOnce(root = document) {
    root.querySelectorAll('form[data-once]').forEach((form) => {
        if (form.dataset.submitOnceInited === '1') return;
        form.dataset.submitOnceInited = '1';

        let locked = false;

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;

            if (locked) {
                event.preventDefault();
                return;
            }

            if (!form.checkValidity()) return;

            locked = true;
            form.setAttribute('aria-busy', 'true');

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                button.disabled = true;
                if (button.tagName === 'BUTTON') {
                    button.dataset.prev = button.textContent || '';
                    const loadingText = form.getAttribute('data-loading-text') || button.getAttribute('data-loading-text') || 'Procesando...';
                    button.textContent = loadingText;
                }
            });
        }, { capture: true });

        const unlock = () => {
            locked = false;
            form.removeAttribute('aria-busy');
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                button.disabled = false;
                if (button.tagName === 'BUTTON' && button.dataset.prev) {
                    button.textContent = button.dataset.prev;
                    delete button.dataset.prev;
                }
            });
        };

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                unlock();
            }
        });
    });
}
