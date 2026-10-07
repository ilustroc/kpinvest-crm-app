@php
    $role = strtolower(auth()->user()->role ?? '');
    $isReportes = request()->routeIs('reportes.*');
    $isIntegracion = request()->is('integracion*');

    $navLink = function (bool $active) {
        return 'group flex min-h-9 items-center gap-3 rounded-md border-l-2 px-3 py-2 text-[13px] font-medium transition kp-focus '
            . ($active
                ? 'border-kp-green bg-white/10 text-white'
                : 'border-transparent text-slate-300 hover:bg-white/[0.06] hover:text-white');
    };

    $subLink = function (bool $active) {
        return 'flex min-h-8 items-center rounded-md px-3 py-1.5 pl-10 text-xs font-medium transition kp-focus '
            . ($active
                ? 'bg-white/10 text-white'
                : 'text-kp-sidebar-muted hover:bg-white/[0.06] hover:text-white');
    };
@endphp

<aside id="rail"
       class="fixed inset-y-0 left-0 z-40 flex w-[260px] -translate-x-full flex-col border-r border-white/10 bg-kp-sidebar text-white shadow-2xl transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:shadow-none">
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 bg-kp-sidebar-deep px-5">
        <img src="{{ asset('assets/img/logo-superior.png') }}" alt="" class="h-8 w-auto" aria-hidden="true">
        <div class="leading-tight">
            <div class="text-sm font-extrabold tracking-[0.08em] text-white">KP INVEST</div>
            <div class="mt-0.5 text-[9px] font-semibold uppercase tracking-[0.16em] text-kp-sidebar-muted">CRM de operaciones</div>
        </div>
    </div>

    @auth
        <nav class="kp-scrollbar flex-1 overflow-y-auto px-3 py-4" aria-label="Navegación principal">
            <section aria-labelledby="sidebar-operation">
                <h2 id="sidebar-operation" class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-kp-sidebar-muted">Operación</h2>

                <div class="space-y-1">
                    <a href="{{ route('panel') }}" class="{{ $navLink(request()->routeIs('panel')) }}">
                        <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25A2.25 2.25 0 0 1 8.25 10.5H6A2.25 2.25 0 0 1 3.75 8.25V6Zm9.75 0a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6Zm-9.75 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25Zm9.75 0a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <a href="{{ route('panel') }}#layoutClientSearch" class="{{ $navLink(request()->routeIs('clientes.*')) }}">
                        <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Z" />
                        </svg>
                        <span>Clientes</span>
                    </a>

                    <a href="{{ route('dashboard') }}" class="{{ $navLink(request()->routeIs('dashboard')) }}">
                        <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5h16.5m-16.5 0A2.25 2.25 0 0 1 6 5.25h12a2.25 2.25 0 0 1 2.25 2.25m-16.5 0v9A2.25 2.25 0 0 0 6 18.75h12a2.25 2.25 0 0 0 2.25-2.25v-9M8.25 11.25h7.5" />
                        </svg>
                        <span>Cartera</span>
                    </a>

                    @can('access-reportes')
                        <a href="{{ route('reportes.pdp') }}" class="{{ $navLink(request()->routeIs('reportes.pdp*')) }}">
                            <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15 11.25h13.5a1.5 1.5 0 0 0 1.5-1.5v-12a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v12a1.5 1.5 0 0 0 1.5 1.5Zm3.75-7.5 2.25 2.25L15.75 10.5" /></svg>
                            <span>Promesas</span>
                        </a>
                        <a href="{{ route('reportes.pagos') }}" class="{{ $navLink(request()->routeIs('reportes.pagos*')) }}">
                            <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3-9.75H10.5a2.25 2.25 0 0 0 0 4.5h3a2.25 2.25 0 0 1 0 4.5H9M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <span>Pagos</span>
                        </a>
                        <a href="{{ route('reportes.cna') }}" class="{{ $navLink(request()->routeIs('reportes.cna*')) }}">
                            <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V14.25Z" /></svg>
                            <span>CNA</span>
                        </a>
                    @endcan

                    @can('access-integracion')
                        <a href="{{ route('integracion.ccd.index') }}" class="{{ $navLink(request()->is('integracion/ccd*')) }}">
                            <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c0-.808.65-1.464 1.458-1.464h3.87c.47 0 .913-.226 1.187-.607l.629-.87a1.46 1.46 0 0 1 1.187-.607h6.711c.808 0 1.458.655 1.458 1.463v9.558c0 .808-.65 1.463-1.458 1.463H5.208a1.46 1.46 0 0 1-1.458-1.463V9.776Z" /></svg>
                            <span>CCD</span>
                        </a>
                    @endcan
                </div>
            </section>

            @if(auth()->user()?->can('review-promesas') || auth()->user()?->can('access-integracion') || auth()->user()?->can('access-admin-users') || auth()->user()?->can('access-reportes'))
                <div class="my-4 border-t border-white/10"></div>

                <section aria-labelledby="sidebar-control">
                    <h2 id="sidebar-control" class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-kp-sidebar-muted">Control</h2>

                    <div class="space-y-1">
                        @can('review-promesas')
                            <a href="{{ route('autorizacion') }}" class="{{ $navLink(request()->is('autorizacion*')) }}">
                                <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6 2.25c0 5.523-4.477 10-10 10S1 17.523 1 12 5.477 2 11 2s10 4.477 10 10Z" /></svg>
                                <span>Autorizaciones</span>
                            </a>
                        @endcan

                        @can('access-admin-users')
                            <a href="{{ route('administracion.index') }}" class="{{ $navLink(request()->routeIs('administracion.*')) }}">
                                <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.205-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                <span>Usuarios</span>
                            </a>
                        @endcan

                        @can('access-integracion')
                            <details class="group" {{ $isIntegracion ? 'open' : '' }}>
                                <summary class="{{ $navLink($isIntegracion) }} justify-between">
                                    <span class="flex items-center gap-3">
                                        <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625A3.375 3.375 0 0 0 16.125 8.25h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V14.25Z" /></svg>
                                        <span>Documentos</span>
                                    </span>
                                    <svg class="size-3.5 shrink-0 text-kp-sidebar-muted transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                </summary>
                                <div class="mt-1 space-y-0.5">
                                    <a href="{{ route('integracion.pagos.index') }}" class="{{ $subLink(request()->is('integracion/pagos*')) }}">Carga de pagos</a>
                                    <a href="{{ route('integracion.asignacion.index') }}" class="{{ $subLink(request()->is('integracion/asignacion*')) }}">Asignación</a>
                                    <a href="{{ route('integracion.ccd.index') }}" class="{{ $subLink(request()->is('integracion/ccd*')) }}">Documentos CCD</a>
                                    <a href="{{ route('integracion.data.index') }}" class="{{ $subLink(request()->is('integracion/data*')) }}">Base de clientes</a>
                                </div>
                            </details>
                        @endcan

                        @can('access-reportes')
                            <details class="group" {{ $isReportes ? 'open' : '' }}>
                                <summary class="{{ $navLink($isReportes) }} justify-between">
                                    <span class="flex items-center gap-3">
                                        <svg class="size-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75Zm6.75-4.5c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625Zm6.75-4.5C16.5 3.504 17.004 3 17.625 3h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                                        <span>Reportes</span>
                                    </span>
                                    <svg class="size-3.5 shrink-0 text-kp-sidebar-muted transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                </summary>
                                <div class="mt-1 space-y-0.5">
                                    <a href="{{ route('reportes.pagos') }}" class="{{ $subLink(request()->routeIs('reportes.pagos*')) }}">Reporte de pagos</a>
                                    <a href="{{ route('reportes.cna') }}" class="{{ $subLink(request()->routeIs('reportes.cna*')) }}">Reporte de CNA</a>
                                    <a href="{{ route('reportes.pdp') }}" class="{{ $subLink(request()->routeIs('reportes.pdp*')) }}">Reporte de promesas</a>
                                </div>
                            </details>
                        @endcan
                    </div>
                </section>
            @endif
        </nav>

        <div class="shrink-0 border-t border-white/10 bg-kp-sidebar-deep px-5 py-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-kp-sidebar-muted">Sesión activa</div>
                    <div class="mt-0.5 max-w-40 truncate text-xs font-semibold text-slate-200">{{ auth()->user()->name ?? 'Usuario' }}</div>
                </div>
                <span class="rounded-md border border-white/10 bg-white/5 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-slate-300">{{ $role ?: 'usuario' }}</span>
            </div>
        </div>
    @endauth
</aside>
