@extends('layouts.app')
@section('title','Integración ▸ Asignar Clientes')
@section('crumb','Integración')

@push('head')
  @vite(['resources/css/integracion/asignacion.css'])
@endpush

@section('content')
<div class="space-y-4">

  {{-- Avisos --}}
  @if(session('ok'))
    <div class="kp-alert kp-alert-ok">
      <span class="kp-alert-ico">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 6 9 17l-5-5"/>
        </svg>
      </span>
      <div>{{ session('ok') }}</div>
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
      <pre class="text-xs leading-relaxed whitespace-pre-wrap m-0">{{ session('warn') }}</pre>
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
      <div>{{ $errors->first() }}</div>
    </div>
  @endif

  {{-- Card --}}
  <div class="kp-card p-5 md:p-6">
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h2 class="text-base font-extrabold text-slate-900">Asignación de clientes</h2>
        <div class="text-sm text-slate-500 mt-1">
          Importa asignaciones masivas.
        </div>
      </div>

      <a href="{{ route('integracion.asignacion.template') }}"
         class="kp-btn kp-btn-soft inline-flex items-center gap-2">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
          <path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z"/>
          <path d="M9 9h1"/>
          <path d="M9 13h6"/>
          <path d="M9 17h6"/>
        </svg>
        Descargar plantilla CSV
      </a>
    </div>

    <div class="kp-divider my-4"></div>

    <form method="POST"
          action="{{ route('integracion.asignacion.import') }}"
          enctype="multipart/form-data"
          class="space-y-4">
      @csrf

      <div class="space-y-2">
        <label class="kp-label">Archivo CSV</label>

        <input type="file"
               name="archivo"
               accept=".csv,text/csv"
               required
               class="kp-input-file" />

        <p class="text-xs text-slate-500">
          Encabezados esperados:
          <strong class="text-slate-700">NUMDOC, OPERACION, NAME</strong>
        </p>
      </div>

      <button class="kp-btn kp-btn-primary w-full sm:w-auto inline-flex items-center gap-2">
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