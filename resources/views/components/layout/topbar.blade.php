<header class="sticky top-0 z-20 border-b border-kp-border bg-white/95 backdrop-blur">
    <div class="mx-auto flex min-h-16 max-w-[1440px] flex-wrap items-center gap-3 px-4 py-3 sm:px-6 md:flex-nowrap md:py-0 lg:px-8">
        <div class="flex min-w-0 items-center gap-2.5">
            <button type="button"
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-kp-border bg-white text-slate-600 transition hover:bg-slate-50 kp-focus lg:hidden"
                    data-toggle-rail
                    aria-label="Abrir menú"
                    aria-controls="rail"
                    aria-expanded="false">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>

            <button type="button"
                    class="hidden size-9 items-center justify-center rounded-lg border border-kp-border bg-white text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700 kp-focus lg:inline-flex"
                    data-sidebar-collapse
                    aria-label="Colapsar menú lateral"
                    aria-controls="rail"
                    aria-expanded="true">
                <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <rect x="3.25" y="4.25" width="17.5" height="15.5" rx="2.25" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15" />
                </svg>
            </button>

            <nav aria-label="Breadcrumb" class="min-w-0">
                <ol class="flex items-center gap-1.5 text-xs">
                    <li>
                        <a href="{{ route('panel') }}" class="inline-flex items-center gap-1 text-slate-400 transition hover:text-kp-secondary kp-focus" aria-label="Inicio">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3 10.5 9-7.5 9 7.5M5.25 9v10.5h13.5V9" />
                            </svg>
                            <span class="hidden sm:inline">Inicio</span>
                        </a>
                    </li>
                    <li class="text-slate-300" aria-hidden="true">/</li>
                    <li class="min-w-0 truncate font-medium text-slate-600" aria-current="page">
                        {{ trim($slot) !== '' ? $slot : 'KP Invest' }}
                    </li>
                </ol>
            </nav>
        </div>

        @auth
            <form
                class="relative order-last flex w-full items-center gap-2 md:order-none md:ml-auto md:w-auto md:max-w-[430px] md:flex-1"
                role="search"
                action="{{ route('clientes.quick') }}"
                method="GET"
                autocomplete="off"
                data-quick-form
                data-suggest-url="{{ route('clientes.suggest') }}"
            >
                <label for="layoutClientSearch" class="sr-only">Buscar cliente</label>
                <div class="relative min-w-0 flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" />
                    </svg>
                    <input
                        id="layoutClientSearch"
                        name="q"
                        class="h-9 w-full rounded-lg border border-kp-border bg-white pl-9 pr-3 text-[13px] text-kp-ink shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition placeholder:text-slate-400 hover:border-slate-300 kp-focus"
                        placeholder="Buscar DNI, operación o nombre"
                        aria-label="Buscar por DNI, operación o nombre"
                        data-quick-input
                    >
                </div>
                <x-ui.button type="submit" size="sm" class="shrink-0">Buscar</x-ui.button>

                <div
                    class="absolute left-0 top-full z-50 mt-2 hidden max-h-80 w-full overflow-auto rounded-xl border border-kp-border bg-white p-1 shadow-xl shadow-slate-950/10 kp-scrollbar"
                    data-quick-suggestions
                ></div>
            </form>

            <div class="ml-auto flex items-center gap-2 md:ml-0">
                <x-layout.notifications-bell />

                <details class="kp-user-menu relative">
                    <summary class="flex list-none cursor-pointer items-center gap-2 rounded-lg px-1.5 py-1 transition hover:bg-slate-50 kp-focus [&::-webkit-details-marker]:hidden">
                        <span class="min-w-0 text-left">
                            <span class="block max-w-24 truncate text-[12px] font-semibold text-kp-ink sm:max-w-32">{{ auth()->user()->name ?? 'Usuario' }}</span>
                            <span class="block text-[10px] uppercase tracking-[0.05em] text-slate-400">{{ auth()->user()->role ?? 'usuario' }}</span>
                        </span>
                        <svg class="size-3.5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </summary>

                    <div class="absolute right-0 top-full z-50 mt-2 w-60 overflow-hidden rounded-xl border border-kp-border bg-white shadow-xl shadow-slate-950/10">
                        <div class="border-b border-kp-border px-4 py-3">
                            <p class="truncate text-[13px] font-semibold text-kp-ink">{{ auth()->user()->name ?? 'Usuario' }}</p>
                            <p class="mt-0.5 text-[10px] uppercase tracking-[0.05em] text-slate-400">{{ auth()->user()->role ?? 'usuario' }}</p>
                        </div>
                        <div class="p-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[12px] font-medium text-slate-600 transition hover:bg-slate-50 hover:text-kp-ink kp-focus">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.25" />
                                    </svg>
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
        @endauth
    </div>

    @if (session('quick_error'))
        <div class="mx-auto max-w-[1440px] px-4 pb-3 sm:px-6 lg:px-8">
            <x-feedback.alert variant="warning">
                {{ session('quick_error') }}
            </x-feedback.alert>
        </div>
    @endif
</header>
