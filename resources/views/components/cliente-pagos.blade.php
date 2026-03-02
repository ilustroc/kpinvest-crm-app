{{-- resources/views/components/cliente-pagos.blade.php --}}
@props([
  'dni',
  'pagos' => collect(),
])

@php
  $pagosCol = $pagos instanceof \Illuminate\Support\Collection ? $pagos : collect($pagos);

  $canDeletePagos = in_array(
    strtolower((string)optional(Auth::user())->role),
    ['administrador','sistemas','supervisor','soporte']
  );
@endphp

<div class="card pad mb-3">
  <div class="d-flex justify-content-between align-items-center">
    <h2 class="h6 mb-0 d-flex align-items-center gap-2">
      <i class="bi bi-receipt"></i><span>Pagos</span>
    </h2>

    <div class="d-flex align-items-center gap-2">
      @if($canDeletePagos)
        <div class="form-check form-switch me-2">
          <input class="form-check-input" type="checkbox" id="toggleDeletePagos">
          <label class="form-check-label small" for="toggleDeletePagos">Eliminar pagos</label>
        </div>

        <form id="frmDeletePagos" method="POST" action="{{ route('clientes.pagos.delete', $dni) }}"
              onsubmit="return confirm('¿Eliminar los pagos seleccionados?');" class="m-0">
          @csrf
          <button type="submit" id="btnDeletePagos" class="btn btn-danger btn-sm" disabled>
            <i class="bi bi-trash"></i> Eliminar seleccionados
          </button>
        </form>
      @endif

      <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse"
              data-bs-target="#pagosCollapse">
        Ver/ocultar
      </button>
    </div>
  </div>

  <div id="pagosCollapse" class="collapse mt-2 show">
    <div class="table-responsive max-h-260">
      <table class="table table-sm align-middle tbl-compact" id="tblPagos">
        <thead class="position-sticky top-0 bg-body">
          <tr>
            @if($canDeletePagos)
              <th class="text-nowrap col-del d-none">
                <input type="checkbox" id="chkAllPagos">
              </th>
            @endif
            <th class="text-nowrap">Fecha</th>
            <th class="text-nowrap">Operación</th>
            <th class="text-end text-nowrap">Monto (S/)</th>
            <th class="text-nowrap">Agente</th>
            <th class="text-nowrap">Cuenta Recaudo</th>
          </tr>
        </thead>

        <tbody>
          @forelse($pagosCol as $p)
            @php
              $id      = $p->id ?? $p['id'] ?? null;
              $oper    = $p->operacion ?? $p['operacion'] ?? '-';
              $monto   = $p->monto_pagado ?? $p['monto_pagado'] ?? 0;
              $fecha   = $p->fecha ?? $p['fecha'] ?? null;
              $gestor  = $p->gestor ?? $p['gestor'] ?? '-';
              $cuenta  = $p->cuenta_recaudo ?? $p['cuenta_recaudo'] ?? '-';
            @endphp

            <tr>
              @if($canDeletePagos)
                <td class="col-del d-none">
                  @if($id)
                    <input type="checkbox" name="ids[]" form="frmDeletePagos" class="chkPago" value="{{ $id }}">
                  @endif
                </td>
              @endif

              <td class="text-nowrap">{{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '' }}</td>
              <td class="text-nowrap">{{ $oper }}</td>
              <td class="text-end text-nowrap">{{ number_format((float)$monto, 2, '.', ',') }}</td>
              <td class="text-nowrap">{{ $gestor }}</td>
              <td class="text-nowrap">{{ $cuenta }}</td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-secondary">Sin pagos</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>