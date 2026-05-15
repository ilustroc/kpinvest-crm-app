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
    <div class="flex items-center gap-3 border-b border-kp-border px-4 py-4">
        <div class="grid size-10 place-items-center rounded-lg bg-kp-green-soft font-black text-kp-green-dark">KP</div>
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
                    <span class="grid size-7 place-items-center rounded-md bg-kp-green-soft text-xs text-kp-green-dark">IN</span>
                    <span>Resumen</span>
                </a>
                <a href="{{ route('dashboard') }}" class="{{ $navLink(request()->routeIs('dashboard')) }}">
                    <span class="grid size-7 place-items-center rounded-md bg-kp-green-soft text-xs text-kp-green-dark">ES</span>
                    <span>Estadisticas</span>
                </a>
            </div>

            @can('access-reportes')
                <div>
                    <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-kp-muted">Reportes</div>
                    <details class="group" {{ $isReportes ? 'open' : '' }}>
                        <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-sm font-bold text-kp-ink hover:bg-slate-100">
                            <span>Reportes</span>
                            <span class="text-kp-muted transition group-open:rotate-180">v</span>
                        </summary>
                        <div class="mt-1 space-y-1 pl-3">
                            <a href="{{ route('reportes.pagos') }}" class="{{ $navLink(request()->routeIs('reportes.pagos*')) }}">Pagos</a>
                            <a href="{{ route('reportes.cna') }}" class="{{ $navLink(request()->routeIs('reportes.cna*')) }}">CNA</a>
                            <a href="{{ route('reportes.pdp') }}" class="{{ $navLink(request()->routeIs('reportes.pdp*')) }}">Promesas</a>
                        </div>
                    </details>
                </div>
            @endcan

            @can('review-promesas')
                <div>
                    <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-kp-muted">Aprobaciones</div>
                    <a href="{{ route('autorizacion') }}" class="{{ $navLink(request()->is('autorizacion*')) }}">
                        <span>Autorizacion</span>
                    </a>
                </div>
            @endcan

            @if(auth()->user()?->can('access-integracion') || auth()->user()?->can('access-admin-users'))
                <div>
                    <div class="px-3 pb-2 text-xs font-bold uppercase tracking-wide text-kp-muted">Admin / Supervision</div>
                    @can('access-integracion')
                        <details class="group" {{ $isIntegracion ? 'open' : '' }}>
                            <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-sm font-bold text-kp-ink hover:bg-slate-100">
                                <span>Integracion</span>
                                <span class="text-kp-muted transition group-open:rotate-180">v</span>
                            </summary>
                            <div class="mt-1 space-y-1 pl-3">
                                <a href="{{ route('integracion.pagos.index') }}" class="{{ $navLink(request()->is('integracion/pagos*')) }}">Subir Pagos</a>
                                <a href="{{ route('integracion.asignacion.index') }}" class="{{ $navLink(request()->is('integracion/asignacion*')) }}">Subir Asignacion</a>
                                <a href="{{ route('integracion.ccd.index') }}" class="{{ $navLink(request()->is('integracion/ccd*')) }}">Subir CCD</a>
                                <a href="{{ route('integracion.data.index') }}" class="{{ $navLink(request()->is('integracion/data*')) }}">Subir Data</a>
                            </div>
                        </details>
                    @endcan

                    @can('access-admin-users')
                        <a href="{{ route('administracion.index') }}" class="{{ $navLink(request()->routeIs('administracion.*')) }}">
                            <span>Administracion</span>
                        </a>
                    @endcan
                </div>
            @endif
        </nav>

        <div class="border-t border-kp-border p-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" class="w-full">Salir</x-ui.button>
            </form>
        </div>
    @endauth
</aside>
