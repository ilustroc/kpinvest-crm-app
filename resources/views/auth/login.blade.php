<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
  <meta charset="utf-8" />
  <title>Ingreso | KP Invest</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen login-bg antialiased flex items-center justify-center p-4 md:p-8">

  <main
    class="glass-card w-full max-w-[920px] overflow-hidden rounded-[2rem]
           border border-white/60 shadow-[0_28px_70px_rgba(2,6,23,.16)]
           backdrop-blur-xl"
  >
    <div class="flex flex-col lg:flex-row min-h-[520px]">

      {{-- PANEL IZQUIERDO (BLANCO) --}}
      <aside class="relative hidden lg:flex w-[42%] flex-col bg-white/70 border-r border-slate-200/70 overflow-hidden">

        {{-- decor suave --}}
        <div class="pointer-events-none absolute inset-0 opacity-80">
          <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-emerald-500/10 blur-3xl"></div>
          <div class="absolute -bottom-24 -right-24 h-72 w-72 rounded-full bg-sky-500/10 blur-3xl"></div>
          <div class="absolute inset-0 left-grid-soft"></div>
        </div>

        {{-- LOGO ARRIBA --}}
        <div class="relative z-10 flex items-center justify-center pt-10">
          <img src="{{ asset('assets/img/logo.png') }}" alt="KP Invest" class="h-9 w-auto">
        </div>

        {{-- ILUSTRACIÓN EN MEDIO --}}
        <div class="relative z-10 flex-1 flex items-center justify-center py-10">
          <img
            src="{{ asset('assets/img/login-illustration.png') }}"
            alt="Ilustración"
            class="w-full max-w-[280px] drop-shadow-[0_25px_25px_rgba(0,0,0,0.18)] animate-fade-in"
          />
        </div>

        {{-- espacio inferior (solo para balance visual) --}}
        <div class="relative z-10 pb-10"></div>
      </aside>

      {{-- PANEL DERECHO --}}
      <section class="relative flex-1 px-8 py-10 md:px-12 md:py-12 bg-white/70">
        {{-- decor --}}
        <div class="pointer-events-none absolute inset-0 right-soft"></div>

        <div class="relative z-10 flex h-full flex-col">

          {{-- INICIO DE SESIÓN ARRIBA --}}
          <header class="mb-8">
            <h1 class="text-sm font-semibold tracking-[0.18em] uppercase text-slate-400">
              Inicio de sesión
            </h1>
            <div class="mt-3 h-px w-full bg-slate-200/70"></div>
          </header>

          <form id="login-form" method="POST" action="{{ route('login.post') }}"
                class="space-y-6 flex-grow" novalidate>
            @csrf

            {{-- EMAIL --}}
            <div class="group">
              <label
                class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 mb-2
                       group-focus-within:text-emerald-600 transition-colors"
              >
                Correo Electrónico
              </label>

              <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 6h16v12H4z"/>
                    <path d="m22 6-10 7L2 6"/>
                  </svg>
                </span>

                <input
                  type="email" name="email" required
                  class="input-pro w-full pl-12"
                  placeholder="ejemplo@kpinvest.pe"
                />
              </div>
            </div>

            {{-- PASSWORD --}}
            <div class="group">
              <div class="flex justify-between mb-2">
                <label
                  class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400
                         group-focus-within:text-emerald-600 transition-colors"
                >
                  Contraseña
                </label>
              </div>

              <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/>
                    <path d="M17 8V7a5 5 0 0 0-10 0v1"/>
                    <path d="M6 8h12v13H6z"/>
                  </svg>
                </span>

                <input
                  id="password" type="password" name="password" required
                  class="input-pro w-full pl-12"
                  placeholder="••••••••"
                />
              </div>
            </div>

            {{-- REMEMBER --}}
            <div class="flex items-center gap-3 pt-1">
              <input
                type="checkbox" id="remember"
                class="h-5 w-5 rounded-lg border-slate-300 text-emerald-600
                       focus:ring-4 focus:ring-emerald-500/20 transition-all"
              />
              <label for="remember" class="text-sm text-slate-600 cursor-pointer select-none">
                Mantener sesión activa
              </label>
            </div>

            {{-- BUTTON --}}
            <button
              class="w-full rounded-2xl py-3.5 font-bold text-base text-white
                     bg-gradient-to-r from-emerald-600 to-emerald-500
                     hover:from-emerald-700 hover:to-emerald-600
                     shadow-[0_10px_35px_rgba(16,185,129,.25)]
                     transition-all active:scale-[0.99]
                     flex items-center justify-center gap-3"
            >
              <span>Ingresar al Sistema</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </button>
          </form>

          <footer class="mt-10 pt-6 border-t border-slate-200/70 flex justify-between items-center
                        text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">
            <span>© 2026 KP Invest</span>
            <a href="#" class="hover:text-slate-600 transition-colors">Soporte Técnico</a>
          </footer>

        </div>
      </section>

    </div>
  </main>
</body>
</html>