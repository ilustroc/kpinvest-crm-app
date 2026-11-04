@extends('layouts.app')

@section('title', 'Integracion > Asignar Clientes')
@section('crumb', 'Integracion > Asignar Clientes')
@section('tailwind_only', true)

@section('content')
  <x-layout.page-shell data-module="integracion-imports">
    <x-layout.page-header
      title="Asignacion de clientes"
      subtitle="Importa asignaciones masivas de clientes a usuarios del sistema con validación previa e historial lineal."
    >
      <x-slot:actions>
        <x-ui.button href="{{ route('integracion.asignacion.template') }}" variant="secondary">
          Descargar plantilla CSV
        </x-ui.button>
      </x-slot:actions>
    </x-layout.page-header>

    @if(session('ok'))
      <x-feedback.alert variant="success">{{ session('ok') }}</x-feedback.alert>
    @endif

    @if(session('warn'))
      <x-feedback.alert variant="warning">
        <pre class="whitespace-pre-wrap font-sans text-sm">{{ session('warn') }}</pre>
      </x-feedback.alert>
    @endif

    @if($errors->any())
      <x-feedback.alert variant="danger">{{ $errors->first() }}</x-feedback.alert>
    @endif

    {{-- Vista Previa Diagnóstica --}}
    @if(session('asignacion_import_preview'))
      @php
        $prev = session('asignacion_import_preview');
        $rechazadosCount = count($prev['detalles_rechazados'] ?? []);
      @endphp
      <x-ui.card title="Diagnóstico de Vista Previa (Sin escribir en base de datos)" subtitle="Revisa el resultado de validación antes de confirmar la carga masiva.">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5 text-center">
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <span class="text-xs font-semibold text-emerald-800">Nuevas Asignaciones</span>
            <p class="mt-1 text-xl font-bold text-emerald-900">{{ number_format($prev['nuevas_asignaciones'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <span class="text-xs font-semibold text-slate-700">Sin Cambio Efectivo</span>
            <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($prev['sin_cambio_efectivo'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
            <span class="text-xs font-semibold text-amber-800">Cuenta Inexistente / Ambigua</span>
            <p class="mt-1 text-xl font-bold text-amber-900">{{ number_format(($prev['cuenta_inexistente'] ?? 0) + ($prev['cuenta_ambigua'] ?? 0)) }}</p>
          </div>
          <div class="rounded-lg border border-red-200 bg-red-50 p-3">
            <span class="text-xs font-semibold text-red-800">Asesor Inexistente / Ambiguo</span>
            <p class="mt-1 text-xl font-bold text-red-900">{{ number_format($prev['asesor_invalido'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-purple-200 bg-purple-50 p-3">
            <span class="text-xs font-semibold text-purple-800">Duplicados en Archivo</span>
            <p class="mt-1 text-xl font-bold text-purple-900">{{ number_format($prev['duplicados'] ?? 0) }}</p>
          </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-kp-border pt-4">
          @if($rechazadosCount > 0)
            <x-ui.button href="{{ route('integracion.asignacion.errors') }}" variant="secondary">
              Descargar reporte de filas rechazadas (CSV)
            </x-ui.button>
          @else
            <div></div>
          @endif

          <form method="POST" action="{{ route('integracion.asignacion.import') }}" data-import-form>
            @csrf
            <input type="hidden" name="temp_path" value="{{ session('asignacion_import_temp_path') }}">
            <x-ui.button type="submit" variant="primary" data-import-submit>
              Confirmar e importar asignaciones
            </x-ui.button>
          </form>
        </div>
      </x-ui.card>
    @endif

    {{-- Resumen de Ejecución --}}
    @if(session('asignacion_import_result'))
      @php
        $res = session('asignacion_import_result');
        $resRechazados = count($res['detalles_rechazados'] ?? []);
      @endphp
      <x-ui.card title="Resumen de Carga Masiva Ejecutada" subtitle="Registros creados con motivo 'carga masiva', origen 'carga_masiva' y estado 'aprobada'.">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5 text-center">
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <span class="text-xs font-semibold text-emerald-800">Nuevas Asignaciones</span>
            <p class="mt-1 text-xl font-bold text-emerald-900">{{ number_format($res['nuevas_asignaciones'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <span class="text-xs font-semibold text-slate-700">Sin Cambio Efectivo</span>
            <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($res['sin_cambio_efectivo'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
            <span class="text-xs font-semibold text-amber-800">Cuenta Inexistente / Ambigua</span>
            <p class="mt-1 text-xl font-bold text-amber-900">{{ number_format(($res['cuenta_inexistente'] ?? 0) + ($res['cuenta_ambigua'] ?? 0)) }}</p>
          </div>
          <div class="rounded-lg border border-red-200 bg-red-50 p-3">
            <span class="text-xs font-semibold text-red-800">Asesor Inválido</span>
            <p class="mt-1 text-xl font-bold text-red-900">{{ number_format($res['asesor_invalido'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-purple-200 bg-purple-50 p-3">
            <span class="text-xs font-semibold text-purple-800">Duplicados CSV</span>
            <p class="mt-1 text-xl font-bold text-purple-900">{{ number_format($res['duplicados'] ?? 0) }}</p>
          </div>
        </div>

        @if($resRechazados > 0)
          <div class="mt-4 flex items-center justify-end">
            <x-ui.button href="{{ route('integracion.asignacion.errors') }}" variant="secondary">
              Descargar reporte de filas rechazadas (CSV)
            </x-ui.button>
          </div>
        @endif
      </x-ui.card>
    @endif

    <x-ui.card title="Archivo de asignacion" subtitle="Campos requeridos: NUMDOC, OPERACION y NAME (nombre único o email del usuario en users).">
      <form
        method="POST"
        action="{{ route('integracion.asignacion.import') }}"
        enctype="multipart/form-data"
        class="space-y-4"
        data-import-form
      >
        @csrf

        <x-forms.field label="Archivo CSV" name="archivo">
          <input
            type="file"
            name="archivo"
            id="archivo"
            class="w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-kp-green-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-kp-primary-ink kp-focus"
            accept=".csv,text/csv"
            required
          >
          <p class="mt-1 text-xs text-kp-muted">Encabezados esperados: NUMDOC, OPERACION, NAME. Se conservan los ceros a la izquierda.</p>
        </x-forms.field>

        <div class="flex flex-wrap items-center gap-3">
          <x-ui.button type="submit" formaction="{{ route('integracion.asignacion.preview') }}" variant="secondary" data-import-submit>
            Generar vista previa
          </x-ui.button>
          <x-ui.button type="submit" formaction="{{ route('integracion.asignacion.import') }}" variant="primary" data-import-submit>
            Subir y procesar
          </x-ui.button>
        </div>
      </form>
    </x-ui.card>
  </x-layout.page-shell>
@endsection
