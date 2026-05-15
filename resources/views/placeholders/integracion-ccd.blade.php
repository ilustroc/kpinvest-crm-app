@extends('layouts.app')

@section('title', 'Integracion > CCD')
@section('crumb', 'Integracion > CCD')
@section('tailwind_only', true)

@section('content')
  <x-layout.page-shell data-module="integracion-imports">
    <x-layout.page-header
      title="Clientes CCD"
      subtitle="Carga o actualiza clientes CCD y sus referencias PDF desde CSV."
    >
      <x-slot:actions>
        <x-ui.button href="{{ route('integracion.ccd.template') }}" variant="secondary">
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

    <x-ui.card title="Archivo CCD" subtitle="Campos esperados: dni, nombre, cartera y pdf.">
      <form
        method="POST"
        action="{{ route('integracion.ccd.import') }}"
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
          <p class="mt-1 text-xs text-kp-muted">Encabezados esperados: dni, nombre, cartera, pdf.</p>
        </x-forms.field>

        <x-ui.button type="submit" data-import-submit>
          Subir y procesar
        </x-ui.button>
      </form>
    </x-ui.card>
  </x-layout.page-shell>
@endsection
