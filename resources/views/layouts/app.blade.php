<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>@yield('title','KP INVEST')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#062b60">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">

  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('head')
</head>
<body class="min-h-screen bg-kp-bg text-kp-ink antialiased">
  <div class="min-h-screen md:flex">
    <x-layout.sidebar />

    <div id="backdrop"
         class="fixed inset-0 z-30 hidden bg-slate-950/40 md:hidden"
         data-toggle-rail></div>

    <main class="min-w-0 flex-1 bg-kp-bg">
      <x-layout.topbar>
        @yield('crumb','')
      </x-layout.topbar>

      <div class="mx-auto flex w-full max-w-[1720px] flex-col gap-2.5 px-2.5 py-2.5 sm:px-3 lg:px-4 lg:py-3">
        @yield('content')
        <footer class="border-t border-kp-border pt-2 text-[10px] text-kp-muted">
          &copy; {{ date('Y') }} KP INVEST · Plataforma de cobranza y operaciones
        </footer>
      </div>
    </main>
  </div>

  @stack('scripts')
</body>
</html>
