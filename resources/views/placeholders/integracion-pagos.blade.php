@extends('layouts.app')

@section('title','Integración ▸ Subir Pagos')
@section('crumb','Integración')

@push('head')
  @vite([
    'resources/css/integracion/pagos.css',
    'resources/js/integracion/pagos.js',
  ])
@endpush

@section('content')
@php
  $ultimoLote = $ultimoLote ?? ($ultimoLotePropia ?? null);
  $pagos      = $pagos ?? ($pagosPropia ?? collect());
@endphp

<div class="space-y-4">

  {{-- ALERTAS (mismo modelo que Asignación) --}}
  @if(session('ok'))
    <div class="kp-alert kp-alert-ok">
      <span class="kp-alert-ico">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 6 9 17l-5-5"/>
        </svg>
      </span>
      <div class="text-sm leading-relaxed">{!! nl2br(e(session('ok'))) !!}</div>
    </div>
  @endif

  @if(session('warn'))
    <div class="kp-alert kp-alert-warn">
      <span class="kp-alert-ico">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 9v4"/>
          <path d="M12 17h.01"/>
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
        </svg>
      </span>
      <pre class="m-0 text-xs leading-relaxed whitespace-pre-wrap">{{ session('warn') }}</pre>
    </div>
  @endif

  @if($errors->any())
    <div class="kp-alert kp-alert-bad">
      <span class="kp-alert-ico">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 9v4"/>
          <path d="M12 17h.01"/>
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
        </svg>
      </span>
      <div class="text-sm">{{ $errors->first() }}</div>
    </div>
  @endif

  {{-- CARD: SUBIDA (igual patrón de Asignación) --}}
  <div class="kp-card p-5">
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-base font-extrabold text-slate-900">Subida de pagos</h2>
        <div class="text-sm text-slate-500 mt-1">
          Formato aceptado: <span class="font-semibold text-slate-700">CSV UTF-8</span> (coma).
        </div>
      </div>

      <a class="kp-btn kp-btn-outline inline-flex items-center gap-2"
         href="{{ route('integracion.pagos.template') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <path d="M7 10l5 5 5-5"/>
          <path d="M12 15V3"/>
        </svg>
        Descargar plantilla
      </a>
    </div>

    <div class="kp-divider my-4"></div>

    <form method="POST"
          action="{{ route('integracion.pagos.import') }}"
          enctype="multipart/form-data"
          id="formImportPagos"
          class="grid grid-cols-1 lg:grid-cols-10 gap-3 items-end">
      @csrf

      <div class="lg:col-span-7 space-y-2">
        <label class="kp-label">Archivo CSV</label>

        <input type="file"
               name="archivo"
               id="csvFilePagos"
               accept=".csv,text/csv"
               required
               class="kp-input-file"/>

        <div class="text-xs text-slate-500 leading-relaxed">
          Encabezados esperados:
          <span class="kp-pill">Fecha</span>
          <span class="kp-pill">DNI</span>
          <span class="kp-pill">Nombre</span>
          <span class="kp-pill">Operacion</span>
          <span class="kp-pill">Monto</span>
          <span class="kp-pill">Agente</span>
          <span class="kp-pill">Cosecha</span>
          <span class="kp-pill">Cuenta_Recaudo</span>
          <span class="kp-pill">Entidad Financiera</span>
        </div>
      </div>

      <div class="lg:col-span-3">
        <button type="submit"
                class="kp-btn kp-btn-primary w-full inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                id="btnImportPagos"
                disabled>
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <path d="M7 10l5 5 5-5"/>
            <path d="M12 15V3"/>
          </svg>
          Importar
        </button>
      </div>

      {{-- PRECHECK --}}
      <div class="lg:col-span-10 hidden" id="precheckBoxPagos">
        <div class="kp-divider my-3"></div>

        <div class="flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="kp-mini-ico kp-ok">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 6 9 17l-5-5"/>
              </svg>
            </span>
            <span class="kp-mini" id="hdrMsgPagos" aria-live="polite">Validando encabezados…</span>
          </div>

          <div class="flex items-center gap-2">
            <span class="kp-mini-ico kp-warn">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19V5"/>
                <path d="M20 19V5"/>
                <path d="M4 12h16"/>
                <path d="M8 9h.01"/>
                <path d="M8 15h.01"/>
              </svg>
            </span>
            <span class="kp-mini" id="typeMsgPagos" aria-live="polite">Tipos por muestra: —</span>
          </div>
        </div>

        <div class="mt-3 hidden" id="issuesWrapPagos">
          <div class="kp-table">
            <table class="min-w-full text-sm">
              <thead class="kp-thead">
                <tr>
                  <th class="px-3 py-2 text-left">Fila</th>
                  <th class="px-3 py-2 text-left">Columna</th>
                  <th class="px-3 py-2 text-left">Valor</th>
                  <th class="px-3 py-2 text-left">Detalle</th>
                </tr>
              </thead>
              <tbody id="issuesBodyPagos" class="divide-y divide-slate-200/60"></tbody>
            </table>
          </div>
        </div>
      </div>
    </form>
  </div>

  {{-- ÚLTIMO LOTE --}}
  <div class="kp-card p-5">
    <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
      <h2 class="text-base font-extrabold text-slate-900">Último lote importado</h2>

      @if($ultimoLote)
        <div class="text-xs text-slate-500">
          Lote <span class="font-semibold text-slate-700">#{{ $ultimoLote->id }}</span>
          · {{ $ultimoLote->created_at->format('Y-m-d H:i') }}
          · {{ $ultimoLote->total_registros }} registros
        </div>
      @endif
    </div>

    @if(!$ultimoLote)
      <div class="text-sm text-slate-500">Aún no hay importaciones.</div>
    @else
      <div class="kp-table">
        <table class="min-w-full text-sm">
          <thead class="kp-thead">
            <tr>
              <th class="px-3 py-2 text-left">Fecha</th>
              <th class="px-3 py-2 text-left">DNI</th>
              <th class="px-3 py-2 text-left">Operacion</th>
              <th class="px-3 py-2 text-left">Nombre</th>
              <th class="px-3 py-2 text-left">Entidad Financiera</th>
              <th class="px-3 py-2 text-right">Monto</th>
              <th class="px-3 py-2 text-left">Agente</th>
              <th class="px-3 py-2 text-left">Cosecha</th>
              <th class="px-3 py-2 text-left">Cuenta_Recaudo</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200/60">
            @forelse($pagos as $p)
              <tr class="hover:bg-emerald-50/40">
                <td class="px-3 py-2 whitespace-nowrap">{{ optional($p->fecha)->format('Y-m-d') }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ $p->dni }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ $p->operacion }}</td>
                <td class="px-3 py-2">{{ $p->nombre_cliente }}</td>
                <td class="px-3 py-2">{{ $p->entidad }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ number_format((float)$p->monto_pagado, 2) }}</td>
                <td class="px-3 py-2">{{ $p->gestor }}</td>
                <td class="px-3 py-2">{{ $p->cosecha }}</td>
                <td class="px-3 py-2">{{ $p->cuenta_recaudo }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="px-3 py-4 text-sm text-slate-500">Sin datos para mostrar.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endif
  </div>

</div>
@endsection