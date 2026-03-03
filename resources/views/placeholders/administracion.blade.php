@extends('layouts.app')
@section('title','Administración')
@section('crumb','Administración')

@push('head')
  @vite(['resources/css/admin/admin.css'])
@endpush

@section('content')
@php
  $me = auth()->user();

  $meRole = strtolower(trim($me->role ?? 'usuario'));

  $roleOptions = match($meRole){
    'administrador' => ['supervisor'=>'Supervisor','asesor'=>'Asesor','soporte'=>'Soporte','usuario'=>'Usuario'],
    'supervisor'    => ['asesor'=>'Asesor','soporte'=>'Soporte'],
    'soporte'       => ['usuario'=>'Usuario'],
    default         => [],
  };
@endphp

{{-- Boot data SIN JS inline --}}
<div id="adminBoot"
     data-me-role="{{ $meRole }}"
     data-me-id="{{ auth()->id() }}"
     data-supervisores='@json($supervisores->map(fn($s)=>[
        "id"=>$s->id,
        "label"=>$s->name." (".$s->email.")"
     ])->values())'
     data-pw-action-template="{{ route('administracion.usuarios.password', '__ID__') }}">
</div>

<div class="admin-compact space-y-4">

  {{-- ALERTAS --}}
  @if(session('ok'))
    <div class="kp-alert kp-alert-ok">
      <span class="kp-alert-ic">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
          <path d="M9 12l2 2 4-4"></path>
          <path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10Z"></path>
        </svg>
      </span>
      <div class="font-semibold">{{ session('ok') }}</div>
    </div>
  @endif

  @if($errors->any())
    <div class="kp-alert kp-alert-bad">
      <span class="kp-alert-ic">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
          <path d="M12 9v4"></path><path d="M12 17h.01"></path>
          <path d="M10.3 3.6 2.3 17.5A2 2 0 0 0 4 20h16a2 2 0 0 0 1.7-2.5L13.7 3.6a2 2 0 0 0-3.4 0Z"></path>
        </svg>
      </span>
      <div class="font-semibold">{{ $errors->first() }}</div>
    </div>
  @endif

  {{-- FILTROS --}}
  <div class="kp-card">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">

      <form method="GET" action="{{ route('administracion.index') }}"
            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:flex-1">
        {{-- Switch --}}
        <label class="inline-flex items-center gap-3 select-none">
          <input id="swInactivos" type="checkbox" name="inactivos" value="1"
                 class="kp-switch"
                 {{ request('inactivos') ? 'checked' : '' }}>
          <span class="text-sm font-semibold text-slate-700">Mostrar inactivos</span>
        </label>

        {{-- Search --}}
        <div class="relative lg:min-w-[320px] lg:flex-1">
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
              <path d="M21 21l-4.3-4.3"></path>
              <path d="M10.8 18.2a7.4 7.4 0 1 1 0-14.8 7.4 7.4 0 0 1 0 14.8Z"></path>
            </svg>
          </span>
          <input id="adminSearch" type="search" name="q" value="{{ request('q') }}"
                 class="kp-input pl-10"
                 placeholder="Buscar nombre o email…">
        </div>

        <div class="flex items-center gap-2">
          <button class="kp-btn kp-btn-ghost" type="submit">
            <span class="inline-flex items-center gap-2">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                <path d="M3 4h18"></path><path d="M6 8h12"></path><path d="M10 12h4"></path>
              </svg>
              Aplicar
            </span>
          </button>

          <a class="kp-btn kp-btn-ghost" href="{{ route('administracion.index') }}">
            Limpiar
          </a>
        </div>
      </form>

      <div class="lg:pl-3">
        <button type="button" class="kp-btn kp-btn-primary" data-modal-open="createUser">
          <span class="inline-flex items-center gap-2">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
              <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
              <path d="M19 8v6"></path><path d="M22 11h-6"></path>
            </svg>
            Nuevo usuario
          </span>
        </button>
      </div>

    </div>
  </div>

  {{-- TABLA --}}
  <div class="kp-card struct">
    <div class="flex items-center justify-between gap-3 mb-3">
      <h2 class="text-sm sm:text-base font-extrabold tracking-tight text-slate-900 flex items-center gap-2">
        <span class="h-9 w-9 rounded-xl grid place-items-center bg-emerald-500/10 text-emerald-700">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
            <path d="M23 21v-2a4 4 0 0 0-3-3.9"></path>
            <path d="M16 3.1a4 4 0 0 1 0 7.8"></path>
          </svg>
        </span>
        Usuarios
      </h2>
    </div>

    <div class="kp-table-wrap">
      <table class="kp-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th class="text-center">Rol</th>
            <th class="text-center">Supervisor</th>
            <th class="text-center">Estado</th>
            <th class="text-right w-[210px]">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($users as $u)
          @php
            $uRole   = strtolower(trim($u->role ?? ''));
            $isOn    = (bool) $u->active;

            $canManage = $meRole === 'administrador'
                      || ($meRole === 'supervisor' && in_array($uRole, ['asesor','soporte']))
                      || ($meRole === 'soporte'    && in_array($uRole, ['usuario']));
          @endphp
          <tr>
            <td class="font-semibold text-slate-900">{{ $u->name }}</td>

            <td class="text-slate-600">
              <div class="max-w-[340px] truncate" title="{{ $u->email }}">{{ $u->email }}</div>
            </td>

            <td class="text-center">
              <span class="kp-badge">{{ strtoupper($u->role) }}</span>
            </td>

            <td class="text-center">
              @if($u->supervisor)
                <span class="text-slate-600">{{ $u->supervisor->name }}</span>
              @else
                <span class="text-slate-400">—</span>
              @endif
            </td>

            <td class="text-center">
              @if($isOn)
                <span class="kp-chip kp-chip-ok">ACTIVO</span>
              @else
                <span class="kp-chip kp-chip-off">INACTIVO</span>
              @endif
            </td>

            <td class="text-right">
              <div class="flex items-center justify-end gap-2">

                {{-- Cambiar contraseña --}}
                @if($meRole === 'administrador' || $me->id === $u->id || $canManage)
                  <button type="button"
                          class="kp-icon-btn"
                          title="Cambiar contraseña"
                          data-modal-open="pw"
                          data-user-id="{{ $u->id }}"
                          data-user-name="{{ $u->name }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                      <path d="M21 2l-2 2"></path>
                      <path d="M7 14l-4 4v3h3l4-4"></path>
                      <path d="M16 5l3 3"></path>
                      <path d="M14 7l3 3"></path>
                      <path d="M8 13l3 3"></path>
                      <path d="M9 12l7-7"></path>
                    </svg>
                  </button>
                @endif

                {{-- Activar / Desactivar --}}
                @if($canManage && $me->id !== $u->id)
                  <form method="POST"
                        action="{{ route('administracion.usuarios.toggle',$u) }}"
                        data-confirm="¿Confirmas {{ $isOn ? 'desactivar' : 'activar' }} esta cuenta?">
                    @csrf @method('PATCH')

                    <button type="submit"
                            class="kp-icon-btn {{ $isOn ? 'kp-icon-danger' : 'kp-icon-success' }}"
                            title="{{ $isOn ? 'Desactivar' : 'Activar' }}">
                      @if($isOn)
                        {{-- icono desactivar --}}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                          <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                          <path d="M18 8l4 4"></path><path d="M22 8l-4 4"></path>
                        </svg>
                      @else
                        {{-- icono activar --}}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                          <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                          <path d="M16 11l2 2 4-4"></path>
                        </svg>
                      @endif
                    </button>
                  </form>
                @endif

              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="py-6 text-center text-slate-500">
              Sin usuarios.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="pt-3">
      {{ $users->withQueryString()->links() }}
    </div>
  </div>

  {{-- MODAL: CAMBIAR CONTRASEÑA (único, reutilizable) --}}
  <div id="modalPw" class="kp-modal hidden" aria-hidden="true">
    <div class="kp-modal-backdrop" data-modal-close></div>

    <div class="kp-modal-card" role="dialog" aria-modal="true" aria-labelledby="pwTitle">
      <div class="kp-modal-head">
        <div class="font-extrabold text-slate-900" id="pwTitle">Cambiar contraseña</div>
        <button type="button" class="kp-x" data-modal-close aria-label="Cerrar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <form id="pwForm" method="POST" action="">
        @csrf @method('PATCH')

        <div class="kp-modal-body space-y-4">
          <div class="text-sm text-slate-600">
            Usuario: <span class="font-semibold text-slate-900" id="pwUserName">—</span>
          </div>

          <div class="grid gap-3">
            <div>
              <label class="kp-label">Nueva contraseña</label>
              <div class="relative">
                <input id="pw1" type="password" name="password" minlength="6" required class="kp-input pr-12" placeholder="Mínimo 6 caracteres">
                <button type="button" class="kp-eye" data-eye>
                  <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path>
                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path>
                  </svg>
                  <svg data-eye-off class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M3 3l18 18"></path>
                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                    <path d="M9.9 5.1A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.2"></path>
                    <path d="M6.1 6.1C3.3 8.2 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 4.3-1"></path>
                  </svg>
                </button>
              </div>
            </div>

            <div>
              <label class="kp-label">Confirmar contraseña</label>
              <div class="relative">
                <input id="pw2" type="password" name="password_confirmation" minlength="6" required class="kp-input pr-12" placeholder="Repite la contraseña">
                <button type="button" class="kp-eye" data-eye>
                  <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path>
                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path>
                  </svg>
                  <svg data-eye-off class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M3 3l18 18"></path>
                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                    <path d="M9.9 5.1A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.2"></path>
                    <path d="M6.1 6.1C3.3 8.2 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 4.3-1"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="kp-modal-foot">
          <button type="button" class="kp-btn kp-btn-ghost" data-modal-close>Cancelar</button>
          <button type="submit" class="kp-btn kp-btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  {{-- MODAL: CREAR USUARIO --}}
  <div id="modalCreateUser" class="kp-modal hidden" aria-hidden="true">
    <div class="kp-modal-backdrop" data-modal-close></div>

    <div class="kp-modal-card" role="dialog" aria-modal="true" aria-labelledby="cuTitle">
      <div class="kp-modal-head">
        <div class="font-extrabold text-slate-900" id="cuTitle">Crear usuario</div>
        <button type="button" class="kp-x" data-modal-close aria-label="Cerrar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <form id="createUserForm" method="POST" action="{{ route('administracion.usuarios.store') }}" autocomplete="off">
        @csrf

        <div class="kp-modal-body space-y-4">
          <div class="grid gap-3">
            <div>
              <label class="kp-label">Nombre</label>
              <input name="name" class="kp-input" required placeholder="Ej: Carlos López">
            </div>

            <div>
              <label class="kp-label">Email</label>
              <input name="email" type="email" class="kp-input" required placeholder="usuario@empresa.com">
            </div>

            <div>
              <label class="kp-label">Rol</label>
              <select name="role" class="kp-input" required id="createRole">
                <option value="">Selecciona...</option>
                @foreach($roleOptions as $val=>$label)
                  <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>

            {{-- Supervisor (dinámico) --}}
            <div id="createSupervisorRow" class="hidden">
              <label class="kp-label">Supervisor</label>

              <select name="supervisor_id" class="kp-input" id="createSupervisor">
                <option value="">Selecciona...</option>

                @forelse($supervisores as $s)
                  <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->email }})</option>
                @empty
                  <option value="" disabled>No hay supervisores activos</option>
                @endforelse
              </select>
            </div>

            <div>
              <label class="kp-label">Contraseña</label>
              <div class="relative">
                <input name="password" type="password" class="kp-input pr-12" required minlength="6" placeholder="Mínimo 6 caracteres">
                <button type="button" class="kp-eye" data-eye>
                  <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path>
                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path>
                  </svg>
                  <svg data-eye-off class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M3 3l18 18"></path>
                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                    <path d="M9.9 5.1A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.2"></path>
                    <path d="M6.1 6.1C3.3 8.2 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 4.3-1"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="kp-modal-foot">
          <button type="button" class="kp-btn kp-btn-ghost" data-modal-close>Cancelar</button>
          <button type="submit" class="kp-btn kp-btn-primary">Crear</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/admin.js'])
@endpush