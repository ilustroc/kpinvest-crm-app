<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>@yield('title','KP INVEST')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#062B60">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">

  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('head')
</head>
<body class="min-h-screen bg-kp-bg text-kp-ink antialiased">
  <a href="#main" class="sr-only z-50 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-kp-primary-dark shadow-lg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
    Saltar al contenido
  </a>

  <div class="min-h-screen lg:flex">
    <x-layout.sidebar />

    <div id="backdrop"
         class="fixed inset-0 z-40 hidden bg-slate-950/40 backdrop-blur-[1px] lg:hidden"
         data-toggle-rail
         aria-hidden="true"></div>

    <main id="main" class="min-w-0 flex-1" tabindex="-1">
      <x-layout.topbar>
        @yield('crumb','')
      </x-layout.topbar>

      <div class="mx-auto flex w-full max-w-[1440px] flex-col gap-5 px-4 py-5 sm:px-6 lg:px-8 lg:py-6">
        @yield('content')
        <footer class="flex flex-col gap-1 border-t border-kp-border/70 pt-4 text-[11px] text-kp-muted sm:flex-row sm:items-center sm:justify-between">
          <span>&copy; {{ date('Y') }} KP INVEST</span>
          <span>CRM Interno - Kp Invest</span>
        </footer>
      </div>
    </main>
  </div>

  @stack('scripts')
</body>
</html>
