@extends('layouts.app')
@section('tailwind_only', true)
@section('title','Administracion')
@section('crumb','Administracion')

@section('content')
<div class="space-y-4" data-admin-users-page>
  @if(session('ok'))
    <x-ui.alert variant="success">{{ session('ok') }}</x-ui.alert>
  @endif

  @if($errors->any())
    <x-ui.alert variant="danger">{{ $errors->first() }}</x-ui.alert>
  @endif

  <x-ui.card>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <form method="GET" action="{{ route('administracion.index') }}" class="grid flex-1 gap-3 md:grid-cols-[auto_1fr_auto_auto] md:items-end">
        <label class="flex items-center gap-3 rounded-md border border-kp-border bg-slate-50 px-3 py-2 text-sm font-semibold text-kp-ink">
          <input id="swInactivos"
                 type="checkbox"
                 name="inactivos"
                 value="1"
                 @checked($vm->showInactive())
                 class="size-4 rounded border-kp-border text-kp-green kp-focus">
          Mostrar inactivos
        </label>

        <x-ui.input
          type="search"
          name="q"
          value="{{ $vm->search() }}"
          placeholder="Buscar nombre o email..."
          data-admin-search />

        <x-ui.button type="submit" variant="secondary">Aplicar</x-ui.button>
        <x-ui.button href="{{ route('administracion.index') }}" variant="ghost">Limpiar</x-ui.button>
      </form>

      @if(count($vm->roleOptions()) > 0)
        <x-ui.button type="button" data-modal-open="#modalCreateUser">
          Nuevo usuario
        </x-ui.button>
      @endif
    </div>
  </x-ui.card>

  <x-ui.card title="Usuarios" subtitle="Gestion de usuarios, roles y estado de acceso.">
    <x-ui.table>
      <thead class="bg-slate-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-kp-muted">Nombre</th>
          <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-kp-muted">Email</th>
          <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-kp-muted">Rol</th>
          <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-kp-muted">Supervisor</th>
          <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-kp-muted">Estado</th>
          <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-kp-muted">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-kp-border bg-white">
        @forelse($vm->users as $u)
          <tr class="hover:bg-slate-50/70">
            <td class="px-4 py-3">
              <div class="font-semibold text-kp-ink">{{ $u->name }}</div>
            </td>
            <td class="max-w-[260px] px-4 py-3 text-kp-muted">
              <div class="truncate" title="{{ $u->email }}">{{ $u->email }}</div>
            </td>
            <td class="px-4 py-3 text-center">
              <x-ui.badge>{{ strtoupper($u->role) }}</x-ui.badge>
            </td>
            <td class="px-4 py-3 text-center text-sm text-kp-muted">
              {{ $u->supervisor?->name ?? '-' }}
            </td>
            <td class="px-4 py-3 text-center">
              @if($u->active)
                <x-ui.badge variant="success">Activo</x-ui.badge>
              @else
                <x-ui.badge variant="danger">Inactivo</x-ui.badge>
              @endif
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-2">
                @if($vm->canUpdatePassword($u))
                  <x-ui.button type="button" variant="secondary" size="sm" data-modal-open="#pw-usr-{{ $u->id }}">
                    Password
                  </x-ui.button>
                @endif

                @if($vm->canManage($u) && $vm->currentUser->id !== $u->id)
                  <form method="POST"
                        action="{{ route('administracion.usuarios.toggle', $u) }}"
                        onsubmit="return confirm('Confirmas {{ $u->active ? 'desactivar' : 'activar' }} esta cuenta?')">
                    @csrf
                    @method('PATCH')
                    <x-ui.button type="submit" variant="{{ $u->active ? 'danger' : 'success' }}" size="sm">
                      {{ $u->active ? 'Desactivar' : 'Activar' }}
                    </x-ui.button>
                  </form>
                @endif
              </div>

              @if($vm->canUpdatePassword($u))
                <x-ui.modal id="pw-usr-{{ $u->id }}" title="Cambiar password" subtitle="{{ $u->name }}">
                    <form method="POST" action="{{ route('administracion.usuarios.password', $u) }}">
                      @csrf
                      @method('PATCH')

                      <div class="space-y-4 p-5">
                        <div data-password-field>
                          <x-ui.input label="Nuevo password" name="password" type="password" minlength="6" required />
                          <button type="button" class="mt-1 text-xs font-semibold text-kp-green" data-password-toggle>Ver</button>
                        </div>

                        <div data-password-field>
                          <x-ui.input label="Confirmar password" name="password_confirmation" type="password" minlength="6" required />
                          <button type="button" class="mt-1 text-xs font-semibold text-kp-green" data-password-toggle>Ver</button>
                        </div>
                      </div>

                      <div class="flex justify-between gap-2 border-t border-kp-border px-5 py-4">
                        <x-ui.button type="button" variant="secondary" data-modal-close>Cancelar</x-ui.button>
                        <x-ui.button type="submit">Guardar</x-ui.button>
                      </div>
                    </form>
                </x-ui.modal>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="px-4 py-8 text-center text-sm text-kp-muted">Sin usuarios.</td>
          </tr>
        @endforelse
      </tbody>
    </x-ui.table>

    <div class="mt-4">
      {{ $vm->users->withQueryString()->links() }}
    </div>
  </x-ui.card>
</div>

<x-ui.modal id="modalCreateUser" title="Crear usuario" subtitle="Registra una cuenta nueva para el CRM." max-width="lg">
    <form method="POST"
          action="{{ route('administracion.usuarios.store') }}"
          autocomplete="off"
          data-create-user-form
          data-current-role="{{ $vm->currentUser->role }}">
      @csrf

      <div class="space-y-4 p-5">
        <x-ui.input label="Nombre" name="name" required placeholder="Ej: Carlos Lopez" />
        <x-ui.input label="Email" name="email" type="email" required placeholder="usuario@empresa.com" />

        <x-ui.select label="Rol" name="role" required>
          <option value="">Selecciona...</option>
          @foreach($vm->roleOptions() as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </x-ui.select>

        @if($vm->currentUser->role === 'supervisor')
          <input type="hidden" name="supervisor_id" value="{{ $vm->currentUser->id }}">
          <div class="hidden" data-supervisor-row></div>
        @else
          <div class="hidden" data-supervisor-row>
            <x-ui.select label="Supervisor" name="supervisor_id">
              <option value="">Selecciona...</option>
              @foreach($vm->supervisorOptions() as $supervisor)
                <option value="{{ $supervisor['id'] }}">{{ $supervisor['label'] }}</option>
              @endforeach
            </x-ui.select>
          </div>
        @endif

        <div data-password-field>
          <x-ui.input label="Password" name="password" type="password" required minlength="6" placeholder="Minimo 6 caracteres" />
          <button type="button" class="mt-1 text-xs font-semibold text-kp-green" data-password-toggle>Ver</button>
        </div>
      </div>

      <div class="flex justify-between gap-2 border-t border-kp-border px-5 py-4">
        <x-ui.button type="button" variant="secondary" data-modal-close>Cancelar</x-ui.button>
        <x-ui.button type="submit">Crear usuario</x-ui.button>
      </div>
    </form>
</x-ui.modal>
@endsection
