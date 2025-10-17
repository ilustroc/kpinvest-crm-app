@extends('layouts.app')
@section('title','Integración ▸ CCD')

@section('content')
<h1 class="h4 mb-3">Integración ▸ CCD</h1>

{{-- Avisos --}}
@if(session('ok'))   <div class="alert alert-success">{{ session('ok') }}</div>@endif
@if(session('warn')) <pre class="alert alert-warning small mb-3">{{ session('warn') }}</pre>@endif
@if($errors->any())  <div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card pad">
  <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
    <div>
      <h2 class="h6 mb-1">Clientes CCD</h2>
      <div class="text-muted small">Cargar/actualizar la tabla <code>ccd</code> con los datos de clientes y PDF.</div>
    </div>
    <a class="btn btn-outline-primary"
       href="{{ route('integracion.ccd.template') }}">
       Descargar plantilla CSV
    </a>
  </div>

  <hr class="my-3">

  <form class="vstack gap-2"
        method="POST"
        action="{{ route('integracion.ccd.import') }}"
        enctype="multipart/form-data">
    @csrf
    <div>
      <label class="form-label">Archivo CSV</label>
      <input type="file" name="archivo" class="form-control" accept=".csv,text/csv" required>
      <div class="form-text">Encabezados: id, codigo, dni, nombre, cartera, pdf</div>
    </div>
    <button class="btn btn-primary">
      Subir y procesar
    </button>
  </form>
</div>
@endsection
