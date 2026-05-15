@extends('layouts.app')
@section('title','Reportes - Pagos')
@section('crumb','Reportes - Pagos')
@section('tailwind_only', true)

@push('head')
  <meta name="rpt-pagos-export" content="{{ route('reportes.pagos.export') }}">
  <meta name="rpt-pagos-facets" content="{{ route('reportes.pagos.facets') }}">
@endpush

@section('content')
<x-layout.page-shell data-module="reportes-pagos">
  <x-layout.page-header
    title="Reporte de pagos"
    subtitle="Consulta pagos por fecha, gestor, cosecha, entidad o DNI.">
    <x-slot:actions>
      <x-ui.button href="#" variant="secondary" data-report-export>
        Exportar
      </x-ui.button>
    </x-slot:actions>
  </x-layout.page-header>

  <x-ui.card>
    <form id="filtros" data-report-filters class="grid gap-3 lg:grid-cols-12 lg:items-end" method="GET" action="{{ route('reportes.pagos') }}">
      <div class="lg:col-span-2">
        <x-forms.date label="Desde" name="from" value="{{ $from }}" data-default="{{ $defaultFrom }}" />
      </div>

      <div class="lg:col-span-2">
        <x-forms.date label="Hasta" name="to" value="{{ $to }}" data-default="{{ $defaultTo }}" />
      </div>

      <div class="lg:col-span-3">
        <x-reportes.multiselect name="gestor" title="Gestor" empty="Todos" :options="$gestores" :selected="$gestorSel" placeholder="Buscar gestor..." />
      </div>

      <div class="lg:col-span-3">
        <x-reportes.multiselect name="entidad" title="Entidad" empty="Todas" :options="$entidades" :selected="$entidadSel" placeholder="Buscar entidad..." />
      </div>

      <div class="lg:col-span-2">
        <x-reportes.multiselect name="cosecha" title="Cosecha" empty="Todas" :options="$cosechas" :selected="$cosechaSel" placeholder="Buscar cosecha..." />
      </div>

      <div class="lg:col-span-8">
        <x-forms.input label="Buscar (DNI / Cliente)" name="q" value="{{ $q }}" placeholder="Ej: 40695493 o MEJIA" />
      </div>

      <div class="flex flex-wrap items-center gap-2 lg:col-span-4 lg:justify-end">
        <x-ui.button type="button" variant="secondary" data-report-search>Buscar</x-ui.button>
        <x-ui.button type="button" variant="ghost" data-report-clear>Limpiar</x-ui.button>
        <div class="w-full text-sm text-kp-muted lg:w-auto" data-report-summary></div>
      </div>
    </form>
  </x-ui.card>

  @include('reportes.pagos_table', ['rows' => $rows])
</x-layout.page-shell>
@endsection
