<div id="tablaPagos">
  <div id="pagMeta" data-page="{{ $rows->currentPage() }}" data-total="{{ $rows->total() }}"></div>

  <x-tables.table>
    <thead>
      <tr>
        <x-tables.th>Fecha</x-tables.th>
        <x-tables.th>DNI</x-tables.th>
        <x-tables.th>Nombre</x-tables.th>
        <x-tables.th>Operacion</x-tables.th>
        <x-tables.th align="right">Monto</x-tables.th>
        <x-tables.th>Agente</x-tables.th>
        <x-tables.th>Cosecha</x-tables.th>
        <x-tables.th>Cuenta Recaudo</x-tables.th>
        <x-tables.th>Entidad Financiera</x-tables.th>
      </tr>
    </thead>
    <tbody class="divide-y divide-kp-border bg-white">
      @forelse($rows as $r)
        <tr>
          <x-tables.td>{{ optional($r->fecha)->format('Y-m-d') }}</x-tables.td>
          <x-tables.td>{{ $r->dni }}</x-tables.td>
          <x-tables.td class="min-w-56">{{ $r->nombre_cliente }}</x-tables.td>
          <x-tables.td>{{ $r->operacion }}</x-tables.td>
          <x-tables.td align="right">{{ number_format((float)$r->monto_pagado, 2) }}</x-tables.td>
          <x-tables.td>{{ $r->gestor }}</x-tables.td>
          <x-tables.td>{{ $r->cosecha }}</x-tables.td>
          <x-tables.td>{{ $r->cuenta_recaudo }}</x-tables.td>
          <x-tables.td>{{ $r->entidad }}</x-tables.td>
        </tr>
      @empty
        <x-tables.empty-row colspan="9" />
      @endforelse
    </tbody>
  </x-tables.table>

  <x-tables.pagination :paginator="$rows" class="mt-3" />
</div>
