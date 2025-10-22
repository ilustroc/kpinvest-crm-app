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
  .admin-compact .btn{ --bs-btn-padding-y:.32rem; --bs-btn-padding-x:.6rem; --bs-btn-border-radius:.45rem; font-size:.92rem }
  .admin-compact .btn-sm{ --bs-btn-padding-y:.25rem; --bs-btn-padding-x:.5rem; font-size:.9rem }

  .admin-compact .chip{ padding:10px 12px; border-radius:12px }
  .admin-compact .chip .t i{ width:28px; height:28px; border-radius:8px; font-size:.95rem }
  .admin-compact .chip .s{ font-size:.88rem }

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

  .admin-compact .assignee{ padding:.45rem .55rem; border-radius:10px; gap:.45rem; display:flex; align-items:center; background:#fff; border:1px solid var(--border) }

  /* Icon-only buttons */
  .btn-icon{
    --size: 36px;
    width:var(--size); height:var(--size);
    padding:0; display:inline-flex; align-items:center; justify-content:center;
    border-radius:10px;
  }
  .btn-icon i{ font-size:1.05rem; }
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

    {{-- Botones abrir modales --}}
    <div class="card pad mb-2">
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSupervisor">
          <i class="bi bi-person-gear me-1"></i> Nuevo supervisor
        </button>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAsesor">
          <i class="bi bi-person-plus me-1"></i> Nuevo asesor
        </button>
      </div>
    </div>

    {{-- Estructura --}}
    <div class="card pad struct">
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
                  <td class="text-center">
                    <span class="badge"
                      style="border-radius:999px; background:color-mix(in oklab, var(--accent) 12%, transparent); color:var(--accent); border:1px solid color-mix(in oklab, var(--accent) 28%, transparent)">
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
                            </span>

                            <div class="ms-auto d-inline-flex gap-2">
                              {{-- Reasignar (modal) --}}
                              <button
                                class="btn btn-outline-primary btn-icon"
                                data-bs-toggle="tooltip" title="Reasignar"
                                data-bs-target="#reassign-{{ $asesor->id }}" data-bs-toggle-second="modal"
                                onclick="document.getElementById('reassign-{{ $asesor->id }}-open').click(); return false;">
                                <i class="bi bi-arrow-left-right"></i>
                              </button>
                              <button id="reassign-{{ $asesor->id }}-open" type="button" class="d-none" data-bs-toggle="modal" data-bs-target="#reassign-{{ $asesor->id }}"></button>

                              {{-- Password --}}
                              <button class="btn btn-outline-secondary btn-icon" data-bs-toggle="tooltip" title="Contraseña"
                                      data-bs-target="#pw-usr-{{ $asesor->id }}" data-bs-toggle-second="modal"
                                      onclick="document.getElementById('pw-open-{{ $asesor->id }}').click(); return false;">
                                <i class="bi bi-key"></i>
                              </button>
                              <button id="pw-open-{{ $asesor->id }}" type="button" class="d-none" data-bs-toggle="modal" data-bs-target="#pw-usr-{{ $asesor->id }}"></button>
                            </div>
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

                          {{-- Modal reasignar asesor --}}
                          <div class="modal fade" id="reassign-{{ $asesor->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                              <form method="POST" action="{{ route('administracion.asesores.reassign', $asesor->id) }}" class="modal-content">
                                @csrf @method('PATCH')
                                <div class="modal-header">
                                  <h6 class="modal-title"><i class="bi bi-arrow-left-right me-1"></i> Reasignar — {{ $asesor->name }}</h6>
                                  <button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                  <label class="form-label">Supervisor destino</label>
                                  <select name="supervisor_id" class="form-select" required>
                                    @foreach($todosSupervisores as $sid => $sname)
                                      <option value="{{ $sid }}" @selected($asesor->supervisor_id == $sid)>{{ $sname }}</option>
                                    @endforeach
                                  </select>
                                </div>
                                <div class="modal-footer">
                                  <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                                  <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Reasignar</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        @endforeach
                      </div>
                    @endif
                  </td>

                  {{-- Acciones supervisor (iconos) --}}
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <button class="btn btn-outline-secondary btn-icon" data-bs-toggle="tooltip" title="Contraseña"
                              data-bs-target="#pw-usr-{{ $sup->id }}" data-bs-toggle-second="modal"
                              onclick="document.getElementById('pw-sup-open-{{ $sup->id }}').click(); return false;">
                        <i class="bi bi-key"></i>
                      </button>
                      <button id="pw-sup-open-{{ $sup->id }}" type="button" class="d-none" data-bs-toggle="modal" data-bs-target="#pw-usr-{{ $sup->id }}"></button>
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

  {{-- Modal: Crear supervisor --}}
  <div class="modal fade" id="modalSupervisor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="POST" action="{{ route('administracion.supervisores.store') }}" class="modal-content" autocomplete="off">
        @csrf
        <div class="modal-header">
          <h6 class="modal-title"><i class="bi bi-person-gear me-1"></i> Crear supervisor</h6>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body vstack gap-3">
          <div>
            <label class="form-label">Nombre</label>
            <input name="name" class="form-control" required placeholder="Ej: Ana Pérez">
          </div>
          <div>
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required placeholder="supervisor@empresa.com">
          </div>
          <div>
            <label class="form-label">Contraseña</label>
            <input name="password" type="password" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
            <div class="helper small text-secondary mt-1">Se enviará al usuario o puedes cambiarla luego.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Crear</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal: Crear asesor --}}
  <div class="modal fade" id="modalAsesor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="POST" action="{{ route('administracion.asesores.store') }}" class="modal-content" autocomplete="off">
        @csrf
        <div class="modal-header">
          <h6 class="modal-title"><i class="bi bi-person-plus me-1"></i> Crear asesor</h6>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body vstack gap-3">
          <div>
            <label class="form-label">Nombre</label>
            <input name="name" class="form-control" required placeholder="Ej: Carlos López">
          </div>
          <div>
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required placeholder="asesor@empresa.com">
          </div>
          <div>
            <label class="form-label">Contraseña</label>
            <input name="password" type="password" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
          </div>
          <div>
            <label class="form-label">Supervisor</label>
            <select name="supervisor_id" class="form-select" required>
              <option value="">Selecciona…</option>
              @foreach($supervisores as $sup)
                <option value="{{ $sup->id }}">{{ $sup->name }} — {{ $sup->email }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Crear</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  // Tooltips (iconos)
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

  // Ojo/ocultar en inputs password (modales)
  document.addEventListener('shown.bs.modal', (e)=>{
    e.target.querySelectorAll('input[type="password"]').forEach(inp=>{
      if (inp.dataset.hasToggle) return;
      inp.dataset.hasToggle = '1';
      const group = document.createElement('div');
      group.className = 'input-group';
      inp.parentNode.insertBefore(group, inp);
      group.appendChild(inp);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-secondary';
      btn.innerHTML = '<i class="bi bi-eye"></i>';
      btn.addEventListener('click', ()=>{
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
      });
      group.appendChild(btn);
    });
  });
</script>
@endpush
