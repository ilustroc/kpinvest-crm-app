export function toSafeInternalUrl(rawUrl, fallback = '#') {
    if (!rawUrl || typeof rawUrl !== 'string') {
        return fallback;
    }

    try {
        const parsed = new URL(rawUrl, window.location.origin);
        if (
            parsed.origin === window.location.origin &&
            (parsed.protocol === 'http:' || parsed.protocol === 'https:')
        ) {
            return parsed.pathname + parsed.search + parsed.hash;
        }
    } catch (_) {
        return fallback;
    }

    return fallback;
}

export function checkAuthResponse(response) {
    if (!response) return true;

    if (
        response.status === 401 ||
        response.status === 419 ||
        (response.redirected && response.url && response.url.includes('/login'))
    ) {
        window.location.href = '/login?expired=1';
        return false;
    }

    return true;
}

export async function fetchText(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
    });

    if (!checkAuthResponse(response)) {
        throw new Error('Session expired');
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.text();
}

export async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
    });

    if (!checkAuthResponse(response)) {
        throw new Error('Session expired');
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}
