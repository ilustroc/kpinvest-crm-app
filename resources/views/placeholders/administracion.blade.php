@extends('layouts.app')
@section('tailwind_only', true)
@section('title', 'Administración')
@section('crumb', 'Administración')

@section('content')
<x-layout.page-shell data-admin-users-page>
  @if(session('ok'))
    <x-ui.alert variant="success">{{ session('ok') }}</x-ui.alert>
  @endif

  @if($errors->any())
    <x-ui.alert variant="danger">{{ $errors->first() }}</x-ui.alert>
  @endif

  <x-layout.page-header
    title="Administración"
    subtitle="Consulta y administra las cuentas, roles y estados de acceso del equipo."
  >
    <x-slot:actions>
      @if(count($vm->roleOptions()) > 0)
        <x-ui.button type="button" data-modal-open="#modalCreateUser" class="w-full sm:w-auto">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Nuevo usuario
        </x-ui.button>
      @endif
    </x-slot:actions>
  </x-layout.page-header>

  <section class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]" aria-labelledby="admin-users-heading">
    <div class="border-b border-kp-border px-4 py-4 sm:px-5">
      <form method="GET"
            action="{{ route('administracion.index') }}"
            role="search"
            aria-label="Buscar usuarios"
            class="flex min-w-0 flex-col gap-3 xl:flex-row xl:items-center">
        <div class="relative min-w-0 flex-1">
          <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" />
          </svg>
          <input
            type="search"
            name="q"
            value="{{ $vm->search() }}"
            placeholder="Buscar por nombre o correo..."
            aria-label="Buscar usuarios por nombre o correo"
            autocomplete="off"
            data-admin-search
            class="h-10 w-full rounded-lg border border-kp-border bg-white pl-9 pr-3 text-[13px] text-kp-ink shadow-[0_1px_1px_rgba(16,24,40,0.02)] transition placeholder:text-slate-400 hover:border-slate-300 kp-focus"
          >
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
          <label class="inline-flex h-10 w-full cursor-pointer items-center gap-2 rounded-lg border border-kp-border bg-white px-3 text-[12px] font-semibold text-slate-600 transition hover:border-slate-300 kp-focus sm:w-auto">
            <input id="swInactivos"
                   type="checkbox"
                   name="inactivos"
                   value="1"
                   @checked($vm->showInactive())
                   class="size-4 rounded border-kp-border accent-kp-primary kp-focus">
            Mostrar inactivos
          </label>

          <div class="flex w-full items-center gap-2 sm:w-auto">
            <x-ui.button type="submit" variant="secondary" class="flex-1 sm:flex-none">
              <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" />
              </svg>
              Buscar
            </x-ui.button>

            <x-ui.button href="{{ route('administracion.index') }}" variant="ghost" class="flex-1 sm:flex-none">Limpiar</x-ui.button>
          </div>
        </div>
      </form>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-kp-border px-4 py-3 sm:px-5">
      <div class="flex min-w-0 items-center gap-2.5">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink" aria-hidden="true">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
          </svg>
        </span>
        <div class="min-w-0">
          <h2 id="admin-users-heading" class="text-[15px] font-semibold text-kp-ink">Usuarios</h2>
          <p class="mt-0.5 text-[11px] text-kp-muted">Cuentas con acceso al CRM.</p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2 text-[11px] text-kp-muted">
        <span class="inline-flex items-center rounded-md border border-kp-border bg-kp-surface-muted px-2 py-1 font-semibold text-slate-600">
          {{ number_format($vm->users->total()) }} {{ $vm->users->total() === 1 ? 'usuario' : 'usuarios' }}
        </span>
        <span class="hidden sm:inline">Ordenado por nombre</span>
        <span class="sm:hidden">Desliza →</span>
      </div>
    </div>

    <x-tables.table :flush="true" :contained="true" min-width="min-w-[980px]">
      <thead>
        <tr>
          <x-tables.th>Nombre</x-tables.th>
          <x-tables.th>Email</x-tables.th>
          <x-tables.th align="center">Rol</x-tables.th>
          <x-tables.th>Supervisor</x-tables.th>
          <x-tables.th align="center">Estado</x-tables.th>
          <x-tables.th align="right">Acciones</x-tables.th>
        </tr>
      </thead>
      <tbody class="divide-y divide-kp-border bg-white">
        @forelse($vm->users as $u)
          @php
            $roleKey = strtolower((string) $u->role);
            $roleVariant = match ($roleKey) {
              'administrador' => 'primary',
              'supervisor' => 'info',
              'soporte' => 'warning',
              default => 'neutral',
            };
            $roleLabel = match ($roleKey) {
              'administrador' => 'Administrador',
              'supervisor' => 'Supervisor',
              'asesor' => 'Asesor',
              'soporte' => 'Soporte',
              default => $roleKey !== '' ? ucfirst($roleKey) : 'Sin rol',
            };
            $hasActions = $vm->canManage($u) || $vm->canUpdatePassword($u);
          @endphp

          <tr data-user-id="{{ $u->id }}"
              data-user-role="{{ $roleKey }}"
              data-user-active="{{ $u->active ? '1' : '0' }}"
              class="group transition-colors hover:bg-slate-50/80">
            <x-tables.td class="min-w-[175px]">
              <div class="truncate font-semibold text-kp-ink" title="{{ $u->name }}">{{ $u->name }}</div>
            </x-tables.td>

            <x-tables.td class="max-w-[260px]">
              <span class="block truncate text-slate-600" title="{{ $u->email }}">{{ $u->email }}</span>
            </x-tables.td>

            <x-tables.td align="center">
              <x-ui.badge :variant="$roleVariant">{{ $roleLabel }}</x-ui.badge>
            </x-tables.td>

            <x-tables.td class="max-w-[190px]">
              @if($u->supervisor)
                <div class="flex items-center gap-1.5 text-slate-600" title="Supervisor: {{ $u->supervisor->name }}">
                  <svg class="size-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                  </svg>
                  <span class="truncate font-medium">{{ $u->supervisor->name }}</span>
                </div>
              @else
                <span class="text-slate-400">—</span>
              @endif
            </x-tables.td>

            <x-tables.td align="center">
              @if($u->active)
                <x-ui.badge variant="success" class="gap-1.5">
                  <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
                  Activo
                </x-ui.badge>
              @else
                <x-ui.badge variant="danger" class="gap-1.5">
                  <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
                  Inactivo
                </x-ui.badge>
              @endif
            </x-tables.td>

            <x-tables.td align="right" class="min-w-[140px]">
              <div class="flex justify-end gap-1.5">
                @if($vm->canManage($u))
                  <x-ui.button type="button" variant="secondary" size="sm" data-modal-open="#edit-usr-{{ $u->id }}" title="Editar usuario" aria-label="Editar usuario">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                  </x-ui.button>
                @endif

                @if($vm->canUpdatePassword($u))
                  <x-ui.button type="button" variant="secondary" size="sm" data-modal-open="#pw-usr-{{ $u->id }}" title="Cambiar password" aria-label="Cambiar password">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                  </x-ui.button>
                @endif

                @if($vm->canManage($u) && $vm->currentUser->id !== $u->id)
                  <form method="POST"
                        action="{{ route('administracion.usuarios.toggle', $u) }}"
                        class="inline-flex"
                        onsubmit="return confirm('Confirmas {{ $u->active ? 'desactivar' : 'activar' }} esta cuenta?')">
                    @csrf
                    @method('PATCH')
                    <x-ui.button type="submit" variant="{{ $u->active ? 'danger' : 'success' }}" size="sm" title="{{ $u->active ? 'Desactivar usuario' : 'Activar usuario' }}" aria-label="{{ $u->active ? 'Desactivar usuario' : 'Activar usuario' }}">
                      @if($u->active)
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                      @else
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                      @endif
                    </x-ui.button>
                  </form>
                @endif

                @if(!$hasActions)
                  <span class="px-2 py-1.5 text-xs text-kp-muted">—</span>
                @endif
              </div>

            </x-tables.td>
          </tr>
        @empty
          <x-tables.empty-row colspan="6">
            <div class="flex flex-col items-center gap-2 py-2">
              <span class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
              </span>
              <span class="text-[13px] font-semibold text-kp-ink">No se encontraron usuarios</span>
              <span class="text-[11px] text-kp-muted">
                {{ $vm->search() !== '' || $vm->showInactive() ? 'Ajusta la búsqueda o limpia los filtros para ver más resultados.' : 'No hay usuarios registrados con los filtros actuales.' }}
              </span>
              @if($vm->search() !== '' || $vm->showInactive())
                <x-ui.button href="{{ route('administracion.index') }}" variant="secondary" size="sm" class="mt-1">
                  Limpiar filtros
                </x-ui.button>
              @endif
            </div>
          </x-tables.empty-row>
        @endforelse
      </tbody>
    </x-tables.table>

    <x-tables.pagination :paginator="$vm->users" />
  </section>

  @foreach($vm->users as $u)
    @if($vm->canManage($u))
      <x-ui.modal id="edit-usr-{{ $u->id }}" title="Editar usuario" subtitle="{{ $u->name }}" max-width="lg">
          <form method="POST"
                action="{{ route('administracion.usuarios.update', $u) }}"
                autocomplete="off"
                data-edit-user-form
                data-current-role="{{ $vm->currentUser->role }}">
            @csrf
            @method('PUT')

            <div class="space-y-4 p-5">
              <x-ui.input label="Nombre" name="name" value="{{ old('name', $u->name) }}" required placeholder="Ej: Carlos Lopez" />
              <x-ui.input label="Email" name="email" type="email" value="{{ old('email', $u->email) }}" required placeholder="usuario@empresa.com" />

              <x-ui.select label="Rol" name="role" required>
                @foreach($vm->roleOptions() as $value => $label)
                  <option value="{{ $value }}" @selected(old('role', $u->role) === $value)>{{ $label }}</option>
                @endforeach
              </x-ui.select>

              @if($vm->currentUser->role === 'supervisor')
                <input type="hidden" name="supervisor_id" value="{{ $vm->currentUser->id }}">
                <div class="hidden" data-supervisor-row></div>
              @else
                <div class="@if(!in_array(old('role', $u->role), ['asesor', 'soporte'], true)) hidden @endif" data-supervisor-row>
                  <x-ui.select label="Supervisor" name="supervisor_id">
                    <option value="">— Sin supervisor —</option>
                    @foreach($vm->supervisorOptions() as $supervisor)
                      @if((int) $supervisor['id'] !== (int) $u->id)
                        <option value="{{ $supervisor['id'] }}" @selected((int) old('supervisor_id', $u->supervisor_id) === (int) $supervisor['id'])>
                          {{ $supervisor['label'] }}
                        </option>
                      @endif
                    @endforeach
                  </x-ui.select>
                </div>
              @endif

              <div class="border-t border-kp-border/60 pt-3" data-password-field>
                <x-ui.input label="Nueva contraseña (opcional)" name="password" type="password" minlength="6" placeholder="Dejar en blanco para mantener la actual" />
                <button type="button" class="mt-1 text-xs font-semibold text-kp-secondary" data-password-toggle>Ver</button>
              </div>
            </div>

            <div class="flex justify-between gap-2 border-t border-kp-border px-5 py-4">
              <x-ui.button type="button" variant="secondary" data-modal-close>Cancelar</x-ui.button>
              <x-ui.button type="submit">Guardar cambios</x-ui.button>
            </div>
          </form>
      </x-ui.modal>
    @endif

    @if($vm->canUpdatePassword($u))
      <x-ui.modal id="pw-usr-{{ $u->id }}" title="Cambiar password" subtitle="{{ $u->name }}">
          <form method="POST" action="{{ route('administracion.usuarios.password', $u) }}">
            @csrf
            @method('PATCH')

            <div class="space-y-4 p-5">
              <div data-password-field>
                <x-ui.input label="Nuevo password" name="password" type="password" minlength="6" required />
                <button type="button" class="mt-1 text-xs font-semibold text-kp-secondary" data-password-toggle>Ver</button>
              </div>

              <div data-password-field>
                <x-ui.input label="Confirmar password" name="password_confirmation" type="password" minlength="6" required />
                <button type="button" class="mt-1 text-xs font-semibold text-kp-secondary" data-password-toggle>Ver</button>
              </div>
            </div>

            <div class="flex justify-between gap-2 border-t border-kp-border px-5 py-4">
              <x-ui.button type="button" variant="secondary" data-modal-close>Cancelar</x-ui.button>
              <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
          </form>
      </x-ui.modal>
    @endif
  @endforeach

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
            <button type="button" class="mt-1 text-xs font-semibold text-kp-secondary" data-password-toggle>Ver</button>
          </div>
        </div>

        <div class="flex justify-between gap-2 border-t border-kp-border px-5 py-4">
          <x-ui.button type="button" variant="secondary" data-modal-close>Cancelar</x-ui.button>
          <x-ui.button type="submit">Crear usuario</x-ui.button>
        </div>
      </form>
  </x-ui.modal>
</x-layout.page-shell>
@endsection
