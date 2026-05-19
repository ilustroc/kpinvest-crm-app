@php
    $role = strtolower(auth()->user()->role ?? '');
    $isReportes = request()->routeIs('reportes.*');
    $isIntegracion = request()->is('integracion*');

    $navLink = function (bool $active) {
        return 'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold transition kp-focus '
            . ($active
                ? 'bg-kp-green-soft text-kp-green-dark'
                : 'text-kp-ink hover:bg-slate-100');
    };
@endphp

<aside id="rail"
       class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-kp-border bg-white shadow-xl transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:shadow-none">
    <div class="flex items-center justify-center gap-3 border-b border-kp-border px-4 py-4">
        <img src="{{ asset('assets/img/logo.png') }}" alt="KP INVEST" class="h-9 w-auto">
    </div>

    @auth
        <div class="border-b border-kp-border px-4 py-3">
            <div class="truncate text-sm font-bold text-kp-ink">{{ auth()->user()->name ?? 'Usuario' }}</div>
            <div class="mt-0.5 text-xs font-bold uppercase tracking-wide text-kp-muted">{{ strtoupper($role) }}</div>
        </div>

        <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-4">
            <div>
                <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-kp-muted">General</div>
                <a href="{{ route('panel') }}" class="{{ $navLink(request()->routeIs('panel')) }}">
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                    <span>Resumen</span>
                </a>
                <a href="{{ route('dashboard') }}" class="{{ $navLink(request()->routeIs('dashboard')) }}">
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    <span>Estadísticas</span>
                </a>
            </div>

            @can('access-reportes')
                <div>
                    <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-kp-muted">Reportes</div>
                    <details class="group" {{ $isReportes ? 'open' : '' }}>
                        <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-sm font-semibold text-kp-ink hover:bg-slate-100 transition">
                            <div class="flex items-center gap-3">
                                <svg class="size-5 shrink-0 text-kp-ink" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <span>Reportes</span>
                            </div>
                            <svg class="size-4 shrink-0 text-kp-muted transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </summary>
                        <div class="mt-1 space-y-1 pl-3">
                            <a href="{{ route('reportes.pagos') }}" class="{{ $navLink(request()->routeIs('reportes.pagos*')) }}">
                                <div class="size-5 shrink-0"></div> <span>Pagos</span>
                            </a>
                            <a href="{{ route('reportes.cna') }}" class="{{ $navLink(request()->routeIs('reportes.cna*')) }}">
                                <div class="size-5 shrink-0"></div> <span>CNA</span>
                            </a>
                            <a href="{{ route('reportes.pdp') }}" class="{{ $navLink(request()->routeIs('reportes.pdp*')) }}">
                                <div class="size-5 shrink-0"></div> <span>Promesas</span>
                            </a>
                        </div>
                    </details>
                </div>
            @endcan

            @can('review-promesas')
                <div>
                    <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-kp-muted">Aprobaciones</div>
                    <a href="{{ route('autorizacion') }}" class="{{ $navLink(request()->is('autorizacion*')) }}">
                        <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                        </svg>
                        <span>Autorización</span>
                    </a>
                </div>
            @endcan

            @if(auth()->user()?->can('access-integracion') || auth()->user()?->can('access-admin-users'))
                <div>
                    <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-kp-muted">Admin / Supervisión</div>
                    
                    @can('access-integracion')
                        <details class="group" {{ $isIntegracion ? 'open' : '' }}>
                            <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-sm font-semibold text-kp-ink hover:bg-slate-100 transition">
                                <div class="flex items-center gap-3">
                                    <svg class="size-5 shrink-0 text-kp-ink" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                    </svg>
                                    <span>Integración</span>
                                </div>
                                <svg class="size-4 shrink-0 text-kp-muted transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </summary>
                            <div class="mt-1 space-y-1 pl-3">
                                <a href="{{ route('integracion.pagos.index') }}" class="{{ $navLink(request()->is('integracion/pagos*')) }}">
                                    <div class="size-5 shrink-0"></div> <span>Subir Pagos</span>
                                </a>
                                <a href="{{ route('integracion.asignacion.index') }}" class="{{ $navLink(request()->is('integracion/asignacion*')) }}">
                                    <div class="size-5 shrink-0"></div> <span>Subir Asignación</span>
                                </a>
                                <a href="{{ route('integracion.ccd.index') }}" class="{{ $navLink(request()->is('integracion/ccd*')) }}">
                                    <div class="size-5 shrink-0"></div> <span>Subir CCD</span>
                                </a>
                                <a href="{{ route('integracion.data.index') }}" class="{{ $navLink(request()->is('integracion/data*')) }}">
                                    <div class="size-5 shrink-0"></div> <span>Subir Data</span>
                                </a>
                            </div>
                        </details>
                    @endcan

                    @can('access-admin-users')
                        <a href="{{ route('administracion.index') }}" class="{{ $navLink(request()->routeIs('administracion.*')) }}">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <span>Administración</span>
                        </a>
                    @endcan
                </div>
            @endif
        </nav>

        <div class="border-t border-kp-border p-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" class="w-full flex items-center justify-center gap-2">
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.25" />
                    </svg>
                    Salir
                </x-ui.button>
            </form>
        </div>
    @endauth
</aside>