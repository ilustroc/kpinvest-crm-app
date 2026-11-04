@extends('layouts.app')

@section('title', 'Integracion > Data')
@section('crumb', 'Integracion > Data')
@section('tailwind_only', true)

@section('content')
  <x-layout.page-shell data-module="integracion-imports">
    <x-layout.page-header
      title="Data maestra"
      subtitle="Carga o actualiza clientes y cuentas desde CSV hacia clientes_cuentas con reconciliación en tres vías."
    >
      <x-slot:actions>
        <x-ui.button href="{{ route('integracion.data.template') }}" variant="secondary">
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
    @if(session('data_import_preview'))
      @php $prev = session('data_import_preview'); @endphp
      <x-ui.card title="Resultado de la Vista Previa (Sin escribir en BD)" subtitle="Huella de base de datos evaluada: {{ $prev['db_fingerprint'] ?? '-' }}">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5 text-center">
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <span class="text-xs font-semibold text-emerald-800">Nuevas (INSERT)</span>
            <p class="mt-1 text-xl font-bold text-emerald-900">{{ number_format($prev['insert'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
            <span class="text-xs font-semibold text-blue-800">Actualizar (UPDATE)</span>
            <p class="mt-1 text-xl font-bold text-blue-900">{{ number_format($prev['update'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <span class="text-xs font-semibold text-slate-700">Sin Cambios</span>
            <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($prev['sin_cambios'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
            <span class="text-xs font-semibold text-amber-800">En Cuarentena</span>
            <p class="mt-1 text-xl font-bold text-amber-900">{{ number_format($prev['cuarentena'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-purple-200 bg-purple-50 p-3">
            <span class="text-xs font-semibold text-purple-800">Duplicados CSV</span>
            <p class="mt-1 text-xl font-bold text-purple-900">{{ number_format($prev['duplicados'] ?? 0) }}</p>
          </div>
        </div>

        @if(!empty($prev['alertas']))
          <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">Alertas identificadas:</p>
            <ul class="mt-1 list-disc pl-4 space-y-1">
              @foreach(array_slice($prev['alertas'], 0, 5) as $alerta)
                <li>{{ $alerta }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-kp-border pt-4">
          @if(($prev['cuarentena'] ?? 0) > 0)
            <x-ui.button href="{{ route('integracion.data.errors') }}" variant="secondary">
              Descargar reporte de cuarentena (CSV)
            </x-ui.button>
          @else
            <div></div>
          @endif

          <form method="POST" action="{{ route('integracion.data.import') }}" data-import-form>
            @csrf
            <input type="hidden" name="temp_path" value="{{ session('data_import_temp_path') }}">
            <input type="hidden" name="db_fingerprint" value="{{ $prev['db_fingerprint'] ?? '' }}">
            <x-ui.button type="submit" variant="primary" data-import-submit>
              Confirmar e importar a base de datos
            </x-ui.button>
          </form>
        </div>
      </x-ui.card>
    @endif

    {{-- Resultado de Importación Ejecutada --}}
    @if(session('data_import_result'))
      @php $res = session('data_import_result'); @endphp
      <x-ui.card title="Resumen de Ejecución Realizada" subtitle="Operación completada bajo transacción atómica.">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 text-center">
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <span class="text-xs font-semibold text-emerald-800">Insertados</span>
            <p class="mt-1 text-xl font-bold text-emerald-900">{{ number_format($res['insert'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
            <span class="text-xs font-semibold text-blue-800">Actualizados</span>
            <p class="mt-1 text-xl font-bold text-blue-900">{{ number_format($res['update'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <span class="text-xs font-semibold text-slate-700">Sin Cambios</span>
            <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($res['sin_cambios'] ?? 0) }}</p>
          </div>
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
            <span class="text-xs font-semibold text-amber-800">Cuarentena</span>
            <p class="mt-1 text-xl font-bold text-amber-900">{{ number_format($res['cuarentena'] ?? 0) }}</p>
          </div>
        </div>

        @if(($res['cuarentena'] ?? 0) > 0)
          <div class="mt-4 flex items-center justify-end">
            <x-ui.button href="{{ route('integracion.data.errors') }}" variant="secondary">
              Descargar reporte de cuarentena (CSV)
            </x-ui.button>
          </div>
        @endif
      </x-ui.card>
    @endif

    <x-ui.card title="Clientes master" subtitle="Campos esperados: NUMDOC, OPERACION, CUENTA, ENTIDAD, DPTO y columnas del maestro.">
      <form
        method="POST"
        action="{{ route('integracion.data.import') }}"
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
          <p class="mt-1 text-xs text-kp-muted">Encabezados requeridos: NUMDOC, OPERACION, CUENTA, ENTIDAD, DPTO, ...</p>
        </x-forms.field>

        <div class="flex flex-wrap items-center gap-3">
          <x-ui.button type="submit" formaction="{{ route('integracion.data.preview') }}" variant="secondary" data-import-submit>
            Generar vista previa
          </x-ui.button>
          <x-ui.button type="submit" formaction="{{ route('integracion.data.import') }}" variant="primary" data-import-submit>
            Subir y procesar directamente
          </x-ui.button>
        </div>
      </form>
    </x-ui.card>
  </x-layout.page-shell>
@endsection
