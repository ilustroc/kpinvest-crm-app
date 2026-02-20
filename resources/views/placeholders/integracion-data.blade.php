@extends('layouts.app')
@section('title','Integración ▸ Data')
@section('crumb','Integración')

@push('head')
  @vite([
    'resources/css/integracion/data.css',
    'resources/js/integracion/data.js',
  ])
@endpush

@section('content')
<div class="space-y-4">

  {{-- Avisos (mismo modelo) --}}
  @if(session('ok'))
    <div class="kp-alert kp-alert-ok">
      <span class="kp-alert-ico">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 6 9 17l-5-5"/>
        </svg>
      </span>
      <div class="text-sm">{{ session('ok') }}</div>
    </div>
  @endif

  @if(session('warn'))
    <div class="kp-alert kp-alert-warn">
      <span class="kp-alert-ico">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 9v4"/><path d="M12 17h.01"/>
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
          <path d="M12 9v4"/><path d="M12 17h.01"/>
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
        </svg>
      </span>
      <div class="text-sm">{{ $errors->first() }}</div>
    </div>
  @endif

  {{-- Card --}}
  <div class="kp-card p-5">
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-base font-extrabold text-slate-900">Clientes (master)</h2>
        <div class="text-sm text-slate-500 mt-1">
          Cargar/actualizar la tabla única
          <span class="font-semibold text-slate-700">clientes_cuentas</span>.
        </div>
        <div class="text-xs text-slate-500 mt-2">
          Encabezados mínimos: <strong class="text-slate-700">NUMDOC, OPERACION, CUENTA, ENTIDAD, DPTO</strong> …
        </div>
      </div>

      <a class="kp-btn kp-btn-outline inline-flex items-center gap-2"
         href="{{ route('integracion.data.template') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <path d="M7 10l5 5 5-5"/>
          <path d="M12 15V3"/>
        </svg>
        Descargar plantilla CSV
      </a>
    </div>

    <div class="kp-divider my-4"></div>

    <form method="POST"
          action="{{ route('integracion.data.import') }}"
          enctype="multipart/form-data"
          id="formImportData"
          class="space-y-4">
      @csrf

      <div class="space-y-2">
        <label class="kp-label">Archivo CSV</label>

        <input type="file"
               name="archivo"
               id="csvFileData"
               accept=".csv,text/csv"
               required
               class="kp-input-file"/>

        <p class="text-xs text-slate-500">
          Encabezados: <strong class="text-slate-700">NUMDOC, OPERACION, CUENTA, ENTIDAD, DPTO, ...</strong>
        </p>
      </div>

      {{-- Precheck (igual modelo) --}}
      <div class="hidden" id="precheckBoxData">
        <div class="kp-divider my-3"></div>

        <div class="flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="kp-mini-ico kp-ok">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 6 9 17l-5-5"/>
              </svg>
            </span>
            <span class="kp-mini" id="hdrMsgData" aria-live="polite">Validando encabezados…</span>
          </div>

          <div class="flex items-center gap-2">
            <span class="kp-mini-ico kp-warn">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 9v4"/><path d="M12 17h.01"/>
              </svg>
            </span>
            <span class="kp-mini" id="typeMsgData" aria-live="polite">Muestra: —</span>
          </div>
        </div>

        <div class="mt-3 hidden" id="issuesWrapData">
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
              <tbody id="issuesBodyData" class="divide-y divide-slate-200/60"></tbody>
            </table>
          </div>
        </div>
      </div>

      <button type="submit"
              class="kp-btn kp-btn-primary w-full sm:w-auto inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              id="btnImportData"
              disabled>
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <path d="M7 10l5 5 5-5"/>
          <path d="M12 15V3"/>
        </svg>
        Subir y procesar
      </button>
    </form>
  </div>

</div>
@endsection