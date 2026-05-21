import { $, escapeHtml, ready } from '../../core/dom';
import { getEcho } from '../../core/echo';
import { notify } from '../../core/toast';

const LAST_COUNT_KEY = 'kp_notifications_unread_count';

function playNotificationSound() {
    const AudioContext = window.AudioContext || window.webkitAudioContext;

    if (!AudioContext) return;

    try {
        const context = new AudioContext();
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(880, context.currentTime);
        oscillator.frequency.exponentialRampToValueAtTime(660, context.currentTime + 0.18);

        gain.gain.setValueAtTime(0.0001, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.12, context.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.22);

        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + 0.24);
    } catch (error) {
        // Audio can be blocked by the browser until the user interacts with the page.
    }
}

function notificationIcon(module) {
    if (module === 'cna') {
        return '<path d="M4 7.5 12 13l8-5.5M5 6h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>';
    }

    return '<path d="M8 7h8M8 11h8M8 15h5M6 3h9l3 3v15H6V3Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>';
}

function setupNotifications(root) {
    const toggle = $('[data-notifications-toggle]', root);
    const panel = $('[data-notifications-panel]', root);
    const list = $('[data-notifications-list]', root);
    const empty = $('[data-notifications-empty]', root);
    const badge = $('[data-notifications-count]', root);
    const markAllButton = $('[data-notifications-mark-all]', root);

    if (!toggle || !panel || !list || !empty || !badge || root.dataset.inited === '1') return;

    root.dataset.inited = '1';

    const csrf = root.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
    let currentItems = [];

    const request = (url, options = {}) => fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.method && options.method !== 'GET' ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        ...options,
    }).then((response) => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    });

    const updateBadge = (count, allowSound = false) => {
        const unread = Number(count || 0);
        const previous = Number(localStorage.getItem(LAST_COUNT_KEY) || 0);

        if (unread > 0) {
            badge.textContent = unread > 9 ? '9+' : String(unread);
            badge.classList.remove('hidden');
        } else {
            badge.textContent = '';
            badge.classList.add('hidden');
        }

        if (allowSound && unread > previous) {
            playNotificationSound();
        }

        localStorage.setItem(LAST_COUNT_KEY, String(unread));
    };

    const currentBadgeCount = () => {
        if (badge.classList.contains('hidden')) return 0;
        if (badge.textContent === '9+') return 9;

        return Number(badge.textContent || 0);
    };

    const markAsRead = (id) => {
        const template = root.dataset.markUrlTemplate;
        if (!template || !id) return Promise.resolve();

        return request(template.replace('__ID__', encodeURIComponent(id)), { method: 'POST' })
            .then((data) => updateBadge(data.unread_count));
    };

    const normalizeRealtimeNotification = (notification = {}) => ({
        id: notification.id || notification.notification_id || '',
        type: notification.type || '',
        module: notification.module || '',
        entity_id: notification.entity_id || null,
        dni: notification.dni || '',
        cliente: notification.cliente || '',
        estado: notification.estado || '',
        title: notification.title || 'Nueva notificacion',
        message: notification.message || '',
        action_label: notification.action_label || 'Abrir',
        action_url: notification.action_url || '',
        created_by: notification.created_by || null,
        created_by_name: notification.created_by_name || null,
        read_at: null,
        created_at: new Date().toISOString(),
        created_at_label: 'Ahora',
        is_read: false,
    });

    const render = (items = []) => {
        currentItems = items;

        if (!items.length) {
            list.innerHTML = '';
            empty.classList.remove('hidden');
            return;
        }

        empty.classList.add('hidden');
        list.innerHTML = items.map((item) => `
            <article class="rounded-lg border border-kp-border bg-white p-3 ${item.is_read ? 'opacity-75' : 'shadow-sm'}">
                <div class="flex gap-3">
                    <div class="mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-full ${item.is_read ? 'bg-slate-100 text-kp-muted' : 'bg-kp-green-soft text-kp-green-dark'}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">${notificationIcon(item.module)}</svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-bold text-kp-ink">${escapeHtml(item.title)}</h3>
                            <span class="shrink-0 text-[11px] text-kp-muted">${escapeHtml(item.created_at_label || '')}</span>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-kp-muted">${escapeHtml(item.message)}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-kp-muted">
                            <span>${escapeHtml(item.module || '-')}</span>
                            <span>DNI ${escapeHtml(item.dni || '-')}</span>
                            <span>${escapeHtml(item.estado || '-')}</span>
                        </div>
                        ${item.action_url ? `
                            <button
                                type="button"
                                class="mt-3 inline-flex rounded-md border border-kp-border px-3 py-1.5 text-xs font-bold text-kp-green-dark hover:bg-kp-green-soft kp-focus"
                                data-notification-open
                                data-id="${escapeHtml(item.id)}"
                                data-url="${escapeHtml(item.action_url)}"
                            >
                                ${escapeHtml(item.action_label || 'Abrir')}
                            </button>
                        ` : ''}
                    </div>
                </div>
            </article>
        `).join('<div class="h-2"></div>');

        list.querySelectorAll('[data-notification-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const url = button.dataset.url;
                markAsRead(button.dataset.id).finally(() => {
                    if (url) window.location.href = url;
                });
            });
        });
    };

    const loadList = () => request(root.dataset.listUrl)
        .then((data) => {
            updateBadge(data.unread_count, true);
            render(data.notifications || []);
        })
        .catch(() => {
            list.innerHTML = '<div class="rounded-md bg-red-50 px-3 py-2 text-sm font-bold text-red-700">No se pudieron cargar las notificaciones.</div>';
            empty.classList.add('hidden');
        });

    const loadCount = (allowSound = false) => {
        if (!root.dataset.countUrl) return;
        request(root.dataset.countUrl)
            .then((data) => updateBadge(data.unread_count, allowSound))
            .catch(() => {});
    };

    const prependRealtimeNotification = (notification) => {
        const item = normalizeRealtimeNotification(notification);

        if (item.id) {
            currentItems = currentItems.filter((current) => current.id !== item.id);
        }

        currentItems = [item, ...currentItems].slice(0, 15);

        if (!panel.classList.contains('hidden')) {
            render(currentItems);
        }
    };

    const subscribeRealtime = () => {
        const echo = getEcho();
        const userId = root.dataset.userId;

        if (!echo || !userId) {
            return;
        }

        try {
            echo.private(`App.Models.User.${userId}`)
                .notification((notification) => {
                    prependRealtimeNotification(notification);
                    updateBadge(currentBadgeCount() + 1, false);
                    notify(notification.title || 'Nueva notificacion', 'info');
                    playNotificationSound();
                    loadCount(false);
                });
        } catch (error) {
            // Reverb is optional here; fetch remains the safe fallback.
        }
    };

    const openPanel = () => {
        panel.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        loadList();
    };

    const closePanel = () => {
        panel.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        if (panel.classList.contains('hidden')) {
            openPanel();
        } else {
            closePanel();
        }
    });

    markAllButton?.addEventListener('click', () => {
        request(root.dataset.markAllUrl, { method: 'POST' })
            .then((data) => {
                updateBadge(data.unread_count);
                return loadList();
            })
            .catch(() => {});
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) closePanel();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePanel();
    });

    loadCount(true);
    subscribeRealtime();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) loadCount(true);
    });
}

ready(() => {
    document.querySelectorAll('[data-module="notifications-bell"]').forEach(setupNotifications);
});
