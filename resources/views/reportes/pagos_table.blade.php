{{-- resources/views/reportes/pagos_table.blade.php --}}
<div id="tablaPagos" class="space-y-3">
  <div id="pagMeta"
       data-page="{{ method_exists($rows,'currentPage') ? $rows->currentPage() : '' }}"
       data-total="{{ method_exists($rows,'total') ? $rows->total() : '' }}"></div>

  <div class="overflow-auto rounded-2xl border border-slate-200 bg-white">
    <table class="min-w-full text-sm">
      <thead class="sticky top-0 bg-slate-50 text-slate-700 border-b border-slate-200">
        <tr>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Fecha</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">DNI</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Nombre</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Operacion</th>
          <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Monto</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Agente</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Cosecha</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Cuenta Recaudo</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Entidad Financiera</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-slate-100">
        @forelse($rows as $r)
          <tr class="{{ $loop->even ? 'bg-slate-50/60' : 'bg-white' }} hover:bg-emerald-50/40">
            <td class="px-3 py-2 whitespace-nowrap font-mono">{{ optional($r->fecha)->format('Y-m-d') }}</td>
            <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->dni }}</td>
            <td class="px-3 py-2">{{ $r->nombre_cliente }}</td>
            <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->operacion }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap font-mono">{{ number_format((float)$r->monto_pagado, 2) }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->gestor }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->cosecha }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->cuenta_recaudo }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->entidad }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="px-3 py-10 text-center text-slate-500">
              Sin resultados.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="text-sm text-slate-500">
      Mostrando {{ method_exists($rows,'firstItem') ? ($rows->firstItem() ?? 0) : 0 }}–{{ method_exists($rows,'lastItem') ? ($rows->lastItem() ?? 0) : 0 }}
      @if(method_exists($rows,'total')) de {{ $rows->total() }} @endif
      .
    </div>

    @if(method_exists($rows,'links'))
      {{ $rows->onEachSide(1)->withQueryString()->links() }}
    @endif
  </div>
</div>