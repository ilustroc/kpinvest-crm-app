<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Ingreso | KP Invest</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="#00a81c">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <link rel="icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/img/logo-superior.png?v=2') }}">

  <link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
</head>
<body>

  <div class="wrap row g-0">
    <!-- IZQUIERDA -->
    <div class="side col-12 col-lg-6 d-none d-lg-flex flex-column">
      <div class="d-flex align-items-center justify-content-between">
        <a href="/" class="brand d-inline-flex align-items-center gap-2">
          <img src="{{ asset('assets/img/logo.png') }}" alt="KP Invest" loading="lazy" decoding="async">
        </a>
      </div>

      <div class="side-hero flex-grow-1">
        <img class="illus img-fluid" src="/assets/img/login-illustration.png" alt="Ilustración" loading="lazy" decoding="async">
        <p class="mt-3 mb-0">Bienvenido a <strong>KP Invest</strong>.</p>
      </div>

      <div class="help small">&copy; {{ date('Y') }} KP Invest</div>
    </div>

    <!-- DERECHA (form) -->
    <div class="col-12 col-lg-6 panel d-flex align-items-center">
      <div class="card-soft w-100">
        <h1 class="h3 mb-1 fw-semibold">Iniciar sesión</h1>
        <p class="help mb-4">Ingresa tus credenciales para continuar.</p>

        @if ($errors->any())
          <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif

        <form id="login-form" method="POST" action="{{ route('login.post') }}" class="vstack gap-3" novalidate>
          @csrf

          <div>
            <label for="email" class="form-label">Correo</label>
            <input id="email" type="email" name="email" class="form-control" placeholder="tucorreo@empresa.com"
                   value="{{ old('email') }}" required autocomplete="username" inputmode="email">
            <div class="invalid-feedback">Ingresa un correo válido.</div>
          </div>

          <div>
            <label for="password" class="form-label d-flex justify-content-between align-items-center">
              <span>Contraseña</span>
              <span id="caps" class="small d-none">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <span class="caps">Bloq Mayús activado</span>
              </span>
            </label>
            <div class="input-group">
              <input id="password" type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
              <button class="input-group-text" type="button" id="togglePwd" aria-label="Mostrar contraseña">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="invalid-feedback">Ingresa tu contraseña.</div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember" name="remember">
              <label class="form-check-label" for="remember">Recordarme</label>
            </div>
            <a href="#" class="small link">¿Olvidaste tu contraseña?</a>
          </div>

          <button id="submitBtn" class="btn btn-brand w-100 mt-2">
            <span class="btn-text">Ingresar</span>
            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
          </button>
        </form>

        <div class="mt-4 d-flex justify-content-between small">
          <a class="link" href="mailto:impulse.conciliacion-cobranza@mgi-go.com">Soporte</a>
          <span class="text-muted">KP Invest</span>
        </div>
      </div>
    </div>
  </div>

  <script src="{{ asset('js/auth/login.js') }}" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
