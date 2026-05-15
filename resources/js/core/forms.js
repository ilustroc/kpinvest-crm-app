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
