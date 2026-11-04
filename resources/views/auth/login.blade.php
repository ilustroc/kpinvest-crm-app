<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Ingreso | KP Invest</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#062B60">
  <meta name="description" content="Acceso a la plataforma CRM de KP Invest.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">

  @vite(['resources/css/app.css', 'resources/js/login.js'])
</head>
<body class="min-h-svh bg-white text-kp-ink antialiased">
  <main class="min-h-svh bg-white lg:grid lg:grid-cols-[1.08fr_0.92fr]" data-module="auth-login">
    <aside class="relative hidden bg-[#F5F7FA] lg:flex lg:min-h-svh lg:items-center lg:justify-center lg:overflow-hidden lg:px-10 xl:px-14">
      <img
        src="{{ asset('assets/img/kp-invest-login-illustration.png') }}"
        alt=""
        aria-hidden="true"
        class="h-auto max-h-[84svh] w-auto max-w-[90%] select-none object-contain"
        decoding="async"
        draggable="false"
      >
    </aside>

    <section class="relative flex min-h-svh flex-col bg-white">
      <div class="flex flex-1 flex-col px-5 pb-8 pt-6 sm:px-8 lg:items-center lg:justify-center lg:px-14 lg:py-12 xl:px-16">
        <div class="w-full max-w-[380px] lg:my-auto">
          <img
            src="{{ asset('assets/img/logo.png') }}"
            alt="KP Invest"
            class="h-9 w-auto sm:h-10"
            decoding="async"
          >

          <h1 class="mt-10 text-[27px] font-semibold tracking-[-0.03em] text-kp-ink sm:text-[29px] lg:mt-12">Iniciar sesión</h1>

          @if (request()->query('expired'))
            <div class="mt-6 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 text-[13px] text-amber-800" role="alert">
              <svg class="mt-0.5 size-4 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3h.008v.008H12v-.008ZM10.34 3.84 2.52 17.25A1.5 1.5 0 0 0 3.82 19.5h16.36a1.5 1.5 0 0 0 1.3-2.25L13.66 3.84a1.5 1.5 0 0 0-2.6 0Z" />
              </svg>
              <span>Tu sesión expiró por inactividad. Ingresa nuevamente.</span>
            </div>
          @endif

          @if ($errors->any())
            <div class="mt-6 flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-3 text-[13px] text-red-800" role="alert">
              <svg class="mt-0.5 size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3h.008v.008H12v-.008ZM10.34 3.84 2.52 17.25A1.5 1.5 0 0 0 3.82 19.5h16.36a1.5 1.5 0 0 0 1.3-2.25L13.66 3.84a1.5 1.5 0 0 0-2.6 0Z" />
              </svg>
              <span>{{ $errors->first() }}</span>
            </div>
          @endif

          <form id="login-form" method="POST" action="{{ route('login.post') }}" class="mt-8 space-y-5" novalidate data-login-form>
            @csrf

            <div>
              <label for="email" class="mb-2 block text-[12px] font-semibold text-slate-700">Correo electrónico</label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                  <svg class="size-[17px]" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                  </svg>
                </span>
                <input
                  id="email"
                  type="email"
                  name="email"
                  class="h-12 w-full rounded-lg border border-[#E4E7EC] bg-white pl-10 pr-3 text-sm text-kp-ink shadow-[0_1px_2px_rgba(16,24,40,0.03)] transition placeholder:text-slate-400 hover:border-slate-300 kp-focus aria-[invalid=true]:border-red-300 aria-[invalid=true]:bg-red-50/40"
                  placeholder="nombre@empresa.com"
                  value="{{ old('email') }}"
                  required
                  autocomplete="username"
                  inputmode="email"
                >
              </div>
              <p class="mt-1.5 hidden text-xs font-medium text-red-600" data-error-for="email">Ingresa un correo válido.</p>
            </div>

            <div>
              <div class="mb-2 flex items-center justify-between gap-3">
                <label for="password" class="block text-[12px] font-semibold text-slate-700">Contraseña</label>
                <span id="caps" class="hidden text-[10px] font-semibold text-amber-700">Bloq Mayús activado</span>
              </div>

              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                  <svg class="size-[17px]" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                  </svg>
                </span>
                <input
                  id="password"
                  type="password"
                  name="password"
                  class="h-12 w-full rounded-lg border border-[#E4E7EC] bg-white pl-10 pr-11 text-sm text-kp-ink shadow-[0_1px_2px_rgba(16,24,40,0.03)] transition placeholder:text-slate-400 hover:border-slate-300 kp-focus aria-[invalid=true]:border-red-300 aria-[invalid=true]:bg-red-50/40"
                  required
                  autocomplete="current-password"
                >
                <button
                  type="button"
                  id="togglePwd"
                  class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 transition kp-focus"
                  aria-label="Mostrar contraseña"
                  title="Mostrar contraseña"
                >
                  <svg class="size-5 eye-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                  </svg>
                  <svg class="size-5 eye-off-icon hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                  </svg>
                </button>
              </div>
              <p class="mt-1.5 hidden text-xs font-medium text-red-600" data-error-for="password">Ingresa tu contraseña.</p>
            </div>

            <label class="inline-flex items-center gap-2 text-[12px] text-slate-600">
              <input
                type="checkbox"
                id="remember"
                name="remember"
                class="size-[15px] rounded border-[#D0D5DD] accent-kp-primary kp-focus"
              >
              Recordarme
            </label>

            <button
              id="submitBtn"
              type="submit"
              class="mt-1 inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-kp-primary-dark px-4 text-sm font-semibold text-white shadow-[0_8px_22px_rgba(0,133,23,0.20)] transition hover:bg-kp-primary-darker kp-focus disabled:pointer-events-none disabled:opacity-60"
            >
              <span class="btn-text">Ingresar</span>
              <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" role="status" aria-hidden="true" data-login-spinner></span>
            </button>
          </form>

          <div class="mt-6 text-center">
            <a href="mailto:jesus.garcia@kpinvest.pe" class="text-[12px] font-medium text-kp-secondary transition hover:text-kp-secondary-dark">
              Soporte
            </a>
          </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
