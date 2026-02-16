<div id="tablaPdp">
  <div id="pagMeta"
       data-page="{{ $rows->currentPage() }}"
       data-total="{{ method_exists($rows,'total') ? $rows->total() : '' }}"></div>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Tipo_Neg</th><th>Entidad</th><th>Fecha</th><th>Cliente</th><th>Telefono</th><th>Nrodoc</th>
          <th>Negociador</th><th>Situacion</th><th>Operacion</th><th>Moneda</th>
          <th class="text-end">Deuda_Act</th><th class="text-end">Capital_Act</th><th class="text-end">Cuotas</th>
          <th>Fec_Pag</th><th class="text-end">Pago_Ini</th><th>Glosa_Neg</th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="nowrap">{{ $r->tipo_neg }}</td>
          <td class="nowrap">{{ $r->entidad }}</td>
          <td class="nowrap text-mono">{{ $r->fecha }}</td>
          <td class="nowrap">{{ $r->cliente }}</td>
          <td class="nowrap">{{ $r->telefono }}</td>
          <td class="nowrap text-mono">{{ $r->nrodoc }}</td>
          <td class="nowrap">{{ $r->negociador }}</td>
          <td class="nowrap">{{ $r->estado }}</td>
          <td class="nowrap text-mono">{{ $r->operacion }}</td>
          <td class="nowrap">{{ $r->moneda }}</td>
          <td class="text-end text-mono">{{ $r->deuda_act!==null ? number_format((float)$r->deuda_act,2) : '' }}</td>
          <td class="text-end text-mono">{{ $r->capital_act!==null ? number_format((float)$r->capital_act,2) : '' }}</td>
          <td class="text-end text-mono">{{ $r->cuotas!==null ? (int)$r->cuotas : '' }}</td>
          <td class="nowrap text-mono">{{ $r->fec_pag }}</td>
          <td class="text-end text-mono">{{ $r->pago_ini!==null ? number_format((float)$r->pago_ini,2) : '' }}</td>
          <td class="nowrap">{{ $r->glosa_neg }}</td>
        </tr>
      @empty
        <tr><td colspan="16" class="text-secondary">Sin resultados.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <div class="small text-muted">
      Mostrando {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }}
      @if(method_exists($rows,'total')) de {{ $rows->total() }} @endif
    </div>
    {{ $rows->onEachSide(1)->withQueryString()->links('pagination::bootstrap-5') }}
  </div>
</div>
