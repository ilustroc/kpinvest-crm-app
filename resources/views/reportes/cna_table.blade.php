<div id="tablaCna">
  <div id="pagMeta"
       data-page="{{ method_exists($rows,'currentPage') ? $rows->currentPage() : '' }}"
       data-total="{{ method_exists($rows,'total') ? $rows->total() : '' }}"></div>

  <x-tables.table>
    <thead>
      <tr>
        <x-tables.th>Documento</x-tables.th>
        <x-tables.th>Cliente</x-tables.th>
        <x-tables.th>Entidad</x-tables.th>
        <x-tables.th>Cna_Nro</x-tables.th>
        <x-tables.th>Cna_Fec</x-tables.th>
        <x-tables.th>Fondo_Inv</x-tables.th>
        <x-tables.th>Anio_Mes</x-tables.th>
        <x-tables.th align="right">Cna_Imp</x-tables.th>
        <x-tables.th>Nro_Cuenta</x-tables.th>
        <x-tables.th>Nro_Operacion</x-tables.th>
        <x-tables.th>Gestor</x-tables.th>
        <x-tables.th>Estado</x-tables.th>
        <x-tables.th>Gen_Gestor</x-tables.th>
        <x-tables.th>Apr_Gestor</x-tables.th>
      </tr>
    </thead>
    <tbody class="divide-y divide-kp-border bg-white">
      @forelse($rows as $r)
        <tr>
          <x-tables.td>{{ $r->documento }}</x-tables.td>
          <x-tables.td class="min-w-56">{{ $r->cliente }}</x-tables.td>
          <x-tables.td>{{ $r->entidad ?? '' }}</x-tables.td>
          <x-tables.td>{{ $r->cna_nro }}</x-tables.td>
          <x-tables.td>{{ $r->cna_fec }}</x-tables.td>
          <x-tables.td class="min-w-64">{{ $r->fondo_inv }}</x-tables.td>
          <x-tables.td>{{ $r->anio_mes }}</x-tables.td>
          <x-tables.td align="right">{{ $r->cna_imp !== null ? number_format((float) $r->cna_imp, 2) : '' }}</x-tables.td>
          <x-tables.td>{{ $r->nro_cuenta }}</x-tables.td>
          <x-tables.td>{{ $r->nro_operacion }}</x-tables.td>
          <x-tables.td>{{ $r->gestor }}</x-tables.td>
          <x-tables.td>{{ $r->estado }}</x-tables.td>
          <x-tables.td>{{ $r->gen_gestor }}</x-tables.td>
          <x-tables.td>{{ $r->apr_gestor }}</x-tables.td>
        </tr>
      @empty
        <x-tables.empty-row colspan="14" />
      @endforelse
    </tbody>
  </x-tables.table>

  <x-tables.pagination :paginator="$rows" class="mt-3" />
</div>
