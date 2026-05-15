<div id="tablaPdp">
  <div id="pagMeta"
       data-page="{{ $rows->currentPage() }}"
       data-total="{{ method_exists($rows,'total') ? $rows->total() : '' }}"></div>

  <x-tables.table>
    <thead>
      <tr>
        <x-tables.th>Tipo_Neg</x-tables.th>
        <x-tables.th>Entidad</x-tables.th>
        <x-tables.th>Fecha</x-tables.th>
        <x-tables.th>Cliente</x-tables.th>
        <x-tables.th>Telefono</x-tables.th>
        <x-tables.th>Nrodoc</x-tables.th>
        <x-tables.th>Negociador</x-tables.th>
        <x-tables.th>Situacion</x-tables.th>
        <x-tables.th>Operacion</x-tables.th>
        <x-tables.th>Moneda</x-tables.th>
        <x-tables.th align="right">Deuda_Act</x-tables.th>
        <x-tables.th align="right">Capital_Act</x-tables.th>
        <x-tables.th align="right">Cuotas</x-tables.th>
        <x-tables.th>Fec_Pag</x-tables.th>
        <x-tables.th align="right">Pago_Ini</x-tables.th>
        <x-tables.th>Glosa_Neg</x-tables.th>
      </tr>
    </thead>
    <tbody class="divide-y divide-kp-border bg-white">
      @forelse($rows as $r)
        <tr>
          <x-tables.td>{{ $r->tipo_neg }}</x-tables.td>
          <x-tables.td>{{ $r->entidad }}</x-tables.td>
          <x-tables.td>{{ $r->fecha }}</x-tables.td>
          <x-tables.td class="min-w-56">{{ $r->cliente }}</x-tables.td>
          <x-tables.td>{{ $r->telefono }}</x-tables.td>
          <x-tables.td>{{ $r->nrodoc }}</x-tables.td>
          <x-tables.td>{{ $r->negociador }}</x-tables.td>
          <x-tables.td>{{ $r->estado }}</x-tables.td>
          <x-tables.td>{{ $r->operacion }}</x-tables.td>
          <x-tables.td>{{ $r->moneda }}</x-tables.td>
          <x-tables.td align="right">{{ $r->deuda_act !== null ? number_format((float) $r->deuda_act, 2) : '' }}</x-tables.td>
          <x-tables.td align="right">{{ $r->capital_act !== null ? number_format((float) $r->capital_act, 2) : '' }}</x-tables.td>
          <x-tables.td align="right">{{ $r->cuotas !== null ? (int) $r->cuotas : '' }}</x-tables.td>
          <x-tables.td>{{ $r->fec_pag }}</x-tables.td>
          <x-tables.td align="right">{{ $r->pago_ini !== null ? number_format((float) $r->pago_ini, 2) : '' }}</x-tables.td>
          <x-tables.td class="min-w-56">{{ $r->glosa_neg }}</x-tables.td>
        </tr>
      @empty
        <x-tables.empty-row colspan="16" />
      @endforelse
    </tbody>
  </x-tables.table>

  <x-tables.pagination :paginator="$rows" class="mt-3" />
</div>
