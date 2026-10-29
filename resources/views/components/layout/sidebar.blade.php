@php
    $role = strtolower(auth()->user()->role ?? '');
    $isReportes = request()->routeIs('reportes.*');
    $isIntegracion = request()->is('integracion*');

    $navLink = function (bool $active) {
        return 'kp-nav-link '.($active ? 'is-active' : '');
    };

    $navIcon = 'kp-nav-icon size-[18px] shrink-0';
    $navLabel = 'kp-nav-label';
    $navGroup = 'kp-nav-group px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400';
@endphp

<aside id="rail"
       class="fixed inset-y-0 left-0 z-50 flex w-[280px] -translate-x-full flex-col border-r border-kp-border bg-kp-sidebar transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-[252px] lg:translate-x-0"
       aria-label="Navegación principal">
    <div class="kp-sidebar-header flex h-16 shrink-0 items-center border-b border-kp-border px-4">
        <a href="{{ route('panel') }}" class="inline-flex items-center kp-focus" aria-label="KP Invest · Resumen">
            <img src="{{ asset('assets/img/logo.png') }}" alt="KP Invest" class="kp-sidebar-full h-8 w-auto" loading="eager" decoding="async">
            <img src="{{ asset('assets/img/logo-superior.png') }}" alt="KP Invest" class="kp-sidebar-compact hidden h-8 w-8 object-contain" loading="eager" decoding="async">
        </a>
    </div>

    @auth
        <nav class="kp-scrollbar flex-1 space-y-5 overflow-y-auto px-3 py-4">
            <div>
                <div class="{{ $navGroup }}">General</div>
                <div class="space-y-0.5">
                    <a href="{{ route('panel') }}"
                       class="{{ $navLink(request()->routeIs('panel')) }}"
                       title="Resumen"
                       @if(request()->routeIs('panel')) aria-current="page" @endif>
                        <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                        <span class="{{ $navLabel }}">Resumen</span>
                    </a>
                    <a href="{{ route('dashboard') }}"
                       class="{{ $navLink(request()->routeIs('dashboard')) }}"
                       title="Estadísticas"
                       @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                        <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        <span class="{{ $navLabel }}">Estadísticas</span>
                    </a>
                </div>
            </div>

            @can('access-reportes')
                <div>
                    <div class="{{ $navGroup }}">Reportes</div>
                    <details class="group" {{ $isReportes ? 'open' : '' }}>
                        <summary class="{{ $navLink($isReportes) }} cursor-pointer list-none justify-between" title="Reportes">
                            <span class="flex items-center gap-2.5">
                                <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <span class="{{ $navLabel }}">Reportes</span>
                            </span>
                            <svg class="{{ $navLabel }} size-3.5 shrink-0 text-slate-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </summary>
                        <div class="kp-nav-children mt-1 space-y-0.5 pl-7">
                            <a href="{{ route('reportes.pagos') }}" class="{{ $navLink(request()->routeIs('reportes.pagos*')).' kp-nav-sub' }}">
                                <span class="size-1.5 rounded-full bg-current opacity-50"></span>
                                <span class="{{ $navLabel }}">Pagos</span>
                            </a>
                            <a href="{{ route('reportes.cna') }}" class="{{ $navLink(request()->routeIs('reportes.cna*')).' kp-nav-sub' }}">
                                <span class="size-1.5 rounded-full bg-current opacity-50"></span>
                                <span class="{{ $navLabel }}">CNA</span>
                            </a>
                            <a href="{{ route('reportes.pdp') }}" class="{{ $navLink(request()->routeIs('reportes.pdp*')).' kp-nav-sub' }}">
                                <span class="size-1.5 rounded-full bg-current opacity-50"></span>
                                <span class="{{ $navLabel }}">Promesas</span>
                            </a>
                        </div>
                    </details>
                </div>
            @endcan

            @can('review-promesas')
                <div>
                    <div class="{{ $navGroup }}">Aprobaciones</div>
                    <a href="{{ route('autorizacion') }}"
                       class="{{ $navLink(request()->is('autorizacion*')) }}"
                       title="Autorización"
                       @if(request()->is('autorizacion*')) aria-current="page" @endif>
                        <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                        </svg>
                        <span class="{{ $navLabel }}">Autorización</span>
                    </a>
                </div>
            @endcan

            @if(auth()->user()?->can('access-integracion') || auth()->user()?->can('access-admin-users'))
                <div>
                    <div class="{{ $navGroup }}">Administración</div>

                    @can('access-integracion')
                        <details class="group" {{ $isIntegracion ? 'open' : '' }}>
                            <summary class="{{ $navLink($isIntegracion) }} cursor-pointer list-none justify-between" title="Integración">
                                <span class="flex items-center gap-2.5">
                                    <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                    </svg>
                                    <span class="{{ $navLabel }}">Integración</span>
                                </span>
                                <svg class="{{ $navLabel }} size-3.5 shrink-0 text-slate-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </summary>
                            <div class="kp-nav-children mt-1 space-y-0.5 pl-7">
                                <a href="{{ route('integracion.pagos.index') }}" class="{{ $navLink(request()->is('integracion/pagos*')).' kp-nav-sub' }}">
                                    <span class="size-1.5 rounded-full bg-current opacity-50"></span><span class="{{ $navLabel }}">Subir pagos</span>
                                </a>
                                <a href="{{ route('integracion.asignacion.index') }}" class="{{ $navLink(request()->is('integracion/asignacion*')).' kp-nav-sub' }}">
                                    <span class="size-1.5 rounded-full bg-current opacity-50"></span><span class="{{ $navLabel }}">Subir asignación</span>
                                </a>
                                <a href="{{ route('integracion.ccd.index') }}" class="{{ $navLink(request()->is('integracion/ccd*')).' kp-nav-sub' }}">
                                    <span class="size-1.5 rounded-full bg-current opacity-50"></span><span class="{{ $navLabel }}">Subir CCD</span>
                                </a>
                                <a href="{{ route('integracion.data.index') }}" class="{{ $navLink(request()->is('integracion/data*')).' kp-nav-sub' }}">
                                    <span class="size-1.5 rounded-full bg-current opacity-50"></span><span class="{{ $navLabel }}">Subir data</span>
                                </a>
                            </div>
                        </details>
                    @endcan

                    @can('access-admin-users')
                        <a href="{{ route('administracion.index') }}"
                           class="{{ $navLink(request()->routeIs('administracion.*')) }}"
                           title="Administración"
                           @if(request()->routeIs('administracion.*')) aria-current="page" @endif>
                            <svg class="{{ $navIcon }}" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <span class="{{ $navLabel }}">Administración</span>
                        </a>
                    @endcan
                </div>
            @endif
        </nav>

        <div class="kp-sidebar-meta shrink-0 border-t border-kp-border p-3">
            <div class="rounded-lg bg-white px-2.5 py-2">
                <span class="min-w-0">
                    <span class="block truncate text-[12px] font-semibold text-kp-ink">{{ auth()->user()->name ?? 'Usuario' }}</span>
                    <span class="block truncate text-[10px] uppercase tracking-[0.05em] text-slate-400">{{ $role }}</span>
                </span>
            </div>
        </div>
    @endauth
</aside>
