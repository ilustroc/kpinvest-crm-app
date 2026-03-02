<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
  <meta charset="utf-8" />
  <title>Ingreso | KP Invest</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  @vite(['resources/css/auth/login.css', 'resources/js/auth/login.js'])
</head>

<body class="login-body">

  <main class="login-card">
    <div class="login-split">

      {{-- PANEL IZQUIERDO --}}
      <aside class="login-side">
        <div class="login-side-decor">
          <div class="login-blob login-blob-emerald"></div>
          <div class="login-blob login-blob-sky"></div>
          <div class="left-grid-soft"></div>
        </div>

        <div class="login-side-top">
          <img src="{{ asset('assets/img/logo.png') }}" alt="KP Invest" class="login-logo">
        </div>

        <div class="login-side-mid">
          <img
            src="{{ asset('assets/img/login-illustration.png') }}"
            alt="Ilustración"
            class="login-illus animate-fade-in"
          />
        </div>

        <div class="login-side-bot"></div>
      </aside>

      {{-- PANEL DERECHO --}}
      <section class="login-panel">
        <div class="right-soft"></div>

        <div class="login-panel-inner">

          <header class="login-head">
            <h1 class="login-kicker">Inicio de sesión</h1>
            <div class="login-divider"></div>
          </header>

          <form id="login-form" method="POST" action="{{ route('login.post') }}"
                class="login-form" novalidate>
            @csrf

            {{-- EMAIL --}}
            <div class="login-group group">
              <label class="login-label">Correo Electrónico</label>

              <div class="login-field">
                <span class="login-ic">
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
            <div class="login-group group">
              <label class="login-label">Contraseña</label>

              <div class="login-field">
                <span class="login-ic">
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
            <div class="login-remember">
              <input type="checkbox" id="remember" class="check-pro" />
              <label for="remember" class="login-remember-label">
                Mantener sesión activa
              </label>
            </div>

            {{-- BUTTON --}}
            <button class="btn-login">
              <span>Ingresar</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </button>
          </form>

          <footer class="login-foot">
            <span>© 2026 KP Invest</span>
          </footer>

        </div>
      </section>

    </div>
  </main>
</body>
</html>