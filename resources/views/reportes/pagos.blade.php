{{-- resources/views/reportes/pagos.blade.php --}}
@extends('layouts.app')
@section('title','Reportes ▸ Pagos')
@section('crumb','Reportes ▸ Pagos')

@push('head') 
  <meta name="rpt-pagos-export" content="{{ route('reportes.pagos.export') }}">
  <meta name="rpt-pagos-facets" content="{{ route('reportes.pagos.facets') }}">
  <link rel="stylesheet" href="{{ asset('css/reportes/pagos.css') }}">
@endpush


@section('content')
<div class="card pad rpt-pagos">

  {{-- ===== Filtros ===== --}}
  <form id="filtros" class="row g-2 align-items-end filters" method="GET" action="{{ route('reportes.pagos') }}">

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
            @forelse($gestores as $g)
              <label class="ms-item">
                <input class="form-check-input" type="checkbox" name="gestor[]" value="{{ $g }}" @checked(in_array($g, $gestorSel, true))>
                <span class="ms-text">{{ $g }}</span>
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
            @forelse($entidades as $e)
              <label class="ms-item">
                <input class="form-check-input" type="checkbox" name="entidad[]" value="{{ $e }}" @checked(in_array($e, $entidadSel, true))>
                <span class="ms-text">{{ $e }}</span>
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

    {{-- Cosecha --}}
    <div class="col-12 col-md-2">
      <label class="form-label">Cosecha</label>
      <div class="dropdown w-100" data-multiselect="cosecha" data-title="Cosecha" data-empty="Todas">
        <button class="btn btn-ms dropdown-toggle w-100 text-start" type="button"
          data-bs-toggle="dropdown" data-bs-auto-close="outside" data-ms-button>
          Cosecha: Todas
        </button>

        <div class="dropdown-menu p-2 w-100 shadow-sm">
          <input type="text" class="form-control form-control-sm mb-2" placeholder="Buscar cosecha…" data-ms-search>
          <div class="ms-list" data-ms-list>
            @forelse($cosechas as $c)
              <label class="ms-item">
                <input class="form-check-input" type="checkbox" name="cosecha[]" value="{{ $c }}" @checked(in_array($c, $cosechaSel, true))>
                <span class="ms-text">{{ $c }}</span>
              </label>
            @empty
              <div class="text-muted small px-1">Sin cosechas en este rango.</div>
            @endforelse
          </div>
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-ms-clear>Limpiar</button>
            <button type="button" class="btn btn-sm btn-success ms-auto" data-ms-apply>Aplicar</button>
          </div>
        </div>
      </div>
    </div>

    {{-- Buscar --}}
    <div class="col-12 col-md-4">
      <label class="form-label">Buscar (DNI / Cliente)</label>
      <div class="input-group">
        <input type="text" name="q" class="form-control" placeholder="Ej: 40695493 o MEJIA" value="{{ $q }}">
        <button class="btn btn-outline-secondary" id="btnBuscar" type="button"><i class="bi bi-search"></i></button>
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

  {{-- ===== Tabla ===== --}}
  @include('reportes.pagos_table', ['rows' => $rows])

</div>
@endsection

@push('scripts')
  <script src="{{ asset('js/reportes/pagos.js') }}" defer></script>
@endpush
