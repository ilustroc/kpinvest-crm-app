@php
    $isReportes = request()->routeIs('reportes.*');

    $navLink = function (bool $active) {
        return 'group relative flex h-9 items-center gap-3 rounded-sm border-l-2 px-3 text-xs font-medium transition kp-focus md:justify-center md:px-0 '
            . ($active
                ? 'border-kp-blue bg-kp-blue-soft text-kp-blue'
                : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-kp-navy');
    };

    $subLink = function (bool $active) {
        return 'block rounded-sm px-2.5 py-2 text-[11px] font-medium transition kp-focus '
            . ($active
                ? 'bg-kp-blue-soft text-kp-blue'
                : 'text-slate-700 hover:bg-slate-100 hover:text-kp-navy');
    };
@endphp

<aside id="rail"
       class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col border-r border-kp-border bg-kp-sidebar shadow-lg transition-transform md:sticky md:top-0 md:h-screen md:w-[52px] md:translate-x-0 md:shadow-none">
    <a href="{{ route('panel') }}" class="flex h-12 shrink-0 items-center gap-2 bg-kp-navy px-3 text-white kp-focus md:justify-center md:px-0" aria-label="Ir al resumen operativo">
        <img src="{{ asset('assets/img/logo-superior.png') }}" alt="" class="h-6 w-auto" aria-hidden="true">
        <span class="text-xs font-bold tracking-wide md:hidden">KP INVEST</span>
    </a>

    @auth
        <nav class="kp-scrollbar flex-1 overflow-y-auto overflow-x-hidden px-2 py-2 md:overflow-visible md:px-1.5" aria-label="Navegación principal">
            <section aria-label="Operación">
                <h2 class="px-2 pb-1.5 pt-1 text-[9px] font-bold uppercase tracking-[0.15em] text-kp-muted md:sr-only">Operación</h2>
                <div class="space-y-0.5">
                    <a href="{{ route('dashboard') }}" class="{{ $navLink(request()->routeIs('dashboard')) }}" title="Dashboard">
                        <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z" /></svg>
                        <span class="md:hidden">Dashboard</span>
                        <span class="kp-rail-tooltip hidden md:block" role="tooltip">Dashboard</span>
                    </a>

                    <a href="#layoutClientSearch" data-rail-focus="#layoutClientSearch" class="{{ $navLink(request()->routeIs('clientes.*')) }}" title="Buscar clientes">
                        <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.2a8.9 8.9 0 0 0 3 .5 8.8 8.8 0 0 0 3.8-.9 4 4 0 0 0-6.8-2.5M15 19.2A6 6 0 0 0 3 19l-.1.1A12 12 0 0 0 9 21c2.2 0 4.2-.6 6-1.8ZM12 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Zm8 2a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z" /></svg>
                        <span class="md:hidden">Clientes</span>
                        <span class="kp-rail-tooltip hidden md:block" role="tooltip">Buscar clientes</span>
                    </a>

                    @can('review-promesas')
                        <a href="{{ route('autorizacion') }}" class="{{ $navLink(request()->is('autorizacion*')) }}" title="Autorizaciones">
                            <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 3 3 5-7m5 4a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <span class="md:hidden">Autorizaciones</span>
                            <span class="kp-rail-tooltip hidden md:block" role="tooltip">Autorizaciones</span>
                        </a>
                    @endcan
                </div>
            </section>

            @can('access-reportes')
                <div class="my-2 border-t border-kp-border"></div>

                <section aria-label="Gestión de cobranza">
                    <h2 class="px-2 pb-1.5 text-[9px] font-bold uppercase tracking-[0.15em] text-kp-muted md:sr-only">Gestión</h2>
                    <div class="space-y-0.5">
                        <a href="{{ route('reportes.pdp') }}" class="{{ $navLink(request()->routeIs('reportes.pdp*')) }}" title="Promesas">
                            <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3v3m12-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Zm4 9 2 2 4-4" /></svg>
                            <span class="md:hidden">Promesas</span>
                            <span class="kp-rail-tooltip hidden md:block" role="tooltip">Promesas</span>
                        </a>
                        <a href="{{ route('reportes.pagos') }}" class="{{ $navLink(request()->routeIs('reportes.pagos*')) }}" title="Pagos">
                            <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4V6Zm3 4h4m-4 4h7m3-4h.01" /></svg>
                            <span class="md:hidden">Pagos</span>
                            <span class="kp-rail-tooltip hidden md:block" role="tooltip">Pagos</span>
                        </a>
                        <a href="{{ route('reportes.cna') }}" class="{{ $navLink(request()->routeIs('reportes.cna*')) }}" title="CNA">
                            <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h8l4 4v14H6V3Zm8 0v5h5M9 13h6m-6 4h6" /></svg>
                            <span class="md:hidden">CNA</span>
                            <span class="kp-rail-tooltip hidden md:block" role="tooltip">CNA</span>
                        </a>
                    </div>
                </section>
            @endcan

            @can('access-integracion')
                <div class="my-2 border-t border-kp-border"></div>

                <section aria-label="Documentos e integración">
                    <h2 class="px-2 pb-1.5 text-[9px] font-bold uppercase tracking-[0.15em] text-kp-muted md:sr-only">Documentos</h2>
                    <div class="space-y-0.5">
                        <a href="{{ route('integracion.ccd.index') }}" class="{{ $navLink(request()->is('integracion/ccd*')) }}" title="CCD">
                            <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h7l2 2h9v10H3V7Zm0 0V5h7l2 2" /></svg>
                            <span class="md:hidden">CCD</span>
                            <span class="kp-rail-tooltip hidden md:block" role="tooltip">CCD</span>
                        </a>
                        <a href="{{ route('integracion.pagos.index') }}" class="{{ $navLink(request()->is('integracion/pagos*')) }}" title="Importar pagos">
                            <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" /></svg>
                            <span class="md:hidden">Importar pagos</span>
                            <span class="kp-rail-tooltip hidden md:block" role="tooltip">Importar pagos</span>
                        </a>
                        <a href="{{ route('integracion.asignacion.index') }}" class="{{ $navLink(request()->is('integracion/asignacion*')) }}" title="Asignación">
                            <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h12M8 12h12M8 18h12M3 6h.01M3 12h.01M3 18h.01" /></svg>
                            <span class="md:hidden">Asignación</span>
                            <span class="kp-rail-tooltip hidden md:block" role="tooltip">Asignación</span>
                        </a>
                        <a href="{{ route('integracion.data.index') }}" class="{{ $navLink(request()->is('integracion/data*')) }}" title="Base de clientes">
                            <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3Zm0 0v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" /></svg>
                            <span class="md:hidden">Base de clientes</span>
                            <span class="kp-rail-tooltip hidden md:block" role="tooltip">Base de clientes</span>
                        </a>
                    </div>
                </section>
            @endcan

            @if(auth()->user()?->can('access-admin-users') || auth()->user()?->can('access-reportes'))
                <div class="my-2 border-t border-kp-border"></div>

                <section aria-label="Control">
                    <h2 class="px-2 pb-1.5 text-[9px] font-bold uppercase tracking-[0.15em] text-kp-muted md:sr-only">Control</h2>
                    <div class="space-y-0.5">
                        @can('access-admin-users')
                            <a href="{{ route('administracion.index') }}" class="{{ $navLink(request()->routeIs('administracion.*')) }}" title="Usuarios">
                                <svg class="size-[17px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19a7 7 0 0 0-14 0m10-11a5 5 0 1 1-10 0 5 5 0 0 1 10 0Zm5 3h7m-3.5-3.5v7" /></svg>
                                <span class="md:hidden">Usuarios</span>
                                <span class="kp-rail-tooltip hidden md:block" role="tooltip">Usuarios</span>
                            </a>
                        @endcan
                    </div>
                </section>
            @endif
        </nav>
    @endauth
</aside>
