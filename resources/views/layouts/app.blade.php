<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>@yield('title','KP INVEST')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00a81c">
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
  <div class="min-h-screen lg:flex">
    <x-layout.sidebar />

    <div id="backdrop"
         class="fixed inset-0 z-30 hidden bg-slate-950/40 lg:hidden"
         data-toggle-rail></div>

    <main class="min-w-0 flex-1 bg-kp-bg">
      <x-layout.topbar>
        @yield('crumb','')
      </x-layout.topbar>

      <div class="mx-auto flex w-full max-w-[1680px] flex-col gap-3 px-3 py-3 sm:px-4 lg:px-5 lg:py-4">
        @yield('content')
        <footer class="border-t border-kp-border pt-3 text-[11px] text-kp-muted">
          &copy; {{ date('Y') }} KP INVEST · Plataforma de cobranza y operaciones
        </footer>
      </div>
    </main>
  </div>

  @stack('scripts')
</body>
</html>
