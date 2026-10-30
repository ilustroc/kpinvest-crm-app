@extends('layouts.app')
@section('title','Reportes - CNA')
@section('crumb','Reportes - CNA')
@section('tailwind_only', true)

@push('head')
  <meta name="rpt-cna-export" content="{{ route('reportes.cna.export') }}">
  <meta name="rpt-cna-facets" content="{{ route('reportes.cna.facets') }}">
@endpush

@section('content')
@php
    $fmtDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '—';
    $periodLabel = $fmtDate($from).' – '.$fmtDate($to);
    $estadoCount = count(array_filter((array) $estadoSel, fn ($value) => (string) $value !== ''));
    $gestorCount = count(array_filter((array) $gestorSel, fn ($value) => (string) $value !== ''));
    $entidadCount = count(array_filter((array) $entidadSel, fn ($value) => (string) $value !== ''));
    $filterChips = [];
    if ($estadoCount > 0) {
        $filterChips[] = $estadoCount === 1 ? 'Estado: '.$estadoSel[0] : 'Estado ('.$estadoCount.')';
    }
    if ($gestorCount > 0) {
        $filterChips[] = $gestorCount === 1 ? 'Gestor: '.$gestorSel[0] : 'Gestor ('.$gestorCount.')';
    }
    if ($entidadCount > 0) {
        $filterChips[] = $entidadCount === 1 ? 'Entidad: '.$entidadSel[0] : 'Entidad ('.$entidadCount.')';
    }
@endphp

<x-layout.page-shell data-module="reportes-cna">
  <x-layout.page-header
    title="Reporte CNA"
    subtitle="Consulta las cartas de no adeudo registradas y exporta la selección actual."
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

  <section class="rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <header class="flex flex-wrap items-center justify-between gap-3 rounded-t-[10px] border-b border-kp-border px-4 py-3">
      <div class="flex min-w-0 items-center gap-2.5">
        <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-kp-primary-soft text-kp-primary-ink">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18l-7.5 8.25V19.5l-3 1.5v-8.25L3 4.5Z" />
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="text-[13px] font-semibold text-kp-ink">Filtros de consulta</h2>
          <p class="text-[11px] text-kp-muted">Periodo, estado y responsables de la solicitud.</p>
        </div>
      </div>
      <span class="text-[11px] font-medium text-kp-muted" data-report-summary></span>
    </header>

    <form id="filtros" data-report-filters class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-12 xl:items-end" method="GET" action="{{ route('reportes.cna') }}">
      <div class="xl:col-span-2">
        <x-ui.date id="from" label="Desde" name="from" value="{{ $from }}" data-default="{{ $defaultFrom }}" />
      </div>

      <div class="xl:col-span-2">
        <x-ui.date id="to" label="Hasta" name="to" value="{{ $to }}" data-default="{{ $defaultTo }}" />
      </div>

      <div class="xl:col-span-2">
        <x-reportes.multiselect name="estado" title="Estado" empty="Todos" :options="$estados" :selected="$estadoSel" placeholder="Buscar estado..." />
      </div>

      <div class="xl:col-span-3">
        <x-reportes.multiselect name="gestor" title="Gestor" empty="Todos" :options="$gestores" :selected="$gestorSel" placeholder="Buscar gestor..." />
      </div>

      <div class="xl:col-span-3">
        <x-reportes.multiselect name="entidad" title="Entidad" empty="Todas" :options="$entidades" :selected="$entidadSel" placeholder="Buscar entidad..." />
      </div>

      <div class="col-span-full flex flex-col gap-3 border-t border-kp-border/70 pt-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-1.5" data-report-filter-chips aria-live="polite">
          <span class="inline-flex items-center rounded-md border border-kp-border bg-kp-surface-muted px-2 py-1 text-[10.5px] font-medium text-slate-600">Periodo: {{ $periodLabel }}</span>
          @foreach($filterChips as $chip)
            <span class="inline-flex items-center rounded-md border border-kp-primary-border bg-kp-primary-soft px-2 py-1 text-[10.5px] font-medium text-kp-primary-ink">{{ $chip }}</span>
          @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
          <x-ui.button type="button" data-report-search>
            <svg class="size-3.5" data-report-search-icon fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
            </svg>
            <span class="hidden size-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white" data-report-search-spinner aria-hidden="true"></span>
            <span data-report-search-label>Buscar</span>
          </x-ui.button>
          <x-ui.button type="button" variant="ghost" data-report-clear>Limpiar</x-ui.button>
        </div>
      </div>
    </form>
  </section>

  @include('reportes.cna_table', ['rows' => $rows])
</x-layout.page-shell>
@endsection
