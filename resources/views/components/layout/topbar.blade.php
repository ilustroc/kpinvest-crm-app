<header class="sticky top-0 z-20 border-b border-kp-border bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-[1220px] flex-col gap-3 px-4 py-3 sm:px-5 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex min-w-0 items-center gap-3">
            <button type="button"
                    class="inline-flex size-10 items-center justify-center rounded-md border border-kp-border bg-white text-kp-ink shadow-sm kp-focus lg:hidden"
                    data-toggle-rail
                    aria-label="Abrir menu">
                <span class="text-xs font-bold uppercase leading-none">Menu</span>
            </button>
            <div class="min-w-0 text-base font-bold text-kp-ink">
                {{ $slot }}
            </div>
        </div>

        @auth
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-start sm:justify-end xl:max-w-3xl">
                <form
                    class="relative flex w-full flex-col gap-2 sm:flex-row xl:max-w-xl"
                    role="search"
                    action="{{ route('clientes.quick') }}"
                    method="GET"
                    autocomplete="off"
                    data-quick-form
                    data-suggest-url="{{ route('clientes.suggest') }}"
                >
                    <label for="layoutClientSearch" class="sr-only">Buscar cliente</label>
                    <input
                        id="layoutClientSearch"
                        name="q"
                        class="w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus placeholder:text-kp-muted"
                        placeholder="DNI / Operacion / Nombre"
                        aria-label="Buscar por DNI, Operacion o Nombre"
                        data-quick-input
                    >
                    <x-ui.button type="submit" class="sm:w-auto">
                        Buscar
                    </x-ui.button>

                    <div
                        class="absolute left-0 top-full z-50 mt-2 hidden max-h-80 w-full overflow-auto rounded-lg border border-kp-border bg-white p-1 shadow-xl shadow-slate-950/10"
                        data-quick-suggestions
                    ></div>
                </form>

                <x-layout.notifications-bell />
            </div>
        @endauth
    </div>

    @if (session('quick_error'))
        <div class="mx-auto max-w-[1220px] px-4 pb-3 sm:px-5">
            <x-feedback.alert variant="warning">
                {{ session('quick_error') }}
            </x-feedback.alert>
        </div>
    @endif
</header>
