{{-- resources/views/panel/resumen.blade.php --}}
@extends('layouts.app')

@section('title', 'Panel')
@section('crumb', 'Resumen')
@section('tailwind_only', true)

@section('content')
@php
  $role = $role ?? strtolower(auth()->user()->role ?? '');
  $isAsesor = $isAsesor ?? ($role === 'asesor');
  $isSupervisor = $isSupervisor ?? ($role === 'supervisor');
  $isAdmin = $isAdmin ?? ($role === 'administrador');

  $misSup = $misSup ?? collect();
  $misPre = $misPre ?? collect();
  $misRes = $misRes ?? collect();
  $cnaSup = $cnaSup ?? collect();
  $cnaPre = $cnaPre ?? collect();
  $cnaRes = $cnaRes ?? collect();

  $curr = \Carbon\Carbon::createFromFormat('Y-m', $mes);
  $chartJson = [
      'labels' => $chartLabels,
      'data' => $chartData,
  ];

  $activityItemClass = 'group flex items-center gap-3 rounded-md border border-transparent px-3 py-2.5 text-sm text-kp-ink transition hover:border-kp-border hover:bg-slate-50';
@endphp

<x-layout.page-shell data-module="panel-resumen">
  <script id="panel-chart-json" type="application/json">{!! json_encode($chartJson, JSON_UNESCAPED_SLASHES) !!}</script>

  <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_390px]">
    <div class="space-y-4">
      <x-ui.card>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h1 class="text-xl font-bold text-kp-ink">Bienvenido(a)</h1>
            <p class="mt-1 text-sm text-kp-muted">Panel inicial del CRM.</p>

            @if (session('quick_error'))
              <x-feedback.alert variant="warning" class="mt-3">
                {{ session('quick_error') }}
              </x-feedback.alert>
            @endif
          </div>

          <form
            id="frmQuick"
            class="relative flex w-full flex-col gap-2 sm:flex-row lg:max-w-xl"
            role="search"
            action="{{ route('clientes.quick') }}"
            method="GET"
            autocomplete="off"
            data-quick-form
            data-suggest-url="{{ route('clientes.suggest') }}"
          >
            <label for="inpQuick" class="sr-only">Buscar cliente</label>
            <input
              id="inpQuick"
              name="q"
              class="w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm kp-focus placeholder:text-kp-muted"
              placeholder="DNI / Operacion / Nombre"
              aria-label="Buscar por DNI, Operacion o Nombre"
              data-quick-input
            >
            <x-ui.button type="submit" class="sm:w-auto">
              Buscar
            </x-ui.button>

            <div
              id="quickSug"
              class="absolute left-0 top-full z-40 mt-2 hidden max-h-80 w-full overflow-auto rounded-lg border border-kp-border bg-white p-1 shadow-xl shadow-slate-950/10"
              data-quick-suggestions
            ></div>
          </form>
        </div>
      </x-ui.card>

      @if (session('quick_list'))
        <x-ui.card title="Coincidencias" subtitle="Selecciona el cliente correcto para abrir su ficha.">
          <x-tables.table>
            <thead>
              <tr>
                <x-tables.th>DNI</x-tables.th>
                <x-tables.th>Nombre</x-tables.th>
                <x-tables.th>Operacion</x-tables.th>
                <x-tables.th>Cosecha</x-tables.th>
                <x-tables.th align="right">Accion</x-tables.th>
              </tr>
            </thead>
            <tbody>
              @foreach(session('quick_list') as $r)
                @php
                  $dni = data_get($r, 'dni');
                  $nombre = data_get($r, 'nombre');
                  $operacion = data_get($r, 'operacion');
                  $cosecha = data_get($r, 'cosecha');
                @endphp
                <tr>
                  <x-tables.td>{{ $dni }}</x-tables.td>
                  <x-tables.td class="min-w-56">{{ $nombre }}</x-tables.td>
                  <x-tables.td>{{ $operacion }}</x-tables.td>
                  <x-tables.td>{{ $cosecha }}</x-tables.td>
                  <x-tables.td align="right">
                    <x-ui.button href="{{ route('clientes.show', $dni) }}" variant="secondary" size="sm">
                      Ver
                    </x-ui.button>
                  </x-tables.td>
                </tr>
              @endforeach
            </tbody>
          </x-tables.table>
        </x-ui.card>
      @endif

      <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.card>
          <div class="flex items-center gap-3">
            <div class="flex size-12 items-center justify-center rounded-full bg-kp-green-soft text-sm font-black text-kp-green-dark">
              PP
            </div>
            <div>
              <p class="text-sm font-semibold text-kp-muted">Promesas creadas hoy</p>
              <p class="mt-1 text-2xl font-black text-kp-ink">{{ number_format($kpiPromHoy) }}</p>
            </div>
          </div>
        </x-ui.card>

        <x-ui.card>
          <div class="flex items-center gap-3">
            <div class="flex size-12 items-center justify-center rounded-full bg-kp-green-soft text-sm font-black text-kp-green-dark">
              S/
            </div>
            <div>
              <p class="text-sm font-semibold text-kp-muted">Pagos registrados hoy</p>
              <p class="mt-1 text-2xl font-black text-kp-ink">S/ {{ number_format($kpiPagosHoy, 2) }}</p>
            </div>
          </div>
        </x-ui.card>
      </div>

      <x-ui.card>
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-base font-bold text-kp-ink">Pagos del mes</h2>
            <p class="mt-1 text-sm text-kp-muted">Evolucion diaria de pagos registrados.</p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <x-ui.button
              href="{{ url()->current().'?mes='.$curr->copy()->subMonth()->format('Y-m') }}"
              variant="secondary"
              size="sm"
              aria-label="Mes anterior"
            >
              &lt;
            </x-ui.button>

            <label for="mesPicker" class="sr-only">Mes</label>
            <input
              type="month"
              id="mesPicker"
              value="{{ $curr->format('Y-m') }}"
              class="rounded-md border border-kp-border bg-white px-3 py-1.5 text-sm font-semibold text-kp-ink shadow-sm kp-focus"
              data-month-picker
            >

            <x-ui.button
              href="{{ url()->current().'?mes='.$curr->copy()->addMonth()->format('Y-m') }}"
              variant="secondary"
              size="sm"
              aria-label="Mes siguiente"
            >
              &gt;
            </x-ui.button>
          </div>
        </div>

        <div class="h-72">
          <canvas id="chartPagos" aria-label="Pagos del mes" role="img"></canvas>
        </div>
      </x-ui.card>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-4 xl:self-start">
      <x-ui.card>
        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <h2 class="text-base font-bold text-kp-ink">{{ $isAsesor ? 'Tus actividades' : 'Actividades' }}</h2>
            <p class="mt-1 text-sm text-kp-muted">Pendientes y ultimos movimientos.</p>
          </div>
          <x-ui.badge>{{ strtoupper($role ?: 'ROL') }}</x-ui.badge>
        </div>

        <div class="max-h-[70vh] space-y-5 overflow-auto pr-1">
          @if($isAsesor)
            <section class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-kp-green-soft text-xs font-black text-kp-green-dark">PP</div>
                <h3 class="text-sm font-bold text-kp-ink">Promesas - En Supervisor</h3>
                <x-ui.badge class="ml-auto">{{ $misSup->count() }}</x-ui.badge>
              </div>

              <div class="space-y-1">
                @forelse($misSup as $p)
                  <a href="{{ route('clientes.show', $p->dni) }}" class="{{ $activityItemClass }}">
                    <span class="size-2 rounded-full bg-kp-green"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate font-bold">{{ $p->dni }} - {{ $p->tipo === 'cancelacion' ? 'Cancelacion' : 'Convenio' }}</span>
                      <span class="block truncate text-xs text-kp-muted">{{ $p->operacion ?: '-' }} - Pendiente de Supervisor</span>
                    </span>
                    <span class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">Ver</span>
                  </a>
                @empty
                  <p class="px-3 py-2 text-sm text-kp-muted">Sin pendientes con Supervisor.</p>
                @endforelse
              </div>
            </section>

            <section class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-amber-50 text-xs font-black text-amber-700">PP</div>
                <h3 class="text-sm font-bold text-kp-ink">Promesas - Pre-aprobadas</h3>
                <x-ui.badge class="ml-auto">{{ $misPre->count() }}</x-ui.badge>
              </div>

              <div class="space-y-1">
                @forelse($misPre as $p)
                  <a href="{{ route('clientes.show', $p->dni) }}" class="{{ $activityItemClass }}">
                    <span class="size-2 rounded-full bg-amber-500"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate font-bold">{{ $p->dni }} - {{ $p->tipo === 'cancelacion' ? 'Cancelacion' : 'Convenio' }}</span>
                      <span class="block truncate text-xs text-kp-muted">{{ $p->operacion ?: '-' }} - Esperando Administracion</span>
                    </span>
                    <span class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">Ver</span>
                  </a>
                @empty
                  <p class="px-3 py-2 text-sm text-kp-muted">No hay pre-aprobadas.</p>
                @endforelse
              </div>
            </section>

            <section class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-slate-100 text-xs font-black text-slate-700">PP</div>
                <h3 class="text-sm font-bold text-kp-ink">Promesas - Resueltas</h3>
                <x-ui.badge class="ml-auto">{{ $misRes->count() }}</x-ui.badge>
              </div>

              <div class="space-y-1">
                @forelse($misRes as $p)
                  @php
                    $estado = strtoupper($p->workflow_estado);
                    $dotClass = in_array($estado, ['RECHAZADA', 'RECHAZADA_SUP'], true) ? 'bg-red-500' : 'bg-emerald-500';
                  @endphp
                  <a href="{{ route('clientes.show', $p->dni) }}" class="{{ $activityItemClass }}">
                    <span class="size-2 rounded-full {{ $dotClass }}"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate font-bold">{{ $p->dni }} - {{ ucfirst($p->workflow_estado) }}</span>
                      <span class="block truncate text-xs text-kp-muted">{{ $p->operacion ?: '-' }}</span>
                    </span>
                    <span class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">Ver</span>
                  </a>
                @empty
                  <p class="px-3 py-2 text-sm text-kp-muted">Aun no hay resoluciones.</p>
                @endforelse
              </div>
            </section>

            <section class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-kp-green-soft text-xs font-black text-kp-green-dark">CNA</div>
                <h3 class="text-sm font-bold text-kp-ink">CNA - En Supervisor</h3>
                <x-ui.badge class="ml-auto">{{ $cnaSup->count() }}</x-ui.badge>
              </div>

              <div class="space-y-1">
                @forelse($cnaSup as $c)
                  <a href="{{ route('clientes.show', $c->dni) }}" class="{{ $activityItemClass }}">
                    <span class="size-2 rounded-full bg-kp-green"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate font-bold">DNI {{ $c->dni }}</span>
                      <span class="block truncate text-xs text-kp-muted">Pendiente de Supervisor</span>
                    </span>
                    <span class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">Ver</span>
                  </a>
                @empty
                  <p class="px-3 py-2 text-sm text-kp-muted">Sin CNA en supervisor.</p>
                @endforelse
              </div>
            </section>

            <section class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-amber-50 text-xs font-black text-amber-700">CNA</div>
                <h3 class="text-sm font-bold text-kp-ink">CNA - Pre-aprobadas</h3>
                <x-ui.badge class="ml-auto">{{ $cnaPre->count() }}</x-ui.badge>
              </div>

              <div class="space-y-1">
                @forelse($cnaPre as $c)
                  <a href="{{ route('clientes.show', $c->dni) }}" class="{{ $activityItemClass }}">
                    <span class="size-2 rounded-full bg-amber-500"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate font-bold">DNI {{ $c->dni }}</span>
                      <span class="block truncate text-xs text-kp-muted">Esperando Administracion</span>
                    </span>
                    <span class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">Ver</span>
                  </a>
                @empty
                  <p class="px-3 py-2 text-sm text-kp-muted">Sin CNA pre-aprobadas.</p>
                @endforelse
              </div>
            </section>

            <section class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-slate-100 text-xs font-black text-slate-700">CNA</div>
                <h3 class="text-sm font-bold text-kp-ink">CNA - Resueltas</h3>
                <x-ui.badge class="ml-auto">{{ $cnaRes->count() }}</x-ui.badge>
              </div>

              <div class="space-y-1">
                @forelse($cnaRes as $c)
                  @php
                    $estado = strtoupper($c->workflow_estado);
                    $dotClass = in_array($estado, ['RECHAZADA', 'RECHAZADA_SUP'], true) ? 'bg-red-500' : 'bg-emerald-500';
                  @endphp
                  <a href="{{ route('clientes.show', $c->dni) }}" class="{{ $activityItemClass }}">
                    <span class="size-2 rounded-full {{ $dotClass }}"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate font-bold">DNI {{ $c->dni }} - {{ ucfirst($c->workflow_estado) }}</span>
                      <span class="block truncate text-xs text-kp-muted">CNA resuelta</span>
                    </span>
                    <span class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">Ver</span>
                  </a>
                @empty
                  <p class="px-3 py-2 text-sm text-kp-muted">Sin CNA resueltas.</p>
                @endforelse
              </div>
            </section>
          @else
            <section class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-kp-green-soft text-xs font-black text-kp-green-dark">PP</div>
                <h3 class="text-sm font-bold text-kp-ink">Promesas por aprobar</h3>
                <x-ui.badge class="ml-auto">{{ $ppPendCount }}</x-ui.badge>
              </div>

              <div class="space-y-1">
                @forelse($ppPend as $p)
                  <a href="{{ route('autorizacion') }}" class="{{ $activityItemClass }}">
                    <span class="size-2 rounded-full bg-kp-green"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate font-bold">{{ $p->dni }} - {{ $p->tipo === 'cancelacion' ? 'Cancelacion' : 'Convenio' }}</span>
                      <span class="block truncate text-xs text-kp-muted">
                        {{ $p->operacion ?: '-' }} - {{ \Carbon\Carbon::parse($p->fecha_promesa)->format('Y-m-d') }} - S/ {{ number_format($p->monto_mostrar, 2) }}
                      </span>
                    </span>
                    <span class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">Revisar</span>
                  </a>
                @empty
                  <p class="px-3 py-2 text-sm text-kp-muted">Nada pendiente aqui.</p>
                @endforelse
              </div>
            </section>

            <section class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-kp-green-soft text-xs font-black text-kp-green-dark">CNA</div>
                <h3 class="text-sm font-bold text-kp-ink">Solicitudes de CNA</h3>
                <x-ui.badge class="ml-auto">{{ $cnaPendCount }}</x-ui.badge>
              </div>

              <div class="space-y-1">
                @forelse($cnaPend as $c)
                  @php $ops = collect((array) $c->operaciones)->filter()->implode(', '); @endphp
                  <a href="{{ route('autorizacion') }}#cna" class="{{ $activityItemClass }}">
                    <span class="size-2 rounded-full bg-amber-500"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate font-bold">CNA #{{ $c->nro_carta }} - DNI {{ $c->dni }}</span>
                      <span class="block truncate text-xs text-kp-muted">{{ $ops ?: '-' }} - {{ optional($c->created_at)->format('Y-m-d') }}</span>
                    </span>
                    <span class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">Revisar</span>
                  </a>
                @empty
                  <p class="px-3 py-2 text-sm text-kp-muted">Sin nuevas CNA.</p>
                @endforelse
              </div>
            </section>

            <section class="space-y-2">
              <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-amber-50 text-xs font-black text-amber-700">7D</div>
                <h3 class="text-sm font-bold text-kp-ink">Cuotas en los proximos 7 dias</h3>
                <x-ui.badge class="ml-auto">{{ $vencCount }}</x-ui.badge>
              </div>

              <div class="space-y-1">
                @forelse($venc as $v)
                  <div class="{{ $activityItemClass }}">
                    <span class="size-2 rounded-full bg-amber-500"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate font-bold">{{ \Carbon\Carbon::parse($v->fecha)->format('d/m') }} - DNI {{ $v->dni }}</span>
                      <span class="block truncate text-xs text-kp-muted">{{ $v->operacion ?: '-' }} - {{ $v->tipo === 'cancelacion' ? 'Cancelacion' : 'Convenio' }} #{{ $v->nro }}</span>
                    </span>
                    <span class="rounded-full border border-kp-border px-2 py-1 text-xs font-semibold text-kp-muted">S/ {{ number_format((float) $v->monto, 2) }}</span>
                  </div>
                @empty
                  <p class="px-3 py-2 text-sm text-kp-muted">No hay vencimientos proximos.</p>
                @endforelse
              </div>
            </section>
          @endif
        </div>

        @unless($isAsesor)
          <div class="mt-4 border-t border-kp-border pt-4 text-right">
            <a href="{{ route('autorizacion') }}" class="text-sm font-semibold text-kp-green hover:text-kp-green-dark">
              Ver bandeja completa
            </a>
          </div>
        @endunless
      </x-ui.card>
    </aside>
  </div>
</x-layout.page-shell>
@endsection
