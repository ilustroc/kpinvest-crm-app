{{-- resources/views/panel/resumen.blade.php --}}
@extends('layouts.app')
@section('title','Panel')
@section('crumb','Resumen')

@push('head')
  <meta name="quick-suggest-url" content="{{ route('clientes.suggest') }}">
  @vite(['resources/css/panel/resumen.css', 'resources/js/panel/resumen.js'])
@endpush

@section('content')
@php
  $role  = $role  ?? strtolower(auth()->user()->role ?? '');
  $isAsesor = $isAsesor ?? ($role==='asesor');
  $isSupervisor = $isSupervisor ?? ($role==='supervisor');
  $isAdmin = $isAdmin ?? in_array($role,['administrador','sistemas']);

  $misSup = $misSup ?? collect();
  $misPre = $misPre ?? collect();
  $misRes = $misRes ?? collect();
  $cnaSup = $cnaSup ?? collect();
  $cnaPre = $cnaPre ?? collect();
  $cnaRes = $cnaRes ?? collect();
@endphp

<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4">
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    {{-- ================= IZQUIERDA ================= --}}
    <div class="lg:col-span-8 space-y-4">

      {{-- Bienvenida + buscador --}}
      <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70 p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

          <div>
            <h1 class="text-base sm:text-lg font-extrabold text-slate-900">¡Bienvenido(a)!</h1>
            <div class="text-sm text-slate-500">Panel inicial.</div>

            @if (session('quick_error'))
              <div class="mt-3 inline-flex items-center gap-2 rounded-xl bg-amber-50 text-amber-800 ring-1 ring-amber-200 px-3 py-2 text-sm">
                <span class="font-extrabold">⚠</span>
                <span class="font-semibold">{{ session('quick_error') }}</span>
              </div>
            @endif
          </div>

          <form id="frmQuick" class="relative w-full sm:w-auto flex items-center gap-2"
                role="search" action="{{ route('clientes.quick') }}" method="GET" autocomplete="off">
            <div class="relative w-full sm:w-[360px]">
              <input id="inpQuick" name="q"
                     class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm
                            focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                     placeholder="DNI / Operación / Nombre" aria-label="Buscar por DNI, Operación o Nombre">
              <div id="quickSug" class="quick-menu"></div>
            </div>

            <button class="inline-flex items-center justify-center rounded-2xl px-4 py-2.5 text-sm font-extrabold
                           bg-emerald-600 text-white hover:bg-emerald-700 transition"
                    type="submit">
              Buscar
            </button>
          </form>
        </div>
      </div>

      {{-- Coincidencias (si aplica) --}}
      @if (session('quick_list'))
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70 p-5">
          <div class="text-sm font-extrabold text-slate-900 mb-3">Coincidencias</div>

          <div class="overflow-auto rounded-2xl ring-1 ring-slate-200/70">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-slate-50 text-xs uppercase tracking-wide text-slate-700">
                  <th class="px-3 py-3 text-left">DNI</th>
                  <th class="px-3 py-3 text-left">Nombre</th>
                  <th class="px-3 py-3 text-left">Operación</th>
                  <th class="px-3 py-3 text-left">Cosecha</th>
                  <th class="px-3 py-3 text-right"></th>
                </tr>
              </thead>
              <tbody>
                @foreach(session('quick_list') as $r)
                  @php
                    $dni      = data_get($r, 'dni');
                    $nombre   = data_get($r, 'nombre');
                    $operacion= data_get($r, 'operacion');
                    $cosecha  = data_get($r, 'cosecha');
                  @endphp
                  <tr class="border-t border-slate-100">
                    <td class="px-3 py-3 font-semibold text-slate-900 whitespace-nowrap">{{ $dni }}</td>
                    <td class="px-3 py-3 text-slate-700">{{ $nombre }}</td>
                    <td class="px-3 py-3 text-slate-700 whitespace-nowrap">{{ $operacion }}</td>
                    <td class="px-3 py-3 text-slate-700 whitespace-nowrap">{{ $cosecha }}</td>
                    <td class="px-3 py-3 text-right">
                      <a class="inline-flex items-center justify-center rounded-xl px-3 py-2 text-xs font-extrabold
                                ring-1 ring-slate-200 hover:bg-slate-50"
                         href="{{ route('clientes.show', $dni) }}">
                        Ver
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif

      {{-- KPIs --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70 p-5 flex items-center gap-3">
          <div class="h-11 w-11 rounded-xl grid place-items-center bg-emerald-500/10 text-emerald-700 font-extrabold">✓</div>
          <div>
            <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Promesas creadas hoy</div>
            <div class="text-xl font-extrabold text-slate-900">{{ number_format($kpiPromHoy) }}</div>
          </div>
        </div>

        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70 p-5 flex items-center gap-3">
          <div class="h-11 w-11 rounded-xl grid place-items-center bg-emerald-500/10 text-emerald-700 font-extrabold">S/</div>
          <div>
            <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Pagos registrados hoy</div>
            <div class="text-xl font-extrabold text-slate-900">S/ {{ number_format($kpiPagosHoy,2) }}</div>
          </div>
        </div>
      </div>

      {{-- Chart Pagos --}}
      <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70 p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-3">
          <div class="text-sm font-extrabold text-slate-900">Pagos del mes</div>

          <div class="flex items-center gap-2">
            @php $curr = \Carbon\Carbon::createFromFormat('Y-m',$mes); @endphp

            <a class="h-9 w-9 grid place-items-center rounded-xl ring-1 ring-slate-200 hover:bg-slate-50"
               href="{{ url()->current().'?mes='.$curr->copy()->subMonth()->format('Y-m') }}">‹</a>

            <input type="month" id="mesPicker"
                   class="h-9 rounded-xl border border-slate-200 bg-white px-3 text-sm"
                   value="{{ $curr->format('Y-m') }}">

            <a class="h-9 w-9 grid place-items-center rounded-xl ring-1 ring-slate-200 hover:bg-slate-50"
               href="{{ url()->current().'?mes='.$curr->copy()->addMonth()->format('Y-m') }}">›</a>
          </div>
        </div>

        <div class="chart-wrap">
          <canvas id="chartPagos"
                  data-chart='@json(["labels"=>$chartLabels,"data"=>$chartData])'></canvas>
        </div>
      </div>

    </div>

    {{-- ================= DERECHA ================= --}}
    <div class="lg:col-span-4">
      <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70 overflow-hidden lg:sticky lg:top-24">
        <div class="px-4 py-3 bg-emerald-700 text-white font-extrabold">
          {{ $isAsesor ? 'Tus actividades' : 'Actividades' }}
        </div>

        <div class="p-3 max-h-[70vh] overflow-auto">

          {{-- ASESOR --}}
          @if($isAsesor)

            @php
              $groups = [
                ['title'=>'Promesas — En Supervisor','count'=>$misSup->count(),'rows'=>$misSup,'dot'=>'bg-emerald-600'],
                ['title'=>'Promesas — Pre-aprobadas','count'=>$misPre->count(),'rows'=>$misPre,'dot'=>'bg-emerald-500'],
                ['title'=>'Promesas — Resueltas','count'=>$misRes->count(),'rows'=>$misRes,'dot'=>'bg-slate-400'],
              ];
            @endphp

            @foreach($groups as $g)
              <div class="mb-4">
                <div class="flex items-center gap-2 px-1 mb-2">
                  <div class="text-sm font-extrabold text-slate-900">{{ $g['title'] }}</div>
                  <span class="ml-auto text-[11px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                    {{ $g['count'] }}
                  </span>
                </div>

                <ul class="space-y-2">
                  @forelse($g['rows'] as $p)
                    <li>
                      <a href="{{ route('clientes.show',$p->dni) }}"
                         class="flex items-start gap-3 rounded-2xl ring-1 ring-slate-200/70 bg-white hover:bg-slate-50 p-3 transition">
                        <span class="mt-2 h-2.5 w-2.5 rounded-full {{ $g['dot'] }}"></span>
                        <div class="min-w-0">
                          <div class="text-sm font-extrabold text-slate-900 truncate">
                            {{ $p->dni }}
                            <span class="text-slate-500 font-semibold">
                              • {{ $p->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }}
                            </span>
                          </div>
                          <div class="text-xs text-slate-500 truncate">
                            {{ $p->operacion ?: '—' }}
                          </div>
                        </div>
                        <span class="ml-auto text-[11px] font-extrabold px-2 py-1 rounded-full ring-1 ring-slate-200 text-slate-700 whitespace-nowrap">
                          Ver cliente
                        </span>
                      </a>
                    </li>
                  @empty
                    <div class="text-xs text-slate-500 px-1">Sin registros.</div>
                  @endforelse
                </ul>
              </div>
            @endforeach

            {{-- CNA (igual estilo) --}}
            @php
              $cnaGroups = [
                ['title'=>'CNA — En Supervisor','count'=>$cnaSup->count(),'rows'=>$cnaSup,'dot'=>'bg-emerald-600','sub'=>'Pendiente de Supervisor'],
                ['title'=>'CNA — Pre-aprobadas','count'=>$cnaPre->count(),'rows'=>$cnaPre,'dot'=>'bg-emerald-500','sub'=>'Pre-aprobada (esperando Administración)'],
                ['title'=>'CNA — Resueltas','count'=>$cnaRes->count(),'rows'=>$cnaRes,'dot'=>'bg-slate-400','sub'=>'Resuelta'],
              ];
            @endphp

            @foreach($cnaGroups as $g)
              <div class="mb-4">
                <div class="flex items-center gap-2 px-1 mb-2">
                  <div class="text-sm font-extrabold text-slate-900">{{ $g['title'] }}</div>
                  <span class="ml-auto text-[11px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                    {{ $g['count'] }}
                  </span>
                </div>

                <ul class="space-y-2">
                  @forelse($g['rows'] as $c)
                    <li>
                      <a href="{{ route('clientes.show',$c->dni) }}"
                         class="flex items-start gap-3 rounded-2xl ring-1 ring-slate-200/70 bg-white hover:bg-slate-50 p-3 transition">
                        <span class="mt-2 h-2.5 w-2.5 rounded-full {{ $g['dot'] }}"></span>
                        <div class="min-w-0">
                          <div class="text-sm font-extrabold text-slate-900 truncate">DNI {{ $c->dni }}</div>
                          <div class="text-xs text-slate-500 truncate">{{ $g['sub'] }}</div>
                        </div>
                        <span class="ml-auto text-[11px] font-extrabold px-2 py-1 rounded-full ring-1 ring-slate-200 text-slate-700 whitespace-nowrap">
                          Ver cliente
                        </span>
                      </a>
                    </li>
                  @empty
                    <div class="text-xs text-slate-500 px-1">Sin registros.</div>
                  @endforelse
                </ul>
              </div>
            @endforeach

          @else
            {{-- ================= SUPERVISOR / ADMIN ================= --}}

            {{-- Promesas por aprobar --}}
            <div class="mb-4">
              <div class="flex items-center gap-2 px-1 mb-2">
                <div class="text-sm font-extrabold text-slate-900">Promesas por aprobar</div>
                <span class="ml-auto text-[11px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                  {{ $ppPendCount }}
                </span>
              </div>

              <ul class="space-y-2">
                @forelse($ppPend as $p)
                  <li>
                    <a href="{{ route('autorizacion') }}"
                      class="flex items-start gap-3 rounded-2xl ring-1 ring-slate-200/70 bg-white hover:bg-slate-50 p-3 transition">
                      <span class="mt-2 h-2.5 w-2.5 rounded-full bg-emerald-600"></span>

                      <div class="min-w-0 flex-1">
                        <div class="text-sm font-extrabold text-slate-900 truncate">
                          {{ $p->dni }}
                          <span class="text-slate-500 font-semibold">
                            • {{ $p->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }}
                          </span>
                        </div>

                        <div class="text-xs text-slate-500 truncate">
                          {{ $p->operacion ?: '—' }}
                          — {{ \Carbon\Carbon::parse($p->fecha_promesa)->format('Y-m-d') }}
                        </div>

                        <div class="mt-1 text-xs font-extrabold text-slate-900">
                          S/ {{ number_format((float)$p->monto_mostrar,2) }}
                        </div>
                      </div>

                      <span class="ml-auto text-[11px] font-extrabold px-2 py-1 rounded-full ring-1 ring-slate-200 text-slate-700 whitespace-nowrap">
                        Revisar
                      </span>
                    </a>
                  </li>
                @empty
                  <div class="text-xs text-slate-500 px-1">Nada pendiente aquí.</div>
                @endforelse
              </ul>
            </div>

            {{-- CNA por aprobar --}}
            <div class="mb-4">
              <div class="flex items-center gap-2 px-1 mb-2">
                <div class="text-sm font-extrabold text-slate-900">Solicitudes de CNA</div>
                <span class="ml-auto text-[11px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                  {{ $cnaPendCount }}
                </span>
              </div>

              <ul class="space-y-2">
                @forelse($cnaPend as $c)
                  @php $ops = collect((array)$c->operaciones)->filter()->implode(', '); @endphp
                  <li>
                    <a href="{{ route('autorizacion') }}#cna"
                      class="flex items-start gap-3 rounded-2xl ring-1 ring-slate-200/70 bg-white hover:bg-slate-50 p-3 transition">
                      <span class="mt-2 h-2.5 w-2.5 rounded-full bg-emerald-500"></span>

                      <div class="min-w-0 flex-1">
                        <div class="text-sm font-extrabold text-slate-900 truncate">
                          CNA #{{ $c->nro_carta }}
                          <span class="text-slate-500 font-semibold">• DNI {{ $c->dni }}</span>
                        </div>

                        <div class="text-xs text-slate-500 truncate">
                          {{ $ops ?: '—' }}
                          — {{ optional($c->created_at)->format('Y-m-d') }}
                        </div>
                      </div>

                      <span class="ml-auto text-[11px] font-extrabold px-2 py-1 rounded-full ring-1 ring-slate-200 text-slate-700 whitespace-nowrap">
                        Revisar
                      </span>
                    </a>
                  </li>
                @empty
                  <div class="text-xs text-slate-500 px-1">Sin nuevas CNA.</div>
                @endforelse
              </ul>
            </div>

            {{-- Vencimientos próximos --}}
            <div class="mb-1">
              <div class="flex items-center gap-2 px-1 mb-2">
                <div class="text-sm font-extrabold text-slate-900">Cuotas próximos 7 días</div>
                <span class="ml-auto text-[11px] font-extrabold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-200">
                  {{ $vencCount }}
                </span>
              </div>

              <ul class="space-y-2">
                @forelse($venc as $v)
                  <li>
                    <div class="flex items-start gap-3 rounded-2xl ring-1 ring-slate-200/70 bg-white p-3">
                      <span class="mt-2 h-2.5 w-2.5 rounded-full bg-amber-400"></span>

                      <div class="min-w-0 flex-1">
                        <div class="text-sm font-extrabold text-slate-900 truncate">
                          {{ \Carbon\Carbon::parse($v->fecha)->format('d/m') }}
                          <span class="text-slate-500 font-semibold">• DNI {{ $v->dni }}</span>
                        </div>

                        <div class="text-xs text-slate-500 truncate">
                          {{ $v->operacion ?: '—' }}
                          — {{ $v->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio' }} #{{ $v->nro }}
                        </div>
                      </div>

                      <span class="ml-auto text-[11px] font-extrabold px-2 py-1 rounded-full ring-1 ring-slate-200 text-slate-700 whitespace-nowrap">
                        S/ {{ number_format((float)$v->monto,2) }}
                      </span>
                    </div>
                  </li>
                @empty
                  <div class="text-xs text-slate-500 px-1">No hay vencimientos próximos.</div>
                @endforelse
              </ul>
            </div>
          @endif

        </div>

        @unless($isAsesor)
          <div class="px-4 py-3 border-t border-slate-200 text-right">
            <a class="text-sm font-semibold text-emerald-700 hover:underline" href="{{ route('autorizacion') }}">
              Ver bandeja completa →
            </a>
          </div>
        @endunless
      </div>
    </div>

  </div>
</div>
@endsection