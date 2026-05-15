@extends('layouts.app')

@section('title', 'Integracion > Data')
@section('crumb', 'Integracion > Data')
@section('tailwind_only', true)

@section('content')
  <x-layout.page-shell data-module="integracion-imports">
    <x-layout.page-header
      title="Data maestra"
      subtitle="Carga o actualiza clientes y cuentas desde CSV hacia clientes_cuentas."
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
            class="w-full rounded-md border border-kp-border bg-white px-3 py-2 text-sm text-kp-ink shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-kp-green-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-kp-green-dark kp-focus"
            accept=".csv,text/csv"
            required
          >
          <p class="mt-1 text-xs text-kp-muted">Encabezados: NUMDOC, OPERACION, CUENTA, ENTIDAD, DPTO, ...</p>
        </x-forms.field>

        <x-ui.button type="submit" data-import-submit>
          Subir y procesar
        </x-ui.button>
      </form>
    </x-ui.card>
  </x-layout.page-shell>
@endsection
