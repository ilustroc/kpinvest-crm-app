<div
    class="relative"
    data-module="notifications-bell"
    data-list-url="{{ route('notificaciones.index') }}"
    data-count-url="{{ route('notificaciones.unread-count') }}"
    data-mark-all-url="{{ route('notificaciones.leer-todas') }}"
    data-mark-url-template="{{ route('notificaciones.leer', '__ID__') }}"
    data-csrf="{{ csrf_token() }}"
    data-user-id="{{ auth()->id() }}"
>
    <button
        type="button"
        class="relative inline-flex size-10 items-center justify-center rounded-md border border-kp-border bg-white text-kp-ink shadow-sm kp-focus hover:bg-slate-50"
        aria-label="Abrir notificaciones"
        aria-expanded="false"
        data-notifications-toggle
    >
        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M15 17H9m10-1.5c-1.2-1.1-2-1.9-2-5.1A5 5 0 0 0 7 10.4c0 3.2-.8 4-2 5.1-.5.5-.2 1.5.6 1.5h12.8c.8 0 1.1-1 .6-1.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        <span
            class="absolute -right-1 -top-1 hidden min-w-5 rounded-full bg-kp-green px-1.5 py-0.5 text-center text-[11px] font-bold leading-none text-white shadow-sm"
            data-notifications-count
        ></span>
    </button>

    <section
        class="absolute right-0 top-full z-50 mt-2 hidden w-[min(92vw,380px)] overflow-hidden rounded-lg border border-kp-border bg-white shadow-xl shadow-slate-950/15"
        aria-label="Notificaciones"
        data-notifications-panel
    >
        <div class="flex items-center justify-between gap-3 border-b border-kp-border px-4 py-3">
            <div>
                <h2 class="text-sm font-bold text-kp-ink">Notificaciones</h2>
                <p class="text-xs text-kp-muted">Promesas y CNA pendientes o actualizadas.</p>
            </div>
            <button
                type="button"
                class="rounded-md border border-kp-border px-2.5 py-1.5 text-xs font-bold text-kp-muted hover:bg-slate-50 hover:text-kp-ink kp-focus"
                data-notifications-mark-all
            >
                Leer todas
            </button>
        </div>

        <div class="max-h-[420px] overflow-y-auto p-2" data-notifications-list></div>

        <div class="hidden px-5 py-8 text-center" data-notifications-empty>
            <div class="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-kp-green-soft text-kp-green-dark">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m5 13 4 4L19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <p class="text-sm font-bold text-kp-ink">Todo al dia</p>
            <p class="mt-1 text-xs text-kp-muted">No tienes notificaciones pendientes.</p>
        </div>
    </section>
</div>
