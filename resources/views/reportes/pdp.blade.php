@extends('layouts.app')
@section('title','Reportes - Promesas')
@section('crumb','Reportes - Promesas')
@section('tailwind_only', true)

@push('head')
  <meta name="rpt-pdp-export" content="{{ route('reportes.pdp.export') }}">
  <meta name="rpt-pdp-facets" content="{{ route('reportes.pdp.facets') }}">
@endpush

@section('content')
<x-layout.page-shell data-module="reportes-promesas">
  <x-layout.page-header
    title="Reporte de promesas"
    subtitle="Consulta promesas por fecha, estado, tipo, entidad o DNI.">
    <x-slot:actions>
      <x-ui.button href="#" variant="secondary" data-report-export>
        Exportar
      </x-ui.button>
    </x-slot:actions>
  </x-layout.page-header>

  <x-ui.card>
    <form id="filtros" data-report-filters class="grid gap-3 lg:grid-cols-12 lg:items-end" method="GET" action="{{ route('reportes.pdp') }}">
      <div class="lg:col-span-2">
        <x-forms.date label="Desde" name="from" value="{{ $from }}" data-default="{{ $defaultFrom }}" />
      </div>

      <div class="lg:col-span-2">
        <x-forms.date label="Hasta" name="to" value="{{ $to }}" data-default="{{ $defaultTo }}" />
      </div>

      <div class="lg:col-span-2">
        <x-reportes.multiselect name="estado" title="Estado" empty="Todos" :options="$estados" :selected="$estadoSel" placeholder="Buscar estado..." />
      </div>

      <div class="lg:col-span-3">
        <x-reportes.multiselect name="tipo" title="Tipo_Neg" empty="Todos" :options="$tipos" :selected="$tipoSel" placeholder="Buscar tipo..." />
      </div>

      <div class="lg:col-span-3">
        <x-reportes.multiselect name="entidad" title="Entidad" empty="Todas" :options="$entidades" :selected="$entidadSel" placeholder="Buscar entidad..." />
      </div>

      <div class="lg:col-span-8">
        <x-forms.input label="Buscar por DNI o Cliente" name="q" value="{{ $q }}" placeholder="DNI o Cliente" />
      </div>

      <div class="flex flex-wrap items-center gap-2 lg:col-span-4 lg:justify-end">
        <x-ui.button type="button" variant="secondary" data-report-search>Buscar</x-ui.button>
        <x-ui.button type="button" variant="ghost" data-report-clear>Limpiar</x-ui.button>
        <div class="w-full text-sm text-kp-muted lg:w-auto" data-report-summary></div>
      </div>
    </form>
  </x-ui.card>

  @include('reportes.pdp_table', ['rows' => $rows])
</x-layout.page-shell>
@endsection
