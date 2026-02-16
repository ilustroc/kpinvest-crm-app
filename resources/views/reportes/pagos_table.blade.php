{{-- resources/views/reportes/pagos_table.blade.php --}}
<div id="tablaPagos">
  <div id="pagMeta" data-page="{{ $rows->currentPage() }}" data-total="{{ $rows->total() }}"></div>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>DNI</th>
          <th>Nombre</th>
          <th>Operación</th>
          <th class="text-end">Monto</th>
          <th>Agente</th>
          <th>Cosecha</th>
          <th>Cuenta Recaudo</th>
          <th>Entidad Financiera</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td class="text-nowrap">{{ optional($r->fecha)->format('Y-m-d') }}</td>
            <td class="text-nowrap">{{ $r->dni }}</td>
            <td>{{ $r->nombre_cliente }}</td>
            <td class="text-nowrap">{{ $r->operacion }}</td>
            <td class="text-end">{{ number_format((float)$r->monto_pagado, 2) }}</td>
            <td>{{ $r->gestor }}</td>
            <td>{{ $r->cosecha }}</td>
            <td>{{ $r->cuenta_recaudo }}</td>
            <td>{{ $r->entidad }}</td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-secondary">Sin resultados.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <div class="small text-muted">
      Mostrando {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} de {{ $rows->total() }}.
    </div>
    {{ $rows->onEachSide(1)->withQueryString()->links('pagination::bootstrap-5') }}
  </div>
</div>
