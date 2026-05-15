<header class="sticky top-0 z-20 border-b border-kp-border bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-[1220px] items-center gap-3 px-4 py-3 sm:px-5">
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
</header>
