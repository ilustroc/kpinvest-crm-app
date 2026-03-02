{{-- resources/views/reportes/pdp.blade.php --}}
@extends('layouts.app')
@section('title','Reportes ▸ Promesas')
@section('crumb','Reportes ▸ Promesas')

@push('head')
  <meta name="rpt-pdp-export" content="{{ route('reportes.pdp.export') }}">
  <meta name="rpt-pdp-facets" content="{{ route('reportes.pdp.facets') }}">

  @vite([
    'resources/css/reportes/promesas.css',
    'resources/js/reportes/promesas.js'
  ])
@endpush

@section('content')
<div class="space-y-4">

  <div class="kp-card p-5 rpt-pdp">

    <form id="filtros" method="GET" action="{{ route('reportes.pdp') }}" class="space-y-3">

      <div class="grid grid-cols-12 gap-3 items-end">

        {{-- Desde --}}
        <div class="col-span-6 md:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Desde</label>
          <input type="date" name="from"
                 value="{{ $from }}" data-default="{{ $defaultFrom }}"
                 class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                        focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400">
        </div>

        {{-- Hasta --}}
        <div class="col-span-6 md:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Hasta</label>
          <input type="date" name="to"
                 value="{{ $to }}" data-default="{{ $defaultTo }}"
                 class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                        focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400">
        </div>

        {{-- Estado --}}
        <div class="col-span-12 md:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Estado</label>

          <div class="relative overflow-visible" data-multiselect="estado" data-title="Estado" data-empty="Todos">
            <button type="button"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                          flex items-center justify-between gap-3 hover:bg-slate-50
                          focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                    data-ms-button>
              <span class="truncate">Estado: Todos</span>
              <svg class="h-4 w-4 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
              </svg>
            </button>

            <div class="ms-menu hidden" data-ms-menu>
              <input type="text"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm
                            focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                    placeholder="Buscar estado…"
                    data-ms-search>

              <div class="ms-list mt-2 max-h-64 overflow-auto rounded-xl border border-slate-200 bg-white" data-ms-list>
                @forelse($estados as $v)
                  <label class="ms-item flex items-center gap-2 px-2 py-2 cursor-pointer select-none hover:bg-slate-50">
                    <input type="checkbox" name="estado[]" value="{{ $v }}"
                          class="h-4 w-4 accent-emerald-600"
                          @checked(in_array($v, $estadoSel, true))>
                    <span class="block w-full truncate">{{ $v }}</span>
                  </label>
                @empty
                  <div class="px-2 py-2 text-xs text-slate-500">Sin estados en este rango.</div>
                @endforelse
              </div>

              <div class="mt-3 flex gap-2">
                <button type="button" class="kp-btn kp-btn-ghost" data-ms-clear>Limpiar</button>
                <button type="button" class="kp-btn kp-btn-primary ms-auto" data-ms-apply>Aplicar</button>
              </div>
            </div>
          </div>
        </div>

        {{-- Tipo Neg --}}
        <div class="col-span-12 md:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo_Neg</label>

          <div class="relative overflow-visible" data-multiselect="tipo" data-title="Tipo_Neg" data-empty="Todos">
            <button type="button"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                           flex items-center justify-between gap-3 hover:bg-slate-50
                           focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                    data-ms-button>
              <span class="truncate">Tipo_Neg: Todos</span>
              <svg class="h-4 w-4 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
              </svg>
            </button>

            <div class="ms-menu hidden" data-ms-menu>
              <input type="text"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm
                            focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                     placeholder="Buscar tipo…"
                     data-ms-search>

              <div class="ms-list mt-2" data-ms-list>
                @forelse($tipos as $v)
                  <label class="ms-item">
                    <input type="checkbox" name="tipo[]" value="{{ $v }}"
                           @checked(in_array($v, $tipoSel, true))>
                    <span class="ms-text">{{ $v }}</span>
                  </label>
                @empty
                  <div class="px-2 py-2 text-xs text-slate-500">Sin tipos en este rango.</div>
                @endforelse
              </div>

              <div class="mt-3 flex gap-2">
                <button type="button" class="kp-btn kp-btn-ghost" data-ms-clear>Limpiar</button>
                <button type="button" class="kp-btn kp-btn-primary ms-auto" data-ms-apply>Aplicar</button>
              </div>
            </div>
          </div>
        </div>

        {{-- Entidad --}}
        <div class="col-span-12 md:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Entidad</label>

          <div class="relative overflow-visible" data-multiselect="entidad" data-title="Entidad" data-empty="Todas">
            <button type="button"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                           flex items-center justify-between gap-3 hover:bg-slate-50
                           focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                    data-ms-button>
              <span class="truncate">Entidad: Todas</span>
              <svg class="h-4 w-4 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
              </svg>
            </button>

            <div class="ms-menu hidden" data-ms-menu>
              <input type="text"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm
                            focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                     placeholder="Buscar entidad…"
                     data-ms-search>

              <div class="ms-list mt-2" data-ms-list>
                @forelse($entidades as $v)
                  <label class="ms-item">
                    <input type="checkbox" name="entidad[]" value="{{ $v }}"
                           @checked(in_array($v, $entidadSel, true))>
                    <span class="ms-text">{{ $v }}</span>
                  </label>
                @empty
                  <div class="px-2 py-2 text-xs text-slate-500">Sin entidades en este rango.</div>
                @endforelse
              </div>

              <div class="mt-3 flex gap-2">
                <button type="button" class="kp-btn kp-btn-ghost" data-ms-clear>Limpiar</button>
                <button type="button" class="kp-btn kp-btn-primary ms-auto" data-ms-apply>Aplicar</button>
              </div>
            </div>
          </div>
        </div>

        {{-- Buscar --}}
        <div class="col-span-12 md:col-span-5">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Buscar por DNI o Cliente</label>
          <div class="flex gap-2">
            <input type="text" name="q" value="{{ $q }}" placeholder="DNI o Cliente"
                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                          focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400">
            <button type="button" id="btnBuscar" class="kp-btn kp-btn-ghost">Buscar</button>
          </div>
        </div>

      </div>

      {{-- Toolbar --}}
      <div class="flex flex-wrap items-center gap-2 pt-1">
        <button type="button" id="btnLimpiar" class="kp-btn kp-btn-ghost">Limpiar</button>

        <div class="flex-1"></div>

        <a class="kp-btn kp-btn-primary kp-btn-success" id="btnExport" href="#">
          Exportar
        </a>
      </div>

    </form>

    <div class="kp-divider my-4"></div>

    @include('reportes.pdp_table', ['rows' => $rows])

  </div>
</div>
@endsection