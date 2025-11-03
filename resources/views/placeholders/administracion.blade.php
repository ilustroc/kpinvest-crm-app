@extends('layouts.app')
@section('title','Administración')
@section('crumb','Administración')

@push('head')
<style>
  /* ======= MODO COMPACTO ======= */
  .admin-compact .card.pad{ padding:12px 14px; border-radius:12px }
  .admin-compact h5{ font-size:1rem; margin-bottom:.7rem }
  .admin-compact .helper{ font-size:.82rem }

  .admin-compact .form-control,
  .admin-compact .form-select{ font-size:.92rem; padding:.35rem .6rem; height:auto }
  .admin-compact .btn{
    --bs-btn-padding-y:.32rem; --bs-btn-padding-x:.6rem; --bs-btn-border-radius:.45rem; font-size:.92rem
  }
  .admin-compact .btn-sm{ --bs-btn-padding-y:.25rem; --bs-btn-padding-x:.5rem; font-size:.9rem }

  .admin-compact .struct .table> :not(caption)>*>*{ padding:.50rem .60rem }
  .admin-compact .struct .table thead th{
    font-size:.78rem; letter-spacing:.3px;
    background: color-mix(in oklab, var(--accent) 8%, #fff);
    border-bottom:1px solid var(--border);
    position:sticky; top:0; z-index:1;
    box-shadow:0 3px 8px rgba(15,23,42,.06);
    text-transform:uppercase;
  }
  .admin-compact .struct .table tbody tr:nth-child(odd) td{
    background: color-mix(in oklab, var(--surface-2) 14%, transparent);
  }
  .admin-compact .struct .table tbody tr:hover td{
    background: color-mix(in oklab, var(--brand) 10%, transparent);
  }

  /* ======= BARRA DE FILTROS (fix layout) ======= */
  .filters{
    display:flex; gap:.8rem; align-items:center; justify-content:space-between;
    flex-wrap:wrap; /* permite que en móviles se parta */
  }
  .filters form{
    display:flex; gap:.8rem; align-items:center;
    flex:1 1 620px; min-width:460px; /* evita que los botones bajen */
    margin:0;
  }
  .filters .form-check{ margin:0 }
  .filters .input-icon{
    flex:1 1 320px; min-width:280px; /* search crece y mantiene mínimo */
  }
  .filters .btn{ white-space:nowrap }  /* que los botones no se rompan */
  /* sección derecha (Nuevo usuario) no necesita estilos extra */

  /* ======= EMAIL TRUNCADO ======= */
  .td-email{ max-width: 340px }
  @media (max-width: 1400px){ .td-email{ max-width: 260px } }
  @media (max-width: 1200px){ .td-email{ max-width: 200px } }

  /* ======= CHIPS DE ESTADO (más pequeños) ======= */
  .state-chip{
    display:inline-flex; align-items:center; justify-content:center;
    font-size:.72rem; line-height:1; letter-spacing:.2px;
    padding:.12rem .48rem; border-radius:999px; font-weight:700;
    border:1px solid transparent; user-select:none;
  }
  .state-ok{
    color:#0f5132;
    background: color-mix(in oklab, #198754 14%, transparent);
    border-color: color-mix(in oklab, #198754 30%, transparent);
  }
  .state-off{
    color:#842029;
    background: color-mix(in oklab, #dc3545 12%, transparent);
    border-color: color-mix(in oklab, #dc3545 30%, transparent);
  }

  /* ======= ACCIONES EN UNA SOLA FILA ======= */
  .col-actions{ width:220px; text-align:right; white-space:nowrap }
  .actions-wrap{
    display:flex; gap:.45rem; align-items:center; justify-content:flex-end; flex-wrap:nowrap;
  }
  .actions-wrap form{ display:inline; margin:0 }
  .btn-icon{
    --size:32px; width:var(--size); height:var(--size);
    padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:10px;
  }
  .btn-icon i{ font-size:1rem }

  /* ======= MÓVIL ======= */
  @media (max-width: 768px){
    .filters{ flex-direction:column; align-items:stretch }
    .filters form{ width:100%; min-width:0; flex:1 1 auto }
    .filters .input-icon{ flex:1 1 auto; min-width:0 }
  }
</style>

@endpush

@section('content')
<div class="admin-compact">

  {{-- ALERTAS --}}
  @if(session('ok'))
    <div class="alert alert-success d-flex align-items-center" role="alert">
      <i class="bi bi-check-circle me-2"></i>
      <div>{{ session('ok') }}</div>
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger d-flex align-items-center" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <div>{{ $errors->first() }}</div>
    </div>
  @endif

  {{-- Barra superior: filtro + búsqueda + crear --}}
  <div class="card pad mb-2">
    <div class="filters">
      <form method="GET" action="{{ route('administracion') }}">
        <div class="form-check form-switch m-0">
          <input class="form-check-input" type="checkbox" id="swInactivos" name="inactivos" value="1" {{ request('inactivos') ? 'checked' : '' }}>
          <label class="form-check-label" for="swInactivos">Mostrar inactivos</label>
        </div>

        <div class="input-icon" style="min-width:260px; flex:1">
          <input type="search" class="form-control" name="q" value="{{ request('q') }}" placeholder="Buscar nombre o email…">
        </div>

        <button class="btn btn-outline-primary" type="submit">
          <i class="bi bi-filter-right me-1"></i> Aplicar
        </button>
        <a class="btn btn-outline-primary" href="{{ route('administracion') }}">
          Limpiar
        </a>
      </form>

      <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateUser">
          <i class="bi bi-person-plus me-1"></i> Nuevo usuario
        </button>
      </div>
    </div>
  </div>

  {{-- Tabla de usuarios --}}
  <div class="card pad struct">
    <h5 class="mb-3 d-flex align-items-center gap-2">
      <i class="bi bi-people" style="color:var(--accent)"></i> <span>Usuarios</span>
    </h5>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th class="text-center">Rol</th>
            <th class="text-center">Estado</th>
            <th class="col-actions">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($users as $u)
          @php
            $me = auth()->user();
            $canManage = $me->role === 'administrador'
                      || ($me->role === 'supervisor' && in_array($u->role, ['asesor','soporte']))
                      || ($me->role === 'soporte'    && in_array($u->role, ['usuario']));
          @endphp
          <tr>
            <td class="fw-semibold">{{ $u->name }}</td>
            <td class="text-secondary td-email">
              <div class="text-truncate" title="{{ $u->email }}">{{ $u->email }}</div>
            </td>
            <td class="text-center">
              <span class="badge rounded-pill text-bg-light border">{{ strtoupper($u->role) }}</span>
            </td>
            <td class="text-center">
              @if($u->active)
                <span class="state-chip state-ok">ACTIVO</span>
              @else
                <span class="state-chip state-off">INACTIVO</span>
              @endif
            </td>
            <td class="col-actions">
              <div class="actions-wrap">
                {{-- Cambiar contraseña --}}
                @if($canManage || $me->role === 'administrador' || $me->id === $u->id)
                  <button class="btn btn-outline-secondary btn-icon" data-bs-toggle="modal" data-bs-target="#pw-usr-{{ $u->id }}" title="Contraseña">
                    <i class="bi bi-key"></i>
                  </button>
                @endif

                {{-- Activar / Desactivar --}}
                @if($canManage && $me->id !== $u->id)
                  <form method="POST" action="{{ route('administracion.usuarios.toggle',$u) }}"
                        onsubmit="return confirm('¿Confirmas {{ $u->active ? 'desactivar' : 'activar' }} esta cuenta?')">
                    @csrf @method('PATCH')
                    <button class="btn btn-outline-{{ $u->active ? 'danger' : 'success' }} btn-icon" title="{{ $u->active ? 'Desactivar' : 'Activar' }}">
                      <i class="bi bi-{{ $u->active ? 'person-x' : 'person-check' }}"></i>
                    </button>
                  </form>
                @endif
              </div>

              {{-- Modal password (centrado + labels alineados) --}}
              <div class="modal fade" id="pw-usr-{{ $u->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="{{ route('administracion.usuarios.password', $u) }}" class="modal-content">
                    @csrf @method('PATCH')
                    <div class="modal-header">
                      <h6 class="modal-title w-100 text-center">
                        <i class="bi bi-key me-1"></i> Cambiar contraseña — {{ $u->name }}
                      </h6>
                      <button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                      <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-4 text-md-end">
                          <label class="form-label mb-0">Nueva contraseña</label>
                        </div>
                        <div class="col-12 col-md-8">
                          <div class="input-group">
                            <input type="password" name="password" class="form-control" minlength="6" required>
                            <button class="btn btn-outline-secondary btn-eye" type="button" data-eye-target="password-{{ $u->id }}"><i class="bi bi-eye"></i></button>
                          </div>
                        </div>

                        <div class="col-12 col-md-4 text-md-end">
                          <label class="form-label mb-0">Confirmar contraseña</label>
                        </div>
                        <div class="col-12 col-md-8">
                          <div class="input-group">
                            <input type="password" name="password_confirmation" class="form-control" minlength="6" required>
                            <button class="btn btn-outline-secondary btn-eye" type="button" data-eye-target="confirm-{{ $u->id }}"><i class="bi bi-eye"></i></button>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                      <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                      <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                    </div>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">Sin usuarios.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-2">
      {{ $users->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
  </div>
</div>

{{-- Modal: Crear usuario --}}
@php
  $me = auth()->user();
  $roleOptions = match($me->role){
    'administrador' => ['supervisor'=>'Supervisor','asesor'=>'Asesor','soporte'=>'Soporte','usuario'=>'Usuario'],
    'supervisor'    => ['asesor'=>'Asesor','soporte'=>'Soporte'],
    'soporte'       => ['usuario'=>'Usuario'],
    default         => [],
  };
@endphp
<div class="modal fade" id="modalCreateUser" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="{{ route('administracion.usuarios.store') }}" class="modal-content" autocomplete="off">
      @csrf
      <div class="modal-header">
        <h6 class="modal-title w-100 text-center"><i class="bi bi-person-plus me-1"></i> Crear usuario</h6>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body vstack gap-3">
        <div class="row g-3 align-items-center">
          <div class="col-12 col-md-4 text-md-end"><label class="form-label mb-0">Nombre</label></div>
          <div class="col-12 col-md-8"><input name="name" class="form-control" required placeholder="Ej: Carlos López"></div>

          <div class="col-12 col-md-4 text-md-end"><label class="form-label mb-0">Email</label></div>
          <div class="col-12 col-md-8"><input name="email" type="email" class="form-control" required placeholder="usuario@empresa.com"></div>

          <div class="col-12 col-md-4 text-md-end"><label class="form-label mb-0">Rol</label></div>
          <div class="col-12 col-md-8">
            <select name="role" class="form-select" required>
              <option value="">Selecciona…</option>
              @foreach($roleOptions as $val=>$label)
                <option value="{{ $val }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-4 text-md-end"><label class="form-label mb-0">Contraseña</label></div>
          <div class="col-12 col-md-8">
            <div class="input-group">
              <input name="password" type="password" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
              <button class="btn btn-outline-secondary btn-eye" type="button"><i class="bi bi-eye"></i></button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Crear</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

  // Botón "ojo": alternar password/visible en input-group
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btn-eye');
    if(!btn) return;
    const input = btn.parentElement.querySelector('input');
    if(!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  }, false);
</script>
@endpush
