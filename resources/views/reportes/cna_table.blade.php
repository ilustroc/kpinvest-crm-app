<div id="tablaCna">
  <div id="pagMeta"
       data-page="{{ method_exists($rows,'currentPage') ? $rows->currentPage() : '' }}"
       data-total="{{ method_exists($rows,'total') ? $rows->total() : '' }}"></div>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Documento</th>
          <th>Cliente</th>
          <th>Entidad</th>
          <th>Cna_Nro</th>
          <th>Cna_Fec</th>
          <th>Fondo_Inv</th>
          <th>Año_Mes</th>
          <th class="text-end">Cna_Imp</th>
          <th>Nro_Cuenta</th>
          <th>Nro_Operación</th>
          <th>Gestor</th>
          <th>Estado</th>
          <th>Gen_Gestor</th>
          <th>Apr_Gestor</th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="nowrap text-mono">{{ $r->documento }}</td>
          <td class="nowrap">{{ $r->cliente }}</td>
          <td class="nowrap">{{ $r->entidad ?? '' }}</td>
          <td class="nowrap">{{ $r->cna_nro }}</td>
          <td class="nowrap text-mono">{{ $r->cna_fec }}</td>
          <td class="nowrap">{{ $r->fondo_inv }}</td>
          <td class="nowrap text-mono">{{ $r->anio_mes }}</td>
          <td class="text-end text-mono">{{ $r->cna_imp!==null ? number_format((float)$r->cna_imp,2) : '' }}</td>
          <td class="nowrap text-mono">{{ $r->nro_cuenta }}</td>
          <td class="nowrap text-mono">{{ $r->nro_operacion }}</td>
          <td class="nowrap">{{ $r->gestor }}</td>
          <td class="nowrap">{{ $r->estado }}</td>
          <td class="nowrap">{{ $r->gen_gestor }}</td>
          <td class="nowrap">{{ $r->apr_gestor }}</td>
        </tr>
      @empty
        <tr><td colspan="15" class="text-secondary">Sin resultados.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <div class="small text-muted">
      Mostrando {{ method_exists($rows,'firstItem') ? ($rows->firstItem() ?? 0) : 0 }}–{{ method_exists($rows,'lastItem') ? ($rows->lastItem() ?? 0) : 0 }}
      @if(method_exists($rows,'total')) de {{ $rows->total() }} @endif
    </div>

    @if(method_exists($rows,'links'))
      {{ $rows->onEachSide(1)->withQueryString()->links('pagination::bootstrap-5') }}
    @endif
  </div>
</div>
