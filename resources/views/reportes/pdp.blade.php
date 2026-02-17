@extends('layouts.app')
@section('title','Reportes ▸ Promesas')
@section('crumb','Reportes ▸ Promesas')

@push('head')
  <meta name="rpt-pdp-export" content="{{ route('reportes.pdp.export') }}">
  <meta name="rpt-pdp-facets" content="{{ route('reportes.pdp.facets') }}">
  <link rel="stylesheet" href="{{ asset('css/reportes/promesas.css') }}">
@endpush

@section('content')
<div class="card pad rpt-pdp">

  <form id="filtros" class="row g-2 align-items-end filters" method="GET" action="{{ route('reportes.pdp') }}">

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
    <div class="col-12 col-md-2">
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

    {{-- Tipo Neg --}}
    <div class="col-12 col-md-3">
      <label class="form-label">Tipo_Neg</label>
      <div class="dropdown w-100" data-multiselect="tipo" data-title="Tipo_Neg" data-empty="Todos">
        <button class="btn btn-ms dropdown-toggle w-100 text-start" type="button"
                data-bs-toggle="dropdown" data-bs-auto-close="outside" data-ms-button>
          Tipo_Neg: Todos
        </button>

        <div class="dropdown-menu p-2 w-100 shadow-sm">
          <input type="text" class="form-control form-control-sm mb-2" placeholder="Buscar tipo…" data-ms-search>
          <div class="ms-list" data-ms-list>
            @forelse($tipos as $v)
              <label class="ms-item">
                <input class="form-check-input" type="checkbox" name="tipo[]" value="{{ $v }}"
                      @checked(in_array($v, $tipoSel, true))>
                <span class="ms-text">{{ $v }}</span>
              </label>
            @empty
              <div class="text-muted small px-1">Sin tipos en este rango.</div>
            @endforelse
          </div>
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-ms-clear>Limpiar</button>
            <button type="button" class="btn btn-sm btn-success ms-auto" data-ms-apply>Aplicar</button>
          </div>
        </div>
      </div>
    </div>

    {{-- Entidad (subida y mismo tamaño que Tipo) --}}
    <div class="col-12 col-md-3">
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

    {{-- Buscar (abajo) --}}
    <div class="col-12">
      <label class="form-label">Buscar por DNI o Cliente</label>
      <div class="input-group">
        <input type="text" name="q" class="form-control"
              placeholder="DNI o Cliente" value="{{ $q }}">
        <button class="btn btn-outline-secondary" id="btnBuscar" type="button">
          <i class="bi bi-search"></i>
        </button>
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

  @include('reportes.pdp_table', ['rows' => $rows])

</div>
@endsection

@push('scripts')
  <script src="{{ asset('js/reportes/promesas.js') }}" defer></script>
@endpush
