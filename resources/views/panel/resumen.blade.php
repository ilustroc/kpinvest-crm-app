{{-- resources/views/panel/resumen.blade.php --}}
@extends('layouts.app')

@section('title', 'Resumen operativo')
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
  $monthLabel = ucfirst($curr->copy()->locale('es')->translatedFormat('F Y'));
  $chartJson = [
      'labels' => $chartLabels,
      'data' => $chartData,
  ];

  $pendientesRevision = $isAsesor
      ? ($misSup->count() + $misPre->count() + $cnaSup->count() + $cnaPre->count())
      : ($ppPendCount + $cnaPendCount);

  $activityItemClass = 'group flex items-center gap-2.5 rounded-lg border border-transparent px-2.5 py-2 text-[12px] text-kp-ink transition hover:border-kp-border hover:bg-white';
  $activityTitleClass = 'block truncate text-[12px] font-semibold text-kp-ink';
  $activityMetaClass = 'block truncate text-[11px] text-kp-muted';
  $activityActionClass = 'shrink-0 rounded-md border border-kp-border bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500 transition group-hover:border-kp-secondary-border group-hover:text-kp-secondary';
  $money = fn ($value) => \App\Support\Money::format($value, 'PEN');
@endphp

<x-layout.page-shell data-module="panel-resumen">
  <script id="panel-chart-json" type="application/json">{!! json_encode($chartJson, JSON_UNESCAPED_SLASHES) !!}</script>

  <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
      <div class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.09em] text-slate-400">
        <span>Operación</span>
        <span class="size-0.5 rounded-full bg-slate-300" aria-hidden="true"></span>
        <span>{{ $monthLabel }}</span>
      </div>
      <h1 class="mt-1 text-[24px] font-semibold tracking-tight text-kp-ink">Resumen operativo</h1>
      <p class="mt-1 text-[13px] text-kp-muted">Indicadores del día, evolución de pagos y actividades que requieren seguimiento.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <x-ui.badge variant="primary">{{ strtoupper($role ?: 'ROL') }}</x-ui.badge>
      <x-ui.button href="{{ route('dashboard') }}" variant="secondary" size="sm">
        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
        </svg>
        Ver estadísticas
      </x-ui.button>
    </div>
  </header>

  <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-ui.stat-card
      label="Promesas creadas hoy"
      :value="number_format($kpiPromHoy)"
      hint="Altas registradas durante el día"
      tone="primary"
    >
      <x-slot:icon>
        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
        </svg>
      </x-slot:icon>
    </x-ui.stat-card>

    <x-ui.stat-card
      label="Pagos registrados hoy"
      :value="$money($kpiPagosHoy)"
      hint="Monto conciliado en el día"
      tone="success"
    >
      <x-slot:icon>
        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
        </svg>
      </x-slot:icon>
    </x-ui.stat-card>

    <x-ui.stat-card
      label="Pagos del mes"
      :value="$money($sumMes)"
      :hint="$monthLabel"
      tone="neutral"
    >
      <x-slot:icon>
        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
        </svg>
      </x-slot:icon>
    </x-ui.stat-card>

    <x-ui.stat-card
      label="Pendientes de revisión"
      :value="number_format($pendientesRevision)"
      :hint="$vencCount === 1 ? '1 cuota vence en 7 días' : $vencCount.' cuotas vencen en 7 días'"
      tone="warning"
    >
      <x-slot:icon>
        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z" />
        </svg>
      </x-slot:icon>
    </x-ui.stat-card>
  </div>

  <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="min-w-0 space-y-5">
      <x-ui.card>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-[15px] font-semibold text-kp-ink">Pagos del mes</h2>
            <p class="mt-0.5 text-[11px] text-kp-muted">Evolución diaria de pagos registrados.</p>
          </div>

          <div class="flex flex-wrap items-center gap-1.5">
            <x-ui.button
              href="{{ url()->current().'?mes='.$curr->copy()->subMonth()->format('Y-m') }}"
              variant="secondary"
              size="icon"
              aria-label="Mes anterior"
            >
              <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
              </svg>
            </x-ui.button>

            <label for="mesPicker" class="sr-only">Mes</label>
            <input
              type="month"
              id="mesPicker"
              value="{{ $curr->format('Y-m') }}"
              class="h-9 rounded-lg border border-kp-border bg-white px-2.5 text-[12px] font-medium text-kp-ink transition hover:border-slate-300 kp-focus"
              data-month-picker
            >

            <x-ui.button
              href="{{ url()->current().'?mes='.$curr->copy()->addMonth()->format('Y-m') }}"
              variant="secondary"
              size="icon"
              aria-label="Mes siguiente"
            >
              <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
              </svg>
            </x-ui.button>
          </div>
        </div>

        <div class="relative h-[300px]">
          <div data-panel-chart-loading class="absolute inset-0 z-0 flex items-end gap-1.5 rounded-lg bg-white px-2 pb-7 pt-4">
            @for($i = 1; $i <= 24; $i++)
              <div class="kp-skeleton flex-1 rounded-t-sm" style="height: {{ 18 + (($i * 17) % 62) }}%"></div>
            @endfor
          </div>
          <canvas id="chartPagos" class="relative z-10" aria-label="Pagos del mes" role="img"></canvas>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-kp-border pt-3">
          <p class="text-[11px] text-kp-muted">Total registrado en {{ $monthLabel }}</p>
          <p class="text-[13px] font-semibold text-kp-primary-dark">{{ $money($sumMes) }}</p>
        </div>
      </x-ui.card>
    </div>

    <aside class="min-w-0 xl:sticky xl:top-20 xl:self-start">
      <x-ui.card class="overflow-hidden">
        <div class="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 class="text-[15px] font-semibold text-kp-ink">{{ $isAsesor ? 'Tus actividades' : 'Actividades' }}</h2>
            <p class="mt-0.5 text-[11px] text-kp-muted">Seguimiento operativo del rol actual.</p>
          </div>
          <x-ui.badge>{{ strtoupper($role ?: 'ROL') }}</x-ui.badge>
        </div>

        <div class="kp-scrollbar max-h-[calc(100vh-230px)] space-y-5 overflow-auto pr-1">
          @if($isAsesor)
            <x-dashboard.activity-section title="Promesas · Supervisor" :count="$misSup->count()" tone="success">
              <x-slot:icon>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2.25 4.5H6.75A2.25 2.25 0 0 1 4.5 18.25V5.75A2.25 2.25 0 0 1 6.75 3.5h10.5a2.25 2.25 0 0 1 2.25 2.25v14.75Z" /></svg>
              </x-slot:icon>
              @forelse($misSup as $p)
                <a href="{{ route('clientes.show', $p->dni) }}" class="{{ $activityItemClass }}">
                  <span class="size-1.5 shrink-0 rounded-full bg-kp-green"></span>
                  <span class="min-w-0 flex-1">
                    <span class="{{ $activityTitleClass }}">{{ $p->dni }} · {{ $p->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }}</span>
                    <span class="{{ $activityMetaClass }}">{{ $p->operacion ?: '-' }} · Pendiente de supervisor</span>
                  </span>
                  <span class="{{ $activityActionClass }}">Ver</span>
                </a>
              @empty
                <p class="kp-empty-inline">Sin pendientes con supervisor.</p>
              @endforelse
            </x-dashboard.activity-section>

            <x-dashboard.activity-section title="Promesas · Pre-aprobadas" :count="$misPre->count()" tone="warning">
              <x-slot:icon>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" /><circle cx="12" cy="12" r="8.25" /></svg>
              </x-slot:icon>
              @forelse($misPre as $p)
                <a href="{{ route('clientes.show', $p->dni) }}" class="{{ $activityItemClass }}">
                  <span class="size-1.5 shrink-0 rounded-full bg-amber-500"></span>
                  <span class="min-w-0 flex-1">
                    <span class="{{ $activityTitleClass }}">{{ $p->dni }} · {{ $p->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }}</span>
                    <span class="{{ $activityMetaClass }}">{{ $p->operacion ?: '-' }} · Esperando administración</span>
                  </span>
                  <span class="{{ $activityActionClass }}">Ver</span>
                </a>
              @empty
                <p class="kp-empty-inline">No hay pre-aprobadas.</p>
              @endforelse
            </x-dashboard.activity-section>

            <x-dashboard.activity-section title="Promesas · Resueltas" :count="$misRes->count()" tone="neutral">
              <x-slot:icon>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" /></svg>
              </x-slot:icon>
              @forelse($misRes as $p)
                @php
                  $estado = strtoupper($p->workflow_estado);
                  $dotClass = in_array($estado, ['RECHAZADA', 'RECHAZADA_SUP'], true) ? 'bg-red-500' : 'bg-kp-success';
                @endphp
                <a href="{{ route('clientes.show', $p->dni) }}" class="{{ $activityItemClass }}">
                  <span class="size-1.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                  <span class="min-w-0 flex-1">
                    <span class="{{ $activityTitleClass }}">{{ $p->dni }} · {{ ucfirst($p->workflow_estado) }}</span>
                    <span class="{{ $activityMetaClass }}">{{ $p->operacion ?: '-' }}</span>
                  </span>
                  <span class="{{ $activityActionClass }}">Ver</span>
                </a>
              @empty
                <p class="kp-empty-inline">Aún no hay resoluciones.</p>
              @endforelse
            </x-dashboard.activity-section>

            <x-dashboard.activity-section title="CNA · Supervisor" :count="$cnaSup->count()" tone="success">
              <x-slot:icon>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5 12 13l8-5.5M5 6h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z" /></svg>
              </x-slot:icon>
              @forelse($cnaSup as $c)
                <a href="{{ route('clientes.show', $c->dni) }}" class="{{ $activityItemClass }}">
                  <span class="size-1.5 shrink-0 rounded-full bg-kp-green"></span>
                  <span class="min-w-0 flex-1">
                    <span class="{{ $activityTitleClass }}">DNI {{ $c->dni }}</span>
                    <span class="{{ $activityMetaClass }}">Pendiente de supervisor</span>
                  </span>
                  <span class="{{ $activityActionClass }}">Ver</span>
                </a>
              @empty
                <p class="kp-empty-inline">Sin CNA en supervisor.</p>
              @endforelse
            </x-dashboard.activity-section>

            <x-dashboard.activity-section title="CNA · Pre-aprobadas" :count="$cnaPre->count()" tone="warning">
              <x-slot:icon>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" /><circle cx="12" cy="12" r="8.25" /></svg>
              </x-slot:icon>
              @forelse($cnaPre as $c)
                <a href="{{ route('clientes.show', $c->dni) }}" class="{{ $activityItemClass }}">
                  <span class="size-1.5 shrink-0 rounded-full bg-amber-500"></span>
                  <span class="min-w-0 flex-1">
                    <span class="{{ $activityTitleClass }}">DNI {{ $c->dni }}</span>
                    <span class="{{ $activityMetaClass }}">Esperando administración</span>
                  </span>
                  <span class="{{ $activityActionClass }}">Ver</span>
                </a>
              @empty
                <p class="kp-empty-inline">Sin CNA pre-aprobadas.</p>
              @endforelse
            </x-dashboard.activity-section>

            <x-dashboard.activity-section title="CNA · Resueltas" :count="$cnaRes->count()" tone="neutral">
              <x-slot:icon>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" /></svg>
              </x-slot:icon>
              @forelse($cnaRes as $c)
                @php
                  $estado = strtoupper($c->workflow_estado);
                  $dotClass = in_array($estado, ['RECHAZADA', 'RECHAZADA_SUP'], true) ? 'bg-red-500' : 'bg-kp-success';
                @endphp
                <a href="{{ route('clientes.show', $c->dni) }}" class="{{ $activityItemClass }}">
                  <span class="size-1.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                  <span class="min-w-0 flex-1">
                    <span class="{{ $activityTitleClass }}">DNI {{ $c->dni }} · {{ ucfirst($c->workflow_estado) }}</span>
                    <span class="{{ $activityMetaClass }}">CNA resuelta</span>
                  </span>
                  <span class="{{ $activityActionClass }}">Ver</span>
                </a>
              @empty
                <p class="kp-empty-inline">Sin CNA resueltas.</p>
              @endforelse
            </x-dashboard.activity-section>
          @else
            <x-dashboard.activity-section title="Promesas por aprobar" :count="$ppPendCount" tone="success">
              <x-slot:icon>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2.25 4.5H6.75A2.25 2.25 0 0 1 4.5 18.25V5.75A2.25 2.25 0 0 1 6.75 3.5h10.5a2.25 2.25 0 0 1 2.25 2.25v14.75Z" /></svg>
              </x-slot:icon>
              @forelse($ppPend as $p)
                <a href="{{ route('autorizacion') }}" class="{{ $activityItemClass }}">
                  <span class="size-1.5 shrink-0 rounded-full bg-kp-green"></span>
                  <span class="min-w-0 flex-1">
                    <span class="{{ $activityTitleClass }}">{{ $p->dni }} · {{ $p->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }}</span>
                    <span class="{{ $activityMetaClass }}">{{ $p->operacion ?: '-' }} · {{ \Carbon\Carbon::parse($p->fecha_promesa)->format('Y-m-d') }} · {{ $money($p->monto_mostrar) }}</span>
                  </span>
                  <span class="{{ $activityActionClass }}">Revisar</span>
                </a>
              @empty
                <p class="kp-empty-inline">Nada pendiente aquí.</p>
              @endforelse
            </x-dashboard.activity-section>

            <x-dashboard.activity-section title="Solicitudes de CNA" :count="$cnaPendCount" tone="warning">
              <x-slot:icon>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5 12 13l8-5.5M5 6h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z" /></svg>
              </x-slot:icon>
              @forelse($cnaPend as $c)
                @php
                  $rawOps = is_string($c->operaciones) ? (json_decode($c->operaciones, true) ?: []) : (array) $c->operaciones;
                  $ops = collect($rawOps)
                      ->map(fn ($op) => is_array($op) ? ($op['operacion'] ?? null) : $op)
                      ->filter()
                      ->implode(', ');
                @endphp
                <a href="{{ route('autorizacion') }}#cna" class="{{ $activityItemClass }}">
                  <span class="size-1.5 shrink-0 rounded-full bg-amber-500"></span>
                  <span class="min-w-0 flex-1">
                    <span class="{{ $activityTitleClass }}">{{ $c->nro_carta ?: 'CNA' }} · DNI {{ $c->dni }}</span>
                    <span class="{{ $activityMetaClass }}">{{ $ops ?: '-' }} · {{ optional($c->created_at)->format('Y-m-d') }}</span>
                  </span>
                  <span class="{{ $activityActionClass }}">Revisar</span>
                </a>
              @empty
                <p class="kp-empty-inline">Sin nuevas CNA.</p>
              @endforelse
            </x-dashboard.activity-section>

            <x-dashboard.activity-section title="Cuotas · próximos 7 días" :count="$vencCount" tone="neutral">
              <x-slot:icon>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
              </x-slot:icon>
              @forelse($venc as $v)
                <div class="{{ $activityItemClass }}">
                  <span class="size-1.5 shrink-0 rounded-full bg-amber-500"></span>
                  <span class="min-w-0 flex-1">
                    <span class="{{ $activityTitleClass }}">{{ \Carbon\Carbon::parse($v->fecha)->format('d/m') }} · DNI {{ $v->dni }}</span>
                    <span class="{{ $activityMetaClass }}">{{ $v->operacion ?: '-' }} · {{ $v->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }} #{{ $v->nro }}</span>
                  </span>
                  <span class="{{ $activityActionClass }}">{{ $money($v->monto) }}</span>
                </div>
              @empty
                <p class="kp-empty-inline">No hay vencimientos próximos.</p>
              @endforelse
            </x-dashboard.activity-section>
          @endif
        </div>

        @unless($isAsesor)
          <div class="mt-4 border-t border-kp-border pt-3 text-right">
            <a href="{{ route('autorizacion') }}" class="text-[12px] font-semibold text-kp-secondary transition hover:text-kp-navy kp-focus">
              Ver bandeja completa
            </a>
          </div>
        @endunless
      </x-ui.card>
    </aside>
  </div>
</x-layout.page-shell>
@endsection
