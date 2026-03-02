@extends('layouts.app')
@section('title','Reportes ▸ CNA')
@section('crumb','Reportes ▸ CNA')

@push('head')
  <meta name="rpt-cna-export" content="{{ route('reportes.cna.export') }}">
  <meta name="rpt-cna-facets" content="{{ route('reportes.cna.facets') }}">

  @vite([
    'resources/css/reportes/cna.css',
    'resources/js/reportes/cna.js'
  ])
@endpush

@section('content')
<div class="space-y-4">

  <div class="kp-card p-5 rpt-cna">
    <form id="filtros"
          method="GET"
          action="{{ route('reportes.cna') }}"
          class="space-y-3">

      {{-- Grid filtros --}}
      <div class="grid grid-cols-12 gap-3 items-end">

        {{-- Desde --}}
        <div class="col-span-6 md:col-span-2">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Desde</label>
          <input type="date"
                 name="from"
                 value="{{ $from }}"
                 data-default="{{ $defaultFrom }}"
                 class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                        focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400">
        </div>

        {{-- Hasta --}}
        <div class="col-span-6 md:col-span-2">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Hasta</label>
          <input type="date"
                 name="to"
                 value="{{ $to }}"
                 data-default="{{ $defaultTo }}"
                 class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                        focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400">
        </div>

        {{-- Estado --}}
        <div class="col-span-12 md:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Estado</label>

          <div class="relative"
               data-multiselect="estado"
               data-title="Estado"
               data-empty="Todos">

            <button type="button"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                           flex items-center justify-between gap-3
                           hover:bg-slate-50
                           focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                    data-ms-button>
              <span class="truncate">Estado: Todos</span>
              <svg class="h-4 w-4 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
              </svg>
            </button>

            <div class="ms-menu hidden" data-ms-menu>
              <input type="text"
                     placeholder="Buscar estado…"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm
                            focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                     data-ms-search>

              <div class="ms-list mt-2" data-ms-list>
                @forelse($estados as $v)
                  <label class="ms-item">
                    <input type="checkbox"
                           name="estado[]"
                           value="{{ $v }}"
                           @checked(in_array($v, $estadoSel, true))>
                    <span class="ms-text">{{ $v }}</span>
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

        {{-- Gestor --}}
        <div class="col-span-12 md:col-span-3">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Gestor</label>

          <div class="relative"
               data-multiselect="gestor"
               data-title="Gestor"
               data-empty="Todos">

            <button type="button"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                           flex items-center justify-between gap-3
                           hover:bg-slate-50
                           focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                    data-ms-button>
              <span class="truncate">Gestor: Todos</span>
              <svg class="h-4 w-4 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
              </svg>
            </button>

            <div class="ms-menu hidden" data-ms-menu>
              <input type="text"
                     placeholder="Buscar gestor…"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm
                            focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                     data-ms-search>

              <div class="ms-list mt-2" data-ms-list>
                @forelse($gestores as $v)
                  <label class="ms-item">
                    <input type="checkbox"
                           name="gestor[]"
                           value="{{ $v }}"
                           @checked(in_array($v, $gestorSel, true))>
                    <span class="ms-text">{{ $v }}</span>
                  </label>
                @empty
                  <div class="px-2 py-2 text-xs text-slate-500">Sin gestores en este rango.</div>
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

          <div class="relative"
               data-multiselect="entidad"
               data-title="Entidad"
               data-empty="Todas">

            <button type="button"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm
                           flex items-center justify-between gap-3
                           hover:bg-slate-50
                           focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                    data-ms-button>
              <span class="truncate">Entidad: Todas</span>
              <svg class="h-4 w-4 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
              </svg>
            </button>

            <div class="ms-menu hidden" data-ms-menu>
              <input type="text"
                     placeholder="Buscar entidad…"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm
                            focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400"
                     data-ms-search>

              <div class="ms-list mt-2" data-ms-list>
                @forelse($entidades as $v)
                  <label class="ms-item">
                    <input type="checkbox"
                           name="entidad[]"
                           value="{{ $v }}"
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

      </div>

      {{-- Toolbar --}}
      <div class="flex flex-wrap items-center gap-2 pt-1">
        <button type="button" id="btnLimpiar" class="kp-btn kp-btn-ghost">
          Limpiar
        </button>

        <div class="flex-1"></div>

        <a id="btnExport" href="#" class="kp-btn kp-btn-primary kp-btn-success">
          Exportar
        </a>
      </div>

    </form>

    <div class="kp-divider my-4"></div>

    @include('reportes.cna_table', ['rows' => $rows])

  </div>

</div>
@endsection