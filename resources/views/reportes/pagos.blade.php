@extends('layouts.app')
@section('title','Reportes - Pagos')
@section('crumb','Reportes - Pagos')
@section('tailwind_only', true)

@push('head')
  <meta name="rpt-pagos-export" content="{{ route('reportes.pagos.export') }}">
  <meta name="rpt-pagos-facets" content="{{ route('reportes.pagos.facets') }}">
@endpush

@section('content')
@php
    $fmtDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '—';
    $periodLabel = $fmtDate($from).' – '.$fmtDate($to);
    $gestorCount = count(array_filter((array) $gestorSel, fn ($value) => (string) $value !== ''));
    $entidadCount = count(array_filter((array) $entidadSel, fn ($value) => (string) $value !== ''));
    $cosechaCount = count(array_filter((array) $cosechaSel, fn ($value) => (string) $value !== ''));
    $q = trim((string) $q);
    $filterChips = [];
    if ($gestorCount > 0) {
        $filterChips[] = $gestorCount === 1 ? 'Gestor: '.$gestorSel[0] : 'Gestor ('.$gestorCount.')';
    }
    if ($entidadCount > 0) {
        $filterChips[] = $entidadCount === 1 ? 'Entidad: '.$entidadSel[0] : 'Entidad ('.$entidadCount.')';
    }
    if ($cosechaCount > 0) {
        $filterChips[] = $cosechaCount === 1 ? 'Cosecha: '.$cosechaSel[0] : 'Cosecha ('.$cosechaCount.')';
    }
    if ($q !== '') {
        $filterChips[] = 'Búsqueda: '.$q;
    }
@endphp

<x-layout.page-shell data-module="reportes-pagos">
  <x-layout.page-header
    title="Reporte de pagos"
    subtitle="Consulta los pagos registrados y exporta la selección actual."
  >
    <x-slot:actions>
      <x-ui.button href="#" variant="secondary" data-report-export>
        <svg class="size-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5M4.5 18.75v1.5h15v-1.5" />
        </svg>
        Exportar
      </x-ui.button>
    </x-slot:actions>
  </x-layout.page-header>

  <section class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3">
      <div class="flex min-w-0 items-center gap-2.5">
        <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18l-7.5 8.25V19.5l-3 1.5v-8.25L3 4.5Z" />
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="text-[13px] font-semibold text-kp-ink">Filtros de consulta</h2>
          <p class="text-[11px] text-kp-muted">Periodo, cartera y datos del cliente.</p>
        </div>
      </div>
      <span class="text-[11px] font-medium text-kp-muted" data-report-summary></span>
    </header>

    <form id="filtros" data-report-filters class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-12 xl:items-end" method="GET" action="{{ route('reportes.pagos') }}">
      <div class="xl:col-span-2">
        <x-ui.date id="from" label="Desde" name="from" value="{{ $from }}" data-default="{{ $defaultFrom }}" />
      </div>

      <div class="xl:col-span-2">
        <x-ui.date id="to" label="Hasta" name="to" value="{{ $to }}" data-default="{{ $defaultTo }}" />
      </div>

      <div class="xl:col-span-2">
        <x-reportes.multiselect name="gestor" title="Gestor" empty="Todos" :options="$gestores" :selected="$gestorSel" placeholder="Buscar gestor..." />
      </div>

      <div class="xl:col-span-3">
        <x-reportes.multiselect name="entidad" title="Entidad" empty="Todas" :options="$entidades" :selected="$entidadSel" placeholder="Buscar entidad..." />
      </div>

      <div class="xl:col-span-3">
        <x-reportes.multiselect name="cosecha" title="Cosecha" empty="Todas" :options="$cosechas" :selected="$cosechaSel" placeholder="Buscar cosecha..." />
      </div>

      <div class="md:col-span-2 xl:col-span-8">
        <x-ui.input id="q" label="Buscar por DNI o cliente" name="q" value="{{ $q }}" placeholder="Ej. 40695493 o MEJIA" />
      </div>

      <div class="flex flex-wrap items-center gap-2 md:col-span-2 xl:col-span-4 xl:justify-end">
        <x-ui.button type="button" data-report-search>
          <svg class="size-3.5" data-report-search-icon fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
          </svg>
          <span class="hidden size-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white" data-report-search-spinner aria-hidden="true"></span>
          <span data-report-search-label>Buscar</span>
        </x-ui.button>
        <x-ui.button type="button" variant="ghost" data-report-clear>Limpiar</x-ui.button>
      </div>

      <div class="col-span-full flex flex-wrap items-center gap-1.5 border-t border-kp-border/70 pt-3" data-report-filter-chips aria-live="polite">
        <span class="inline-flex items-center rounded-md border border-kp-border bg-kp-surface-muted px-2 py-1 text-[10.5px] font-medium text-slate-600">Periodo: {{ $periodLabel }}</span>
        @foreach($filterChips as $chip)
          <span class="inline-flex items-center rounded-md border border-kp-primary-border bg-kp-primary-soft px-2 py-1 text-[10.5px] font-medium text-kp-primary-ink">{{ $chip }}</span>
        @endforeach
      </div>
    </form>
  </section>

  @include('reportes.pagos_table', ['rows' => $rows])
</x-layout.page-shell>
@endsection
