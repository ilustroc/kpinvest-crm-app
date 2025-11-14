<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>@yield('title','KP INVEST')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00a81c">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    /* ======= TOKENS (marca verde KP) ======= */
    :root{
      --brand:#00a81c;            /* verde principal */
      --brand-ink:#008517;        /* hover/ink */
      --brand-tint:#e9f9ec;       /* tint suave */
      --radius:14px; --radius-sm:10px;
      --shadow:0 12px 36px rgba(15,23,42,.08);
      --shadow-sm:0 8px 22px rgba(15,23,42,.06);
      --font:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
      --content-max:1220px;

      /* Tema ÚNICO (light) */
      --bg:#f7f8fc; --surface:#ffffff; --surface-2:#f3f6fb; --border:#e8ecf3;
      --ink:#151a23; --muted:#6d7b8a;

      --bs-body-bg:var(--bg); --bs-body-color:var(--ink); --bs-heading-color:var(--ink);
      --bs-secondary-color:var(--muted); --bs-border-color:var(--border);
      --bs-card-bg:var(--surface); --bs-card-border-color:var(--border);
    }

    /* ======= ESCALA GLOBAL ======= */
    html{ font-size:15px; }
    @media (min-width: 1600px){ html{ font-size:14.5px; } }

    html,body{height:100%}
    body{margin:0; background:var(--bg); color:var(--ink); font-family:var(--font)}

    /* ======= SHELL ======= */
    .shell{display:flex; min-height:100vh;}

    /* Sidebar */
    .rail{
      width:240px; flex:0 0 240px; height:100vh; position:sticky; top:0;
      background:
        radial-gradient(900px 500px at -10% -10%, color-mix(in oklab, var(--brand) 8%, transparent), transparent 60%),
        linear-gradient(180deg, var(--surface-2), var(--surface));
      border-right:1px solid var(--border);
      display:flex; flex-direction:column;
    }

    .brand{ display:flex; gap:12px; align-items:center; padding:14px 16px; border-bottom:1px solid var(--border) }
    .brand .mark{
      width:40px; height:40px; border-radius:12px; display:grid; place-items:center;
      background: color-mix(in oklab, var(--brand) 14%, transparent);
      color:var(--brand);
      flex:0 0 40px;
      font-size:1.05rem;
    }
    .logo { display:flex; align-items:center; gap:10px }
    .logo img{ height:36px; width:auto; display:block } /* usa 1 solo logo.png */

    .who{padding:10px 16px; border-bottom:1px solid var(--border)}
    .who .n{font-weight:700}
    .who .r{font-size:.78rem; color:var(--muted); text-transform:uppercase; letter-spacing:.5px}

    .navy{padding:8px; overflow:auto}
    .navy .lab{font-size:.72rem; color:var(--muted); padding:8px 10px 6px}
    .navy a{
      position:relative;
      display:flex; align-items:center; gap:.6rem;
      padding:8px 10px; border-radius:12px;
      color:inherit; text-decoration:none; border:1px solid transparent; font-size:.98rem;
    }
    .navy a i{
      color:var(--brand);
      background: color-mix(in oklab, var(--brand) 16%, transparent);
      width:32px; height:32px; border-radius:10px; display:grid; place-items:center;
      font-size:1rem;
    }
    .navy a:hover{
      background: color-mix(in oklab, var(--brand) 10%, transparent);
      border-color: color-mix(in oklab, var(--brand) 26%, transparent);
    }
    .navy a.active{
      background: color-mix(in oklab, var(--brand) 14%, transparent);
      border-color: color-mix(in oklab, var(--brand) 36%, transparent);
      font-weight:600;
    }
    .navy a.active::before{
      content:""; position:absolute; left:-10px; top:8px; bottom:8px; width:4px;
      background: linear-gradient(180deg, var(--brand), var(--brand-ink)); border-radius:8px;
    }

    /* ======= Botón de acordeón (padre) ======= */
    .navy .accordion-btn{
      width:100%; text-align:left; background:transparent; border:1px solid transparent;
      display:flex; align-items:center; gap:.6rem; padding:8px 10px; border-radius:12px;
      font-weight:600; color:inherit; cursor:pointer;
    }
    .navy .accordion-btn:hover{
      background: color-mix(in oklab, var(--brand) 10%, transparent);
      border-color: color-mix(in oklab, var(--brand) 26%, transparent);
    }
    .navy .accordion-btn i{
      color:var(--brand);
      background: color-mix(in oklab, var(--brand) 16%, transparent);
      width:32px; height:32px; border-radius:10px; display:grid; place-items:center;
      font-size:1rem;
    }
    .navy .accordion-btn .chev{
      margin-left:auto; transition:transform .18s ease;
    }
    .navy .accordion-btn[aria-expanded="true"] .chev{ transform:rotate(180deg) }

    /* Submenú */
    .navy .submenu{
      padding:6px 0 8px 42px; /* sangría para alinear */
    }
    .navy .submenu a{
      padding:7px 10px; border-radius:10px; font-weight:500;
    }

    .rail-foot{margin-top:auto; padding:12px; border-top:1px solid var(--border)}
    .rail-foot .small{ color:var(--muted); }

    /* ======= MAIN ======= */
    .main{flex:1; min-width:0; display:flex; flex-direction:column;}
    .appbar{
      position:sticky; top:0; z-index:10;
      background:var(--surface); border-bottom:1px solid var(--border);
      box-shadow:0 4px 16px rgba(15,23,42,.03);
    }
    .appbar-in{
      margin:0 auto; max-width:var(--content-max);
      padding:10px 20px; display:flex; align-items:center; gap:12px;
    }
    .appbar .crumb{ font-weight:700; letter-spacing:.2px; font-size:1.05rem }

    .content{flex:1}
    .content-in{
      margin:0 auto; max-width:var(--content-max);
      padding:18px 20px 16px; display:flex; flex-direction:column; gap:16px;
    }

    .card{background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow-sm)}
    .card.pad{padding:12px 14px}

    .chip{
      background:var(--surface); border:1px solid var(--border); border-radius:12px;
      padding:12px 14px; height:100%; transition:transform .06s, box-shadow .12s;
      display:flex; flex-direction:column; gap:6px;
    }
    .chip:hover{transform:translateY(-1px); box-shadow:var(--shadow)}
    .chip .t{display:flex; align-items:center; gap:10px; font-weight:600}
    .chip .t i{
      color:var(--brand); background:color-mix(in oklab, var(--brand) 18%, transparent);
      width:32px; height:32px; border-radius:10px; display:grid; place-items:center;
      font-size:1rem;
    }
    .chip .s{color:var(--muted); font-size:.9rem}

    .kpi{position:relative; background:var(--surface); border:1px solid var(--border);
      border-radius:12px; padding:14px; height:100%; display:flex; flex-direction:column; justify-content:center; gap:6px;}
    .kpi::before{
      content:""; position:absolute; left:0; top:0; bottom:0; width:4px;
      background:linear-gradient(180deg, var(--brand), var(--brand-ink)); opacity:.95;
      border-top-left-radius:12px; border-bottom-left-radius:12px;
    }
    .kpi .label{color:var(--muted); font-size:.88rem}
    .kpi .value{font-weight:800; font-size:1.7rem; line-height:1}

    .footer{margin-top:auto; padding:12px 0; color:var(--muted)}

    :focus-visible{
      outline:3px solid color-mix(in oklab, var(--brand) 50%, transparent);
      outline-offset:2px; border-radius:10px;
    }

    /* ======= Tablas / formularios compactos ======= */
    .table> :not(caption)>*>*{ padding:.55rem .75rem; }
    .form-control,.form-select{ background:var(--surface); border-color:var(--border); }
    .form-control::placeholder{ color:var(--muted) }
    .form-control:focus,.form-select:focus{
      background:var(--surface);
      border-color: color-mix(in oklab, var(--brand) 52%, var(--border));
      box-shadow: 0 0 0 .25rem color-mix(in oklab, var(--brand) 22%, transparent);
    }

    /* ======= Botones globales en verde ======= */
    .btn-primary{ background:var(--brand); border-color:var(--brand); }
    .btn-primary:hover{ background:var(--brand-ink); border-color:var(--brand-ink); }
    .btn-primary:focus{ box-shadow:0 0 0 .25rem color-mix(in oklab, var(--brand) 28%, transparent) }

    .btn-outline-primary{ color:var(--brand); border-color:var(--brand); }
    .btn-outline-primary:hover{ color:#fff; border-color:var(--brand-ink); background:var(--brand-ink) }
    .btn-outline-primary:focus{ box-shadow:0 0 0 .25rem color-mix(in oklab, var(--brand) 20%, transparent) }

    /* ======= Scrollbar ======= */
    ::-webkit-scrollbar{ width:10px; height:10px }
    ::-webkit-scrollbar-thumb{ background: color-mix(in oklab, var(--brand) 22%, transparent); border-radius:10px }
    ::-webkit-scrollbar-track{ background: transparent }

    @media (max-width: 992px){
      .rail{position:fixed; left:-240px; z-index:1050; transition:left .2s}
      .rail.show{left:0}
      .backdrop{position:fixed; inset:0; background:rgba(0,0,0,.35); display:none; z-index:1040}
      .backdrop.show{display:block}
      .appbar-in,.content-in{padding-left:16px; padding-right:16px}
    }
  </style>
  @stack('head')
</head>
<body>
  @php
    $route = request()->route();
    $role  = strtolower(auth()->user()->role ?? '');
    $isReportes     = request()->routeIs('reportes.*');
    $isIntegracion  = request()->is('integracion/*');
  @endphp

  <div class="shell">
    <!-- Sidebar -->
    <aside id="rail" class="rail">
      <div class="brand">
        <div class="mark"><i class="bi bi-building"></i></div>
        <div class="logo">
          <!-- ÚNICO logo -->
          <img src="{{ asset('assets/img/logo.png') }}" alt="KP INVEST">
        </div>
      </div>

      <div class="who">
        <div class="n">{{ auth()->user()->name ?? 'Usuario' }}</div>
        <div class="r">{{ auth()->user()->role ?? '' }}</div>
      </div>

      <nav class="navy">
        @auth
          {{-- GENERAL --}}
          <div class="lab">GENERAL</div>
          <a href="{{ route('panel') }}" class="{{ request()->routeIs('panel') ? 'active' : '' }}">
            <i class="bi bi-grid"></i><span>Resumen</span>
          </a>
          <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-graph-up"></i><span>Estadísticas</span>
          </a>

          {{-- REPORTES (supervisor, admin, sistemas, soporte) --}}
          @if(in_array($role, ['supervisor','administrador','sistemas','soporte']))
            <div class="lab">REPORTES</div>

            <!-- Botón padre -->
            <button
              class="accordion-btn {{ $isReportes ? 'active' : '' }}"
              data-bs-toggle="collapse"
              data-bs-target="#menuReportes"
              aria-expanded="{{ $isReportes ? 'true' : 'false' }}"
              aria-controls="menuReportes">
              <i class="bi bi-bar-chart-line"></i>
              <span>Reportes</span>
              <i class="bi bi-chevron-down chev"></i>
            </button>

            <!-- Submenú -->
            <div id="menuReportes" class="collapse {{ $isReportes ? 'show' : '' }}">
              <div class="submenu">
                <a href="{{ route('reportes.pagos') }}"
                   class="{{ request()->routeIs('reportes.pagos*') ? 'active' : '' }}">
                  <i class="bi bi-cash-coin"></i><span>Reporte de Pagos</span>
                </a>
                <a href="{{ route('reportes.cna') }}"
                   class="{{ request()->routeIs('reportes.cna*') ? 'active' : '' }}">
                  <i class="bi bi-chat-dots"></i><span>Reporte de Cna</span>
                </a>
                <a href="{{ route('reportes.pdp') }}"
                   class="{{ request()->routeIs('reportes.pdp*') ? 'active' : '' }}">
                  <i class="bi bi-flag"></i><span>Reporte de Promesas</span>
                </a>
              </div>
            </div>
          @endif

          {{-- APROBACIONES (admin y supervisor) --}}
          @if(in_array($role, ['supervisor','administrador']))
            <div class="lab">APROBACIONES</div>
            <a href="{{ route('autorizacion') }}" class="{{ request()->is('autorizacion*') ? 'active' : '' }}">
              <i class="bi bi-check2-square"></i><span>Autorización</span>
            </a>
          @endif

          {{-- ADMIN / SUPERVISIÓN (admin, supervisor, soporte, sistemas) --}}
          @if(in_array($role, ['administrador','supervisor','soporte','sistemas']))
            <div class="lab">ADMIN / SUPERVISIÓN</div>

            <!-- Botón padre Integración -->
            <button
              class="accordion-btn {{ $isIntegracion ? 'active' : '' }}"
              data-bs-toggle="collapse"
              data-bs-target="#menuIntegracion"
              aria-expanded="{{ $isIntegracion ? 'true' : 'false' }}"
              aria-controls="menuIntegracion">
              <i class="bi bi-hdd-network"></i>
              <span>Integración</span>
              <i class="bi bi-chevron-down chev"></i>
            </button>

            <!-- Submenú Integración -->
            <div id="menuIntegracion" class="collapse {{ $isIntegracion ? 'show' : '' }}">
              <div class="submenu">
                <a href="{{ route('integracion.pagos') }}"
                   class="{{ request()->is('integracion/pagos*') ? 'active' : '' }}">
                  <i class="bi bi-upload"></i><span>Subir Pagos</span>
                </a>
                <a href="{{ route('integracion.asignar') }}"
                   class="{{ request()->is('integracion/asignar*') ? 'active' : '' }}">
                  <i class="bi bi-person-check"></i><span>Subir Asignacion</span>
                </a>
                <a href="{{ route('integracion.ccd') }}"
                   class="{{ request()->is('integracion/ccd*') ? 'active' : '' }}">
                  <i class="bi bi-database"></i><span>Subir CCD</span>
                </a>
                <a href="{{ route('integracion.data') }}"
                   class="{{ request()->is('integracion/data*') ? 'active' : '' }}">
                  <i class="bi bi-cloud-upload"></i><span>Subir Data</span>
                </a>
              </div>
            </div>

            <a href="{{ route('administracion') }}" class="{{ request()->routeIs('administracion') ? 'active' : '' }}">
              <i class="bi bi-gear"></i><span>Administración</span>
            </a>
          @endif
        @endauth
      </nav>

      <div class="rail-foot">
        @auth
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-primary w-100">
              <i class="bi bi-box-arrow-right me-1"></i> Salir
            </button>
          </form>
        @endauth

        <div class="small mt-2">© {{ date('Y') }} KP INVEST</div>
      </div>
    </aside>

    <!-- Backdrop móvil -->
    <div id="backdrop" class="backdrop" onclick="toggleRail()"></div>

    <!-- Main -->
    <main class="main">
      <div class="appbar">
        <div class="appbar-in">
          <button class="btn btn-outline-primary d-lg-none" onclick="toggleRail()"><i class="bi bi-list"></i></button>
          <div class="crumb">@yield('crumb','')</div>
        </div>
      </div>

      <div class="content">
        <div class="content-in">
          @yield('content')
          <div class="footer small">© {{ date('Y') }} KP INVEST</div>
        </div>
      </div>
    </main>
  </div>

  <script>
    function toggleRail(){
      document.getElementById('rail').classList.toggle('show');
      document.getElementById('backdrop').classList.toggle('show');
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
