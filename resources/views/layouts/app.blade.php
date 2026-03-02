<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
  <meta charset="utf-8">
  <title>@yield('title','KP INVEST')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00a81c">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="clientes-suggest-url" content="{{ route('clientes.suggest') }}">

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">
  
  @vite(['resources/css/layout/app.css', 'resources/js/layout/app.js'])

  @stack('head')
</head>

<body class="min-h-screen kp-bg antialiased text-slate-900">

@php
  $role = strtolower(auth()->user()->role ?? '');

  $isReportes    = request()->routeIs('reportes.*');
  $isIntegracion = request()->is('integracion*');

  $navBase   = 'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold border transition';
  $navIdle   = 'border-transparent text-slate-700 hover:bg-emerald-500/10 hover:border-emerald-500/20';
  $navActive = 'bg-emerald-500/10 border-emerald-500/25 text-slate-900';
  $iconBox   = 'h-9 w-9 rounded-xl grid place-items-center bg-emerald-500/10 text-emerald-700';
  $labCls    = 'px-3 pt-4 pb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400';
@endphp

<div class="min-h-screen flex">

  {{-- Backdrop (móvil) --}}
  <button id="kpBackdrop" type="button"
          class="fixed inset-0 z-40 hidden bg-black/35 lg:hidden"
          aria-label="Cerrar menú"></button>

  {{-- Sidebar --}}
  <aside id="kpRail"
         class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full lg:translate-x-0
                lg:sticky lg:top-0 lg:h-screen
                border-r border-slate-200/70 bg-white/70 backdrop-blur-xl
                shadow-[0_18px_60px_rgba(2,6,23,.10)]
                transition-transform duration-200 ease-out flex flex-col">

    {{-- Brand --}}
    <div class="flex items-center gap-3 px-4 py-4 border-b border-slate-200/70">
      <div class="h-11 w-11 rounded-2xl grid place-items-center bg-emerald-500/10 text-emerald-700">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 21h18"/>
          <path d="M5 21V7a2 2 0 0 1 2-2h3v16"/>
          <path d="M14 21V3h5a2 2 0 0 1 2 2v16"/>
          <path d="M8 9h1"/><path d="M8 13h1"/><path d="M8 17h1"/>
          <path d="M16 7h1"/><path d="M16 11h1"/><path d="M16 15h1"/><path d="M16 19h1"/>
        </svg>
      </div>
      <img src="{{ asset('assets/img/logo.png') }}" alt="KP INVEST" class="h-9 w-auto">
    </div>

    {{-- User --}}
    <div class="px-4 py-3 border-b border-slate-200/70">
      <div class="font-extrabold text-slate-900 leading-tight">
        {{ auth()->user()->name ?? 'Usuario' }}
      </div>
      <div class="mt-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">
        {{ strtoupper($role) }}
      </div>
    </div>

    {{-- Quick DNI (entre User y GENERAL) --}}
    @auth
      <div class="px-4 py-3 border-b border-slate-200/70">
        <form id="frmQuickDni" data-show-url="{{ route('clientes.show','__DNI__') }}" class="relative">
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-emerald-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/>
              <path d="m21 21-4.3-4.3"/>
            </svg>
          </span>

          <input id="inpQuickDni"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                maxlength="8"
                placeholder="Buscar DNI..."
                class="w-full rounded-2xl border border-emerald-600/25 bg-white/70 pl-9 pr-3 py-2 text-sm
                        text-slate-900 placeholder:text-slate-400 shadow-sm outline-none transition
                        focus:border-emerald-500/50 focus:ring-4 focus:ring-emerald-500/15">
        </form>
      </div>
    @endauth

    {{-- Nav --}}
    <nav class="px-2 py-2 overflow-y-auto overflow-x-hidden flex-1">
      @auth

        <div class="{{ $labCls }}">GENERAL</div>

        <a data-close-rail href="{{ route('panel') }}"
           class="{{ $navBase }} {{ request()->routeIs('panel') ? $navActive : $navIdle }}">
          <span class="{{ $iconBox }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h7v7H4z"/><path d="M13 4h7v7h-7z"/><path d="M4 13h7v7H4z"/><path d="M13 13h7v7h-7z"/>
            </svg>
          </span>
          <span>Resumen</span>
        </a>

        <a data-close-rail href="{{ route('dashboard') }}"
           class="{{ $navBase }} {{ request()->routeIs('dashboard') ? $navActive : $navIdle }}">
          <span class="{{ $iconBox }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 3v18h18"/>
              <path d="M7 14v4"/><path d="M12 10v8"/><path d="M17 6v12"/>
            </svg>
          </span>
          <span>Estadísticas</span>
        </a>

        {{-- REPORTES --}}
        @if(in_array($role, ['supervisor','administrador','sistemas','soporte']))
          <div class="{{ $labCls }}">REPORTES</div>

          <button type="button"
                  class="{{ $navBase }} w-full {{ $isReportes ? $navActive : $navIdle }}"
                  data-acc-btn="reportes"
                  aria-expanded="{{ $isReportes ? 'true' : 'false' }}">
            <span class="{{ $iconBox }}">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19V5"/><path d="M8 19V9"/><path d="M12 19V7"/><path d="M16 19V11"/><path d="M20 19V4"/>
              </svg>
            </span>
            <span>Reportes</span>
            <span class="ml-auto text-slate-400 transition-transform duration-200" data-acc-chev="reportes">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </span>
          </button>

          <div data-acc-panel="reportes" class="mt-2 pl-12 {{ $isReportes ? '' : 'hidden' }}">
            <a data-close-rail href="{{ route('reportes.pagos') }}"
               class="block rounded-xl px-3 py-2 text-sm font-semibold border transition
                      {{ request()->routeIs('reportes.pagos*') ? 'bg-emerald-500/10 border-emerald-500/25' : 'border-transparent hover:bg-emerald-500/10 hover:border-emerald-500/20' }}">
              Reporte de Pagos
            </a>

            <a data-close-rail href="{{ route('reportes.cna') }}"
               class="mt-1 block rounded-xl px-3 py-2 text-sm font-semibold border transition
                      {{ request()->routeIs('reportes.cna*') ? 'bg-emerald-500/10 border-emerald-500/25' : 'border-transparent hover:bg-emerald-500/10 hover:border-emerald-500/20' }}">
              Reporte de Cna
            </a>

            <a data-close-rail href="{{ route('reportes.pdp') }}"
               class="mt-1 block rounded-xl px-3 py-2 text-sm font-semibold border transition
                      {{ request()->routeIs('reportes.pdp*') ? 'bg-emerald-500/10 border-emerald-500/25' : 'border-transparent hover:bg-emerald-500/10 hover:border-emerald-500/20' }}">
              Reporte de Promesas
            </a>
          </div>
        @endif

        {{-- APROBACIONES --}}
        @if(in_array($role, ['supervisor','administrador']))
          <div class="{{ $labCls }}">APROBACIONES</div>
          <a data-close-rail href="{{ route('autorizacion') }}"
             class="{{ $navBase }} {{ request()->is('autorizacion*') ? $navActive : $navIdle }}">
            <span class="{{ $iconBox }}">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
              </svg>
            </span>
            <span>Autorización</span>
          </a>
        @endif

        {{-- INTEGRACIÓN --}}
        @if(in_array($role, ['administrador','supervisor','soporte','sistemas']))
          <div class="{{ $labCls }}">ADMIN / SUPERVISIÓN</div>

          <button type="button"
                  class="{{ $navBase }} w-full {{ $isIntegracion ? $navActive : $navIdle }}"
                  data-acc-btn="integracion"
                  aria-expanded="{{ $isIntegracion ? 'true' : 'false' }}">
            <span class="{{ $iconBox }}">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <ellipse cx="12" cy="5" rx="8" ry="3"/>
                <path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/>
                <path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>
              </svg>
            </span>
            <span>Integración</span>
            <span class="ml-auto text-slate-400 transition-transform duration-200" data-acc-chev="integracion">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </span>
          </button>

          <div data-acc-panel="integracion" class="mt-2 pl-12 {{ $isIntegracion ? '' : 'hidden' }}">
            <a data-close-rail href="{{ route('integracion.pagos.index') }}"
               class="block rounded-xl px-3 py-2 text-sm font-semibold border transition
                      {{ request()->is('integracion/pagos*') ? 'bg-emerald-500/10 border-emerald-500/25' : 'border-transparent hover:bg-emerald-500/10 hover:border-emerald-500/20' }}">
              Subir Pagos
            </a>

            <a data-close-rail href="{{ route('integracion.asignacion.index') }}"
               class="mt-1 block rounded-xl px-3 py-2 text-sm font-semibold border transition
                      {{ request()->is('integracion/asignacion*') ? 'bg-emerald-500/10 border-emerald-500/25' : 'border-transparent hover:bg-emerald-500/10 hover:border-emerald-500/20' }}">
              Subir Asignación
            </a>

            <a data-close-rail href="{{ route('integracion.ccd.index') }}"
               class="mt-1 block rounded-xl px-3 py-2 text-sm font-semibold border transition
                      {{ request()->is('integracion/ccd*') ? 'bg-emerald-500/10 border-emerald-500/25' : 'border-transparent hover:bg-emerald-500/10 hover:border-emerald-500/20' }}">
              Subir CCD
            </a>

            <a data-close-rail href="{{ route('integracion.data.index') }}"
               class="mt-1 block rounded-xl px-3 py-2 text-sm font-semibold border transition
                      {{ request()->is('integracion/data*') ? 'bg-emerald-500/10 border-emerald-500/25' : 'border-transparent hover:bg-emerald-500/10 hover:border-emerald-500/20' }}">
              Subir Data
            </a>
          </div>

          <a data-close-rail href="{{ route('administracion.index') }}"
             class="{{ $navBase }} mt-2 {{ request()->routeIs('administracion.*') ? $navActive : $navIdle }}">
            <span class="{{ $iconBox }}">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/>
                <path d="M19.4 15a7.7 7.7 0 0 0 .1-6l-2.1.5a6.2 6.2 0 0 0-1.4-1.4l.5-2.1a7.7 7.7 0 0 0-6-.1l.5 2.1a6.2 6.2 0 0 0-1.4 1.4L5 9a7.7 7.7 0 0 0-.1 6l2.1-.5c.4.5.9 1 1.4 1.4l-.5 2.1a7.7 7.7 0 0 0 6 .1l-.5-2.1c.5-.4 1-.9 1.4-1.4Z"/>
              </svg>
            </span>
            <span>Administración</span>
          </a>
        @endif

      @endauth
    </nav>

    {{-- Footer rail --}}
    <div class="mt-auto border-t border-slate-200/70 p-3">
      @auth
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit"
                  class="w-full rounded-xl border border-emerald-600/30 bg-white/60 px-3 py-2.5
                         text-sm font-extrabold text-emerald-700 hover:bg-emerald-500/10
                         transition flex items-center justify-center gap-2">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10 17l5-5-5-5"/>
              <path d="M15 12H3"/>
              <path d="M21 21V3a2 2 0 0 0-2-2h-6"/>
            </svg>
            Salir
          </button>
        </form>
      @endauth

      <div class="mt-2 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
        © {{ date('Y') }} KP INVEST
      </div>
    </div>
  </aside>

  {{-- Main --}}
  <main class="flex-1 min-w-0 flex flex-col">

    {{-- Appbar --}}
    <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/70 backdrop-blur-xl">
      <div class="mx-auto max-w-[1220px] px-4 sm:px-6 py-3 flex flex-wrap items-center gap-3">

        <button id="kpMenuBtn" type="button"
                class="lg:hidden inline-flex items-center justify-center rounded-xl
                      border border-emerald-600/25 bg-white/70 p-2.5 text-emerald-700
                      hover:bg-emerald-500/10 transition"
                aria-label="Abrir menú">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>
          </svg>
        </button>

        <div class="text-base font-extrabold tracking-tight text-slate-700">
          @yield('crumb','')
        </div>

      </div>
    </header>

    {{-- Content --}}
    <section class="flex-1">
      <div class="mx-auto max-w-[1220px] px-2 sm:px-4 py-4 space-y-2">
        @yield('content')

        <div class="pt-6 text-xs text-slate-500">
          © {{ date('Y') }} KP INVEST
        </div>
      </div>
    </section>
  </main>
</div>

{{-- JS extra por vista --}}
@stack('scripts')
</body>
</html>