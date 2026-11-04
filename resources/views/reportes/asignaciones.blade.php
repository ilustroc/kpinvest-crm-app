@extends('layouts.app')
@section('title', 'Reportes - Asignaciones')
@section('crumb', 'Reportes - Asignaciones')
@section('tailwind_only', true)

@section('content')
<x-layout.page-shell data-module="reportes-asignaciones">
  <x-layout.page-header title="Reporte de Asignaciones" subtitle="Historial completo de solicitudes manuales y cargas masivas.">
    <x-slot:actions><x-ui.button href="{{ route('reportes.asignaciones.export', request()->query()) }}" variant="secondary">Exportar Excel</x-ui.button></x-slot:actions>
  </x-layout.page-header>

  <section class="rounded-[10px] border border-kp-border bg-white p-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <form method="GET" class="grid gap-3 md:grid-cols-3 xl:grid-cols-7 xl:items-end">
      <x-ui.date id="from" label="Desde" name="from" :value="$filters['from'] ?? ''" />
      <x-ui.date id="to" label="Hasta" name="to" :value="$filters['to'] ?? ''" />
      <x-ui.input label="Documento" name="numdoc" :value="$filters['numdoc'] ?? ''" />
      <x-ui.input label="Operación" name="operacion" :value="$filters['operacion'] ?? ''" />
      <x-ui.input label="Asesor" name="asesor" :value="$filters['asesor'] ?? ''" />
      <div><label class="block text-[11px] font-semibold text-kp-muted">Estado</label><select name="estado" class="mt-1 h-10 w-full rounded-md border border-kp-border bg-white px-3 text-sm"><option value="">Todos</option>@foreach(['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'] as $value => $label)<option value="{{ $value }}" @selected(($filters['estado'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
      <div><label class="block text-[11px] font-semibold text-kp-muted">Origen</label><select name="origen" class="mt-1 h-10 w-full rounded-md border border-kp-border bg-white px-3 text-sm"><option value="">Todos</option><option value="manual" @selected(($filters['origen'] ?? '') === 'manual')>Manual</option><option value="carga_masiva" @selected(($filters['origen'] ?? '') === 'carga_masiva')>Carga masiva</option></select></div>
      <div class="flex gap-2"><x-ui.button type="submit">Buscar</x-ui.button><x-ui.button href="{{ route('reportes.asignaciones') }}" variant="ghost">Limpiar</x-ui.button></div>
    </form>
  </section>

  <section class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <header class="flex items-center justify-between border-b border-kp-border px-4 py-3"><div><h2 class="text-[15px] font-semibold text-kp-ink">Historial de asignaciones</h2><p class="text-[11px] text-kp-muted">Orden: fecha de solicitud descendente e ID descendente.</p></div><span class="text-[11px] text-kp-muted">{{ number_format($rows->total()) }} registros</span></header>
    <x-tables.table :flush="true" :contained="true" min-width="min-w-[1600px]"><thead><tr>@foreach(['ID','Fecha solicitud','Documento','Cliente','Operación','Solicitante','Asesor destino','Motivo','Origen','Estado','Usuario que resolvió','Fecha resolución'] as $heading)<x-tables.th>{{ $heading }}</x-tables.th>@endforeach</tr></thead><tbody class="divide-y divide-kp-border bg-white">@forelse($rows as $row)<tr class="hover:bg-slate-50/80">@foreach([$row->id, $row->fecha_solicitud, $row->numdoc, $row->cliente, $row->operacion, $row->solicitante, $row->asesor_destino, $row->motivo, $row->origen === 'manual' ? 'Manual' : 'Carga masiva', $row->estado, $row->resuelto_por ?: '—', $row->fecha_aprobacion ?: '—'] as $value)<x-tables.td>{{ $value ?: '—' }}</x-tables.td>@endforeach</tr>@empty<x-tables.empty-row colspan="12" message="No hay asignaciones para los filtros indicados." />@endforelse</tbody></x-tables.table>
    <x-tables.pagination :paginator="$rows" />
  </section>
</x-layout.page-shell>
@endsection
