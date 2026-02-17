@extends('layouts.app')
@section('title','Reportes ▸ CNA')
@section('crumb','Reportes ▸ CNA')

@push('head')
  <meta name="rpt-cna-export" content="{{ route('reportes.cna.export') }}">
  <meta name="rpt-cna-facets" content="{{ route('reportes.cna.facets') }}">
  <link rel="stylesheet" href="{{ asset('css/reportes/cna.css') }}">
@endpush

@section('content')
<div class="card pad rpt-cna">

  <form id="filtros" class="row g-2 align-items-end filters" method="GET" action="{{ route('reportes.cna') }}">

    <div class="col-6 col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="from" class="form-control"
             value="{{ $from }}" data-default="{{ $defaultFrom }}">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="to" class="form-control"
             value="{{ $to }}" data-default="{{ $defaultTo }}">
    </div>

    {{-- Estado --}}
    <div class="col-12 col-md-3">
      <label class="form-label">Estado</label>
      <div class="dropdown w-100" data-multiselect="estado" data-title="Estado" data-empty="Todos">
        <button class="btn btn-ms dropdown-toggle w-100 text-start" type="button"
                data-bs-toggle="dropdown" data-bs-auto-close="outside" data-ms-button>
          Estado: Todos
        </button>

        <div class="dropdown-menu p-2 w-100 shadow-sm">
          <input type="text" class="form-control form-control-sm mb-2" placeholder="Buscar estado…" data-ms-search>
          <div class="ms-list" data-ms-list>
            @forelse($estados as $v)
              <label class="ms-item">
                <input class="form-check-input" type="checkbox" name="estado[]" value="{{ $v }}"
                       @checked(in_array($v, $estadoSel, true))>
                <span class="ms-text">{{ $v }}</span>
              </label>
            @empty
              <div class="text-muted small px-1">Sin estados en este rango.</div>
            @endforelse
          </div>
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-ms-clear>Limpiar</button>
            <button type="button" class="btn btn-sm btn-success ms-auto" data-ms-apply>Aplicar</button>
          </div>
        </div>
      </div>
    </div>

    {{-- Gestor --}}
    <div class="col-12 col-md-3">
      <label class="form-label">Gestor</label>
      <div class="dropdown w-100" data-multiselect="gestor" data-title="Gestor" data-empty="Todos">
        <button class="btn btn-ms dropdown-toggle w-100 text-start" type="button"
                data-bs-toggle="dropdown" data-bs-auto-close="outside" data-ms-button>
          Gestor: Todos
        </button>

        <div class="dropdown-menu p-2 w-100 shadow-sm">
          <input type="text" class="form-control form-control-sm mb-2" placeholder="Buscar gestor…" data-ms-search>
          <div class="ms-list" data-ms-list>
            @forelse($gestores as $v)
              <label class="ms-item">
                <input class="form-check-input" type="checkbox" name="gestor[]" value="{{ $v }}"
                       @checked(in_array($v, $gestorSel, true))>
                <span class="ms-text">{{ $v }}</span>
              </label>
            @empty
              <div class="text-muted small px-1">Sin gestores en este rango.</div>
            @endforelse
          </div>
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-ms-clear>Limpiar</button>
            <button type="button" class="btn btn-sm btn-success ms-auto" data-ms-apply>Aplicar</button>
          </div>
        </div>
      </div>
    </div>

    {{-- Entidad --}}
    <div class="col-12 col-md-4">
      <label class="form-label">Entidad</label>
      <div class="dropdown w-100" data-multiselect="entidad" data-title="Entidad" data-empty="Todas">
        <button class="btn btn-ms dropdown-toggle w-100 text-start" type="button"
                data-bs-toggle="dropdown" data-bs-auto-close="outside" data-ms-button>
          Entidad: Todas
        </button>

        <div class="dropdown-menu p-2 w-100 shadow-sm">
          <input type="text" class="form-control form-control-sm mb-2" placeholder="Buscar entidad…" data-ms-search>
          <div class="ms-list" data-ms-list>
            @forelse($entidades as $v)
              <label class="ms-item">
                <input class="form-check-input" type="checkbox" name="entidad[]" value="{{ $v }}"
                       @checked(in_array($v, $entidadSel, true))>
                <span class="ms-text">{{ $v }}</span>
              </label>
            @empty
              <div class="text-muted small px-1">Sin entidades en este rango.</div>
            @endforelse
          </div>
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-ms-clear>Limpiar</button>
            <button type="button" class="btn btn-sm btn-success ms-auto" data-ms-apply>Aplicar</button>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="toolbar mt-1">
        <button class="btn btn-clean" id="btnLimpiar" type="button">
          <i class="bi bi-eraser me-1"></i> Limpiar
        </button>

        <div class="spacer"></div>
        <div id="summary" class="tiny"></div>

        <a class="btn btn-export" id="btnExport" href="#">
          <i class="bi bi-download me-1"></i> Exportar
        </a>
      </div>
    </div>
  </form>

  <hr class="my-3">

  @include('reportes.cna_table', ['rows' => $rows])

</div>
@endsection

@push('scripts')
  <script src="{{ asset('js/reportes/cna.js') }}" defer></script>
@endpush
