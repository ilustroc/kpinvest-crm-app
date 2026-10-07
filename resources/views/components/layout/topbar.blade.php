@php
    $userName = auth()->user()->name ?? 'Usuario';
    $userRole = auth()->user()->role ?? 'usuario';
    $initials = collect(preg_split('/\s+/', trim($userName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('') ?: 'U';
@endphp

<header class="sticky top-0 z-20 border-b border-kp-border bg-white/95 shadow-[0_1px_2px_rgba(15,23,42,0.03)] backdrop-blur">
    <div class="mx-auto flex min-h-16 w-full max-w-[1680px] flex-col gap-2 px-3 py-2.5 sm:px-4 lg:flex-row lg:items-center lg:justify-between lg:px-5 lg:py-0">
        <div class="flex min-w-0 items-center gap-3">
            <button type="button"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-kp-border bg-white text-kp-ink shadow-sm kp-focus lg:hidden"
                    data-toggle-rail
                    aria-label="Abrir navegación">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <nav class="min-w-0" aria-label="Breadcrumb">
                <ol class="flex min-w-0 items-center gap-2 text-xs">
                    <li>
                        <a href="{{ route('panel') }}" class="inline-flex items-center gap-1.5 font-medium text-kp-muted transition hover:text-kp-ink kp-focus">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                            </svg>
                            <span class="hidden sm:inline">Inicio</span>
                        </a>
                    </li>
                    <li class="text-slate-300" aria-hidden="true">/</li>
                    <li class="truncate font-semibold text-kp-ink" aria-current="page">{{ $slot }}</li>
                </ol>
            </nav>
        </div>

        @auth
            <div class="flex w-full min-w-0 items-center gap-2 lg:w-auto lg:justify-end">
                <form
                    class="relative min-w-0 flex-1 lg:w-[430px] lg:flex-none"
                    role="search"
                    action="{{ route('clientes.quick') }}"
                    method="GET"
                    autocomplete="off"
                    data-quick-form
                    data-suggest-url="{{ route('clientes.suggest') }}"
                >
                    <label for="layoutClientSearch" class="sr-only">Buscar cliente</label>
                    <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-kp-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                    </svg>
                    <input
                        id="layoutClientSearch"
                        name="q"
                        class="h-9 w-full rounded-md border border-kp-border bg-slate-50 py-2 pl-9 pr-20 text-sm text-kp-ink shadow-inner transition placeholder:text-slate-400 hover:border-slate-300 focus:border-kp-green focus:bg-white kp-focus"
                        placeholder="Buscar DNI, operación o cliente"
                        aria-label="Buscar por DNI, operación o nombre"
                        data-quick-input
                    >
                    <button type="submit" class="absolute right-1 top-1/2 inline-flex h-7 -translate-y-1/2 items-center rounded px-2.5 text-xs font-bold text-kp-green-dark transition hover:bg-kp-green-soft kp-focus">
                        Buscar
                    </button>

                    <div
                        class="kp-scrollbar absolute left-0 top-full z-50 mt-2 hidden max-h-80 w-full overflow-auto rounded-lg border border-kp-border bg-white p-1 shadow-xl shadow-slate-950/10"
                        data-quick-suggestions
                    ></div>
                </form>

                <x-layout.notifications-bell />

                <details class="group relative shrink-0">
                    <summary class="flex h-9 items-center gap-2 rounded-md border border-kp-border bg-white px-1.5 pr-2 text-left shadow-sm transition hover:bg-slate-50 kp-focus" aria-label="Abrir menú de usuario">
                        <span class="inline-flex size-7 items-center justify-center rounded-md bg-kp-sidebar text-[10px] font-bold text-white">{{ $initials }}</span>
                        <span class="hidden max-w-32 leading-tight xl:block">
                            <span class="block truncate text-xs font-semibold text-kp-ink">{{ $userName }}</span>
                            <span class="block text-[9px] font-bold uppercase tracking-wide text-kp-muted">{{ $userRole }}</span>
                        </span>
                        <svg class="hidden size-3.5 text-kp-muted transition group-open:rotate-180 sm:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </summary>

                    <div class="absolute right-0 top-full z-50 mt-2 w-60 overflow-hidden rounded-lg border border-kp-border bg-white shadow-xl shadow-slate-950/10">
                        <div class="border-b border-kp-border px-3 py-3">
                            <div class="truncate text-sm font-semibold text-kp-ink">{{ $userName }}</div>
                            <div class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-kp-muted">{{ $userRole }}</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="p-1.5">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50 kp-focus">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m-3-6 3 3m0 0-3 3m3-3H9" />
                                </svg>
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        @endauth
    </div>

    @if (session('quick_error'))
        <div class="mx-auto w-full max-w-[1680px] px-3 pb-2 sm:px-4 lg:px-5">
            <x-feedback.alert variant="warning">{{ session('quick_error') }}</x-feedback.alert>
        </div>
    @endif
</header>
