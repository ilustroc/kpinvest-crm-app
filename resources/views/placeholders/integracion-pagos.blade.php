@extends('layouts.app')

@section('title', 'Integracion > Subir Pagos')
@section('crumb', 'Integracion > Subir Pagos')
@section('tailwind_only', true)

@section('content')
  @php
    $ultimoLote = $ultimoLote ?? ($ultimoLotePropia ?? null);
    $pagos = $pagos ?? ($pagosPropia ?? collect());
  @endphp

  <x-layout.page-shell data-module="integracion-imports">
    <x-layout.page-header
      title="Subir pagos"
      subtitle="Carga pagos desde un archivo CSV local y revisa el ultimo lote importado."
    >
      <x-slot:actions>
        <x-ui.button href="{{ route('integracion.pagos.template') }}" variant="secondary">
          Descargar plantilla CSV
        </x-ui.button>
      </x-slot:actions>
    </x-layout.page-header>

    @if(session('ok'))
      <x-feedback.alert variant="success">{!! nl2br(e(session('ok'))) !!}</x-feedback.alert>
    @endif

    @if(session('warn'))
      <x-feedback.alert variant="warning">
        <pre class="whitespace-pre-wrap font-sans text-sm">{{ session('warn') }}</pre>
      </x-feedback.alert>
    @endif

    @if($errors->any())
      <x-feedback.alert variant="danger">{{ $errors->first() }}</x-feedback.alert>
    @endif

    <x-ui.card title="Subida de archivo" subtitle="Formato aceptado: CSV UTF-8 delimitado por coma.">
      <form
        method="POST"
        action="{{ route('integracion.pagos.import') }}"
        enctype="multipart/form-data"
        id="formImportPagos"
        class="space-y-4"
      >
        @csrf

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px] lg:items-end">
          <div class="space-y-2">
            <x-forms.label for="csvFilePagos">Archivo CSV</x-forms.label>
            <input
              type="file"
              name="archivo"
              id="csvFilePagos"
              class="w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-kp-green-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-kp-green-dark kp-focus"
              accept=".csv,text/csv"
              required
            >
            <x-forms.error name="archivo" />

            <div class="flex flex-wrap gap-2 text-xs text-kp-muted">
              <span class="font-semibold text-kp-ink">Encabezados:</span>
              @foreach(['Fecha', 'DNI', 'Nombre', 'Operacion', 'Monto', 'Agente', 'Cosecha', 'Cuenta_Recaudo', 'Entidad Financiera'] as $header)
                <x-ui.badge>{{ $header }}</x-ui.badge>
              @endforeach
            </div>
          </div>

          <x-ui.button type="submit" id="btnImportPagos" class="w-full" disabled>
            Importar
          </x-ui.button>
        </div>

        <div id="precheckBoxPagos" class="hidden rounded-lg border border-dashed border-kp-border bg-slate-50 p-4">
          <div class="flex flex-wrap items-center gap-4 text-sm">
            <div class="flex items-center gap-2">
              <span class="size-2 rounded-full bg-emerald-500"></span>
              <span id="hdrMsgPagos" class="text-kp-muted" aria-live="polite">Validando encabezados...</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="size-2 rounded-full bg-amber-500"></span>
              <span id="typeMsgPagos" class="text-kp-muted" aria-live="polite">Tipos por muestra: -</span>
            </div>
          </div>

          <div id="issuesWrapPagos" class="mt-3 hidden">
            <x-tables.table>
              <thead>
                <tr>
                  <x-tables.th>Fila</x-tables.th>
                  <x-tables.th>Columna</x-tables.th>
                  <x-tables.th>Valor</x-tables.th>
                  <x-tables.th>Detalle</x-tables.th>
                </tr>
              </thead>
              <tbody id="issuesBodyPagos"></tbody>
            </x-tables.table>
          </div>
        </div>
      </form>
    </x-ui.card>

    <x-ui.card>
      <div class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 class="text-base font-bold text-kp-ink">Ultimo lote importado</h2>
          <p class="text-sm text-kp-muted">Vista rapida de los registros mas recientes.</p>
        </div>

        @if($ultimoLote)
          <x-ui.badge>
            Lote #{{ $ultimoLote->id }} - {{ $ultimoLote->created_at->format('Y-m-d H:i') }} - {{ $ultimoLote->total_registros }} registros
          </x-ui.badge>
        @endif
      </div>

      @if(!$ultimoLote)
        <x-feedback.empty-state title="Sin importaciones" message="Aun no hay lotes de pagos cargados." />
      @else
        <x-tables.table>
          <thead>
            <tr>
              <x-tables.th>Fecha</x-tables.th>
              <x-tables.th>DNI</x-tables.th>
              <x-tables.th>Operacion</x-tables.th>
              <x-tables.th>Nombre</x-tables.th>
              <x-tables.th>Entidad Financiera</x-tables.th>
              <x-tables.th align="right">Monto</x-tables.th>
              <x-tables.th>Agente</x-tables.th>
              <x-tables.th>Cosecha</x-tables.th>
              <x-tables.th>Cuenta_Recaudo</x-tables.th>
            </tr>
          </thead>
          <tbody>
            @forelse($pagos as $p)
              <tr>
                <x-tables.td>{{ optional($p->fecha)->format('Y-m-d') }}</x-tables.td>
                <x-tables.td>{{ $p->dni }}</x-tables.td>
                <x-tables.td>{{ $p->operacion }}</x-tables.td>
                <x-tables.td class="min-w-56">{{ $p->nombre_cliente }}</x-tables.td>
                <x-tables.td>{{ $p->entidad }}</x-tables.td>
                <x-tables.td align="right">{{ number_format((float) $p->monto_pagado, 2) }}</x-tables.td>
                <x-tables.td>{{ $p->gestor }}</x-tables.td>
                <x-tables.td>{{ $p->cosecha }}</x-tables.td>
                <x-tables.td>{{ $p->cuenta_recaudo }}</x-tables.td>
              </tr>
            @empty
              <x-tables.empty-row colspan="9" />
            @endforelse
          </tbody>
        </x-tables.table>
      @endif
    </x-ui.card>
  </x-layout.page-shell>
@endsection
