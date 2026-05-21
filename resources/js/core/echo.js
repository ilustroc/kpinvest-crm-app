import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo = null;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function createEcho() {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const port = import.meta.env.VITE_REVERB_PORT;
    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

    if (!key || !host || !port) {
        return null;
    }

    try {
        window.Pusher = Pusher;

        return new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: host,
            wsPort: Number(port),
            wssPort: Number(port),
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        });
    } catch (error) {
        return null;
    }
}

export function getEcho() {
    if (echo !== null) {
        return echo;
    }

    echo = createEcho();
    window.Echo = echo;

    return echo;
}
