<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Ingreso | KP Invest</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00a81c">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-kp-bg text-kp-ink antialiased">
  <main
    class="flex min-h-screen items-center justify-center bg-[radial-gradient(circle_at_top_left,rgba(0,168,28,0.10),transparent_34%),linear-gradient(180deg,#fbfcff,#f6f8fb)] px-4 py-6 sm:px-6"
    data-module="auth-login"
  >
    <section class="grid w-full max-w-6xl overflow-hidden rounded-2xl border border-kp-border bg-white shadow-2xl shadow-slate-950/5 lg:grid-cols-2">
      <aside class="hidden min-h-[560px] flex-col justify-between border-r border-kp-border bg-white p-8 lg:flex">
        <a href="/" class="inline-flex items-center">
          <img src="{{ asset('assets/img/logo.png') }}" alt="KP Invest" class="h-10 w-auto" loading="lazy" decoding="async">
        </a>

        <div class="grid place-items-center text-center">
          <img
            src="{{ asset('assets/img/login-illustration.png') }}"
            alt="Ilustracion de ingreso"
            class="max-h-80 w-auto rounded-xl drop-shadow-xl"
            loading="lazy"
            decoding="async"
          >
          <p class="mt-5 text-sm text-kp-muted">
            Bienvenido a <strong class="font-semibold text-kp-ink">KP Invest</strong>.
          </p>
        </div>

        <p class="text-xs text-kp-muted">&copy; {{ date('Y') }} KP Invest</p>
      </aside>

      <section class="flex min-h-[620px] items-center p-5 sm:p-8 lg:p-10">
        <div class="mx-auto w-full max-w-md rounded-xl border border-kp-border bg-white p-6 shadow-lg shadow-slate-950/5 sm:p-8">
          <div class="mb-7">
            <div class="mb-5 lg:hidden">
              <img src="{{ asset('assets/img/logo.png') }}" alt="KP Invest" class="h-9 w-auto" loading="lazy" decoding="async">
            </div>
            <h1 class="text-2xl font-bold text-kp-ink">Iniciar sesion</h1>
            <p class="mt-2 text-sm text-kp-muted">Ingresa tus credenciales para continuar.</p>
          </div>

          @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
              {{ $errors->first() }}
            </div>
          @endif

          <form id="login-form" method="POST" action="{{ route('login.post') }}" class="space-y-4" novalidate data-login-form>
            @csrf

            <div class="space-y-1">
              <label for="email" class="block text-sm font-semibold text-kp-ink">Correo</label>
              <input
                id="email"
                type="email"
                name="email"
                class="w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus placeholder:text-kp-muted aria-[invalid=true]:border-red-300 aria-[invalid=true]:bg-red-50"
                placeholder="tucorreo@empresa.com"
                value="{{ old('email') }}"
                required
                autocomplete="username"
                inputmode="email"
              >
              <p class="hidden text-xs font-medium text-red-600" data-error-for="email">Ingresa un correo valido.</p>
            </div>

            <div class="space-y-1">
              <div class="flex items-center justify-between gap-3">
                <label for="password" class="block text-sm font-semibold text-kp-ink">Contrasena</label>
                <span id="caps" class="hidden text-xs font-semibold text-amber-700">
                  Bloq Mayus activado
                </span>
              </div>

              <div class="flex rounded-md border border-kp-border bg-white shadow-sm focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-kp-green">
                <input
                  id="password"
                  type="password"
                  name="password"
                  class="min-w-0 flex-1 rounded-l-md border-0 bg-transparent px-3 py-2 text-sm text-kp-ink outline-none placeholder:text-kp-muted aria-[invalid=true]:bg-red-50"
                  placeholder="********"
                  required
                  autocomplete="current-password"
                >
                <button
                  type="button"
                  id="togglePwd"
                  class="rounded-r-md border-l border-kp-border px-3 text-xs font-semibold text-kp-muted transition hover:bg-slate-50 hover:text-kp-ink"
                  aria-label="Mostrar contrasena"
                  data-show-label="Mostrar"
                  data-hide-label="Ocultar"
                >
                  Mostrar
                </button>
              </div>
              <p class="hidden text-xs font-medium text-red-600" data-error-for="password">Ingresa tu contrasena.</p>
            </div>

            <div class="flex items-center justify-between gap-3 text-sm">
              <label class="inline-flex items-center gap-2 text-kp-muted">
                <input
                  type="checkbox"
                  id="remember"
                  name="remember"
                  class="size-4 rounded border-kp-border text-kp-green kp-focus"
                >
                Recordarme
              </label>
              <a href="mailto:impulse.conciliacion-cobranza@mgi-go.com" class="font-semibold text-kp-green hover:text-kp-green-dark">
                Soporte
              </a>
            </div>

            <button
              id="submitBtn"
              type="submit"
              class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-kp-green px-4 py-3 text-sm font-bold text-white transition hover:bg-kp-green-dark kp-focus disabled:pointer-events-none disabled:opacity-60"
            >
              <span class="btn-text">Ingresar</span>
              <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" role="status" aria-hidden="true" data-login-spinner></span>
            </button>
          </form>

          <div class="mt-6 flex items-center justify-between text-xs text-kp-muted">
            <span>CRM interno</span>
            <span>KP Invest</span>
          </div>
        </div>
      </section>
    </section>
  </main>
</body>
</html>
