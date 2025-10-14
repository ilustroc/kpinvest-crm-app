@extends('layouts.app')
@section('title','Administración')
@section('crumb','Administración')

@push('head')
<style>
  /* ======= MODO COMPACTO (verde + azul, menos alto y tipografía menor) ======= */
  .admin-compact .card.pad{ padding:12px 14px; border-radius:12px }
  .admin-compact h5{ font-size:1rem; margin-bottom:.7rem }
  .admin-compact .helper{ font-size:.82rem }

  /* inputs / selects / botones más pequeños */
  .admin-compact .form-control,
  .admin-compact .form-select{ font-size:.92rem; padding:.35rem .6rem; height:auto }
  .admin-compact .btn{ --bs-btn-padding-y:.32rem; --bs-btn-padding-x:.6rem; --bs-btn-border-radius:.45rem; font-size:.92rem }
  .admin-compact .btn-sm{ --bs-btn-padding-y:.25rem; --bs-btn-padding-x:.5rem; font-size:.9rem }

  /* chips KPI */
  .admin-compact .chip{ padding:10px 12px; border-radius:12px }
  .admin-compact .chip .t i{ width:28px; height:28px; border-radius:8px; font-size:.95rem }
  .admin-compact .chip .s{ font-size:.88rem }

  /* tabla estructura (menos alto, sticky header, cebreado sutil) */
  .admin-compact .struct .table> :not(caption)>*>*{ padding:.55rem .65rem }
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

  /* mini tarjetas de cada asesor, más chicas */
  .admin-compact .assignee{ padding:.45rem .55rem; border-radius:10px; gap:.45rem }
  .admin-compact .badge-state{ padding:.15rem .5rem; font-weight:700; font-size:.86rem }
  .admin-compact .dot{ width:8px; height:8px }

  /* botones de la tabla coherentes con marca */
  .admin-compact .btn-primary{ background:var(--brand); border-color:var(--brand) }
  .admin-compact .btn-primary:hover{ background:color-mix(in oklab, var(--brand) 85%, black) }
  .admin-compact .btn-outline-primary{ color:var(--brand); border-color:var(--brand) }
  .admin-compact .btn-outline-primary:hover{ color:#fff; background:var(--brand) }
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

    {{-- KPIs rápidos --}}
    <div class="row g-3 mb-1">
      <div class="col-sm-6 col-lg-3">
        <div class="chip">
          <div class="t"><i class="bi bi-person-gear"></i><span>Supervisores</span></div>
          <div class="value fw-bold fs-5">{{ $supervisores->count() }}</div>
          <div class="s">Usuarios con rol de Supervisor</div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="chip">
          <div class="t"><i class="bi bi-people"></i><span>Asesores</span></div>
          <div class="value fw-bold fs-5">{{ $supervisores->sum('asesores_count') }}</div>
          <div class="s">Total de asesores registrados</div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      {{-- Crear Supervisor --}}
      <div class="col-lg-6">
        <div class="card pad">
          <h5 class="mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-person-gear" style="color:var(--accent)"></i> <span>Crear Supervisor</span>
          </h5>
          <form method="POST" action="{{ route('administracion.supervisores.store') }}" class="vstack gap-3" autocomplete="off">
            @csrf
            <div>
              <label class="form-label">Nombre</label>
              <input name="name" class="form-control" value="{{ old('name') }}" required placeholder="Ej: Ana Pérez">
              @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
              <label class="form-label">Email</label>
              <input name="email" type="email" class="form-control" value="{{ old('email') }}" required placeholder="supervisor@empresa.com">
              @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
              <label class="form-label">Contraseña</label>
              <input name="password" type="password" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
              <div class="helper">Se enviará al usuario o cámbiala luego desde Administración.</div>
              @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="d-grid d-sm-block">
              <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Crear Supervisor</button>
            </div>
          </form>
        </div>
      </div>

      {{-- Crear Asesor --}}
      <div class="col-lg-6">
        <div class="card pad">
          <h5 class="mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-person-plus" style="color:var(--accent)"></i> <span>Crear Asesor</span>
          </h5>
          <form method="POST" action="{{ route('administracion.asesores.store') }}" class="vstack gap-3" autocomplete="off">
            @csrf
            <div>
              <label class="form-label">Nombre</label>
              <input name="name" class="form-control" value="{{ old('name') }}" required placeholder="Ej: Carlos López">
              @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
              <label class="form-label">Email</label>
              <input name="email" type="email" class="form-control" value="{{ old('email') }}" required placeholder="asesor@empresa.com">
              @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
              <label class="form-label">Contraseña</label>
              <input name="password" type="password" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
              @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
              <label class="form-label">Supervisor</label>
              <select name="supervisor_id" class="form-select" required>
                <option value="">Selecciona…</option>
                @foreach($supervisores as $sup)
                  <option value="{{ $sup->id }}" @selected(old('supervisor_id')==$sup->id)>{{ $sup->name }} — {{ $sup->email }}</option>
                @endforeach
              </select>
              @error('supervisor_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="d-grid d-sm-block">
              <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Crear Asesor</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Estructura: Supervisores y Asesores --}}
    <div class="card pad mt-3 struct">
      <h5 class="mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-diagram-3" style="color:var(--accent)"></i> <span>Estructura</span>
      </h5>

      @if($supervisores->isEmpty())
        <div class="text-secondary">Aún no hay supervisores creados.</div>
      @else
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Supervisor</th>
                <th>Email</th>
                <th>Estado</th>
                <th class="text-center"># Asesores</th>
                <th>Asesores (reasignables)</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach($supervisores as $sup)
                <tr>
                  <td class="fw-semibold">{{ $sup->name }}</td>
                  <td class="text-secondary">{{ $sup->email }}</td>
                  <td>
                    <span class="badge-state {{ $sup->is_active ? 'on' : 'off' }}">
                      <span class="dot {{ $sup->is_active ? 'on' : 'off' }}"></span>
                      {{ $sup->is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>
                  <td class="text-center">
                    <span class="badge" style="border-radius:999px; background:color-mix(in oklab, var(--accent) 12%, transparent); color:var(--accent); border:1px solid color-mix(in oklab, var(--accent) 28%, transparent)">
                      {{ $sup->asesores_count }}
                    </span>
                  </td>
                  <td>
                    @if($sup->asesores->isEmpty())
                      <span class="text-secondary">—</span>
                    @else
                      <div class="vstack gap-2">
                        @foreach($sup->asesores as $asesor)
                          <div class="assignee">
                            <i class="bi bi-person-badge"></i>
                            <span class="me-2">
                              {{ $asesor->name }}
                              <span class="text-secondary">({{ $asesor->email }})</span>
                              <span class="badge-state {{ $asesor->is_active ? 'on' : 'off' }} ms-1">
                                <span class="dot {{ $asesor->is_active ? 'on' : 'off' }}"></span>
                                {{ $asesor->is_active ? 'Activo' : 'Inactivo' }}
                              </span>
                            </span>

                            {{-- Reasignar --}}
                            <form method="POST" action="{{ route('administracion.asesores.reassign', $asesor->id) }}" class="d-flex gap-2 ms-auto">
                              @csrf @method('PATCH')
                              <select name="supervisor_id" class="form-select form-select-sm" style="width:auto; min-width: 200px">
                                @foreach($todosSupervisores as $sid => $sname)
                                  <option value="{{ $sid }}" @selected($asesor->supervisor_id == $sid)>{{ $sname }}</option>
                                @endforeach
                              </select>
                              <button class="btn btn-sm btn-outline-primary" title="Reasignar" onclick="return confirm('¿Reasignar a este asesor?')">
                                <i class="bi bi-arrow-repeat"></i><span class="d-none d-md-inline ms-1">Reasignar</span>
                              </button>
                            </form>

                            {{-- Activar/Desactivar asesor --}}
                            <form method="POST" action="{{ route('administracion.usuarios.toggle', $asesor) }}" onsubmit="return confirm('¿Seguro?')">
                              @csrf @method('PATCH')
                              <button class="btn btn-sm {{ $asesor->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                <i class="bi {{ $asesor->is_active ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                <span class="d-none d-md-inline ms-1">{{ $asesor->is_active ? 'Desactivar' : 'Activar' }}</span>
                              </button>
                            </form>

                            {{-- Cambiar password asesor (modal) --}}
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#pw-usr-{{ $asesor->id }}">
                              <i class="bi bi-key"></i><span class="d-none d-md-inline ms-1">Contraseña</span>
                            </button>
                          </div>

                          {{-- Modal password asesor --}}
                          <div class="modal fade" id="pw-usr-{{ $asesor->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                              <form method="POST" action="{{ route('administracion.usuarios.password', $asesor) }}" class="modal-content">
                                @csrf @method('PATCH')
                                <div class="modal-header">
                                  <h6 class="modal-title"><i class="bi bi-key me-1"></i> Cambiar contraseña — {{ $asesor->name }}</h6>
                                  <button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                  <div class="mb-2">
                                    <label class="form-label">Nueva contraseña</label>
                                    <input type="password" name="password" class="form-control" minlength="6" required>
                                  </div>
                                  <div>
                                    <label class="form-label">Confirmar contraseña</label>
                                    <input type="password" name="password_confirmation" class="form-control" minlength="6" required>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                                  <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        @endforeach
                      </div>
                    @endif
                  </td>

                  {{-- Acciones supervisor --}}
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <form method="POST" action="{{ route('administracion.usuarios.toggle', $sup) }}" onsubmit="return confirm('¿Seguro?')">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm {{ $sup->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                          <i class="bi {{ $sup->is_active ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                          <span class="d-none d-md-inline ms-1">{{ $sup->is_active ? 'Desactivar' : 'Activar' }}</span>
                        </button>
                      </form>

                      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#pw-usr-{{ $sup->id }}">
                        <i class="bi bi-key"></i><span class="d-none d-md-inline ms-1">Contraseña</span>
                      </button>
                    </div>

                    {{-- Modal password supervisor --}}
                    <div class="modal fade" id="pw-usr-{{ $sup->id }}" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog">
                        <form method="POST" action="{{ route('administracion.usuarios.password', $sup) }}" class="modal-content">
                          @csrf @method('PATCH')
                          <div class="modal-header">
                            <h6 class="modal-title"><i class="bi bi-key me-1"></i> Cambiar contraseña — {{ $sup->name }}</h6>
                            <button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                          </div>
                          <div class="modal-body">
                            <div class="mb-2">
                              <label class="form-label">Nueva contraseña</label>
                              <input type="password" name="password" class="form-control" minlength="6" required>
                            </div>
                            <div>
                              <label class="form-label">Confirmar contraseña</label>
                              <input type="password" name="password_confirmation" class="form-control" minlength="6" required>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
@push('scripts')
<script>
/* ====== Auto-poner botón para ver/ocultar contraseña en todos los campos ====== */
(function(){
  function enhancePwdInputs(scope=document){
    const pwds = scope.querySelectorAll('input[type="password"]:not([data-has-toggle])');
    pwds.forEach(inp=>{
      // marca para no procesar dos veces
      inp.setAttribute('data-has-toggle','1');

      // crea input-group pequeño y mueve el input adentro
      const wrap = document.createElement('div');
      wrap.className = 'input-group input-group-sm pwd-group';
      inp.parentNode.insertBefore(wrap, inp);
      wrap.appendChild(inp);

      // botón ojo
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-secondary js-toggle-pwd';
      btn.setAttribute('aria-label','Mostrar contraseña');
      btn.innerHTML = '<i class="bi bi-eye"></i>';
      wrap.appendChild(btn);
    });
  }

  // inicial
  enhancePwdInputs(document);

  // toggle
  document.addEventListener('click', e=>{
    const btn = e.target.closest('.js-toggle-pwd');
    if(!btn) return;
    const inp = btn.parentElement.querySelector('input[type="password"], input[type="text"]');
    if(!inp) return;
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
    inp.focus();
  });

  // si hay modales que se inyectan tarde, observa el DOM
  const mo = new MutationObserver(muts=>{
    muts.forEach(m=>{
      m.addedNodes.forEach(n=>{
        if(n.nodeType===1) enhancePwdInputs(n);
      });
    });
  });
  mo.observe(document.body, {childList:true, subtree:true});
})();
</script>
@endpush
