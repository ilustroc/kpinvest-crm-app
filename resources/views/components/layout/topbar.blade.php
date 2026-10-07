@php
    $userName = auth()->user()->name ?? 'Usuario';
    $userRole = auth()->user()->role ?? 'usuario';
    $initials = collect(preg_split('/\s+/', trim($userName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('') ?: 'U';
@endphp

<header class="sticky top-0 z-30 border-b border-kp-border bg-white">
    <div class="bg-kp-navy text-white">
        <div class="mx-auto flex min-h-12 w-full max-w-[1720px] flex-wrap items-center gap-2 px-2 sm:px-3 lg:flex-nowrap lg:px-4">
            <div class="flex h-12 shrink-0 items-center gap-2">
                <button type="button"
                        class="inline-flex size-8 items-center justify-center rounded-sm text-white/85 transition hover:bg-white/10 hover:text-white kp-focus md:hidden"
                        data-toggle-rail
                        aria-label="Abrir navegación">
                    <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h3v3H4V5Zm6.5 0h3v3h-3V5ZM17 5h3v3h-3V5ZM4 11h3v3H4v-3Zm6.5 0h3v3h-3v-3Zm6.5 0h3v3h-3v-3ZM4 17h3v3H4v-3Zm6.5 0h3v3h-3v-3Zm6.5 0h3v3h-3v-3Z" />
                    </svg>
                </button>

                <a href="{{ route('panel') }}" class="hidden size-8 items-center justify-center rounded-sm text-white/85 transition hover:bg-white/10 hover:text-white kp-focus md:inline-flex" aria-label="Abrir inicio de KP INVEST">
                    <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h3v3H4V5Zm6.5 0h3v3h-3V5ZM17 5h3v3h-3V5ZM4 11h3v3H4v-3Zm6.5 0h3v3h-3v-3Zm6.5 0h3v3h-3v-3ZM4 17h3v3H4v-3Zm6.5 0h3v3h-3v-3Zm6.5 0h3v3h-3v-3Z" />
                    </svg>
                </a>

                <div class="flex items-baseline gap-2 border-l border-white/20 pl-3">
                    <span class="text-[13px] font-bold tracking-[0.04em]">KP INVEST</span>
                    <span class="hidden text-[10px] font-medium text-blue-100 sm:inline">CRM de Cobranzas</span>
                </div>
            </div>

            @auth
                <form
                    class="relative order-3 mb-2 w-full min-w-0 sm:order-none sm:mb-0 sm:ml-auto sm:w-[360px] lg:mx-auto lg:w-full lg:max-w-[520px]"
                    role="search"
                    action="{{ route('clientes.quick') }}"
                    method="GET"
                    autocomplete="off"
                    data-quick-form
                    data-suggest-url="{{ route('clientes.suggest') }}"
                >
                    <label for="layoutClientSearch" class="sr-only">Buscar cliente</label>
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                    </svg>
                    <input
                        id="layoutClientSearch"
                        name="q"
                        class="h-8 w-full rounded-sm border border-white/25 bg-white py-1.5 pl-8 pr-9 text-xs text-kp-ink transition placeholder:text-slate-500 focus:border-white kp-focus"
                        placeholder="Buscar por DNI, operación o nombre"
                        aria-label="Buscar por DNI, operación o nombre"
                        data-quick-input
                    >
                    <button type="submit" class="absolute right-0 top-0 inline-flex size-8 items-center justify-center rounded-r-sm text-kp-blue transition hover:bg-kp-blue-soft kp-focus" aria-label="Ejecutar búsqueda">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                        </svg>
                    </button>

                    <div class="kp-scrollbar absolute left-0 top-full z-50 mt-1 hidden max-h-80 w-full overflow-auto rounded-sm border border-kp-border bg-white p-1 text-kp-ink shadow-lg" data-quick-suggestions></div>
                </form>

                <div class="ml-auto flex h-12 shrink-0 items-center gap-0.5 sm:ml-0">
                    <x-layout.notifications-bell />

                    <details class="group relative shrink-0">
                        <summary class="flex h-8 items-center gap-1.5 rounded-sm px-1.5 text-left text-white/90 transition hover:bg-white/10 hover:text-white kp-focus" aria-label="Abrir menú de usuario">
                            <span class="hidden max-w-28 truncate text-[11px] font-semibold xl:block">{{ $userName }}</span>
                            <svg class="hidden size-3 text-blue-100 transition group-open:rotate-180 sm:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </summary>

                        <div class="absolute right-0 top-full z-50 mt-1 w-56 overflow-hidden rounded-sm border border-kp-border bg-white text-kp-ink shadow-lg">
                            <div class="border-b border-kp-border bg-slate-50 px-3 py-2.5">
                                <div class="truncate text-xs font-semibold">{{ $userName }}</div>
                                <div class="mt-0.5 text-[9px] font-bold uppercase tracking-wider text-kp-muted">{{ $userRole }}</div>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="p-1">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 rounded-sm px-2.5 py-2 text-[11px] font-semibold text-red-700 transition hover:bg-red-50 kp-focus">
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
    </div>

    <div class="mx-auto flex h-8 w-full max-w-[1720px] items-center px-3 lg:px-4">
        <nav class="min-w-0" aria-label="Breadcrumb">
            <ol class="flex min-w-0 items-center gap-1.5 text-[10px]">
                <li><a href="{{ route('panel') }}" class="font-medium text-kp-blue hover:underline kp-focus">Inicio</a></li>
                <li class="text-slate-400" aria-hidden="true">›</li>
                <li class="truncate font-semibold text-kp-ink" aria-current="page">{{ $slot }}</li>
            </ol>
        </nav>
    </div>

    @if (session('quick_error'))
        <div class="mx-auto w-full max-w-[1720px] px-3 pb-2 lg:px-4">
            <x-feedback.alert variant="warning">{{ session('quick_error') }}</x-feedback.alert>
        </div>
    @endif
</header>
