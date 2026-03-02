{{-- resources/views/reportes/pdp_table.blade.php --}}
<div id="tablaPdp" class="space-y-3">

  <div id="pagMeta"
       data-page="{{ method_exists($rows,'currentPage') ? $rows->currentPage() : '' }}"
       data-total="{{ method_exists($rows,'total') ? $rows->total() : '' }}"></div>

  <div class="overflow-auto rounded-2xl border border-slate-200 bg-white">
    <table class="min-w-full text-sm">
      <thead class="sticky top-0 bg-slate-50 text-slate-700 border-b border-slate-200">
        <tr>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Tipo_Neg</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Entidad</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Fecha</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Cliente</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Telefono</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Nrodoc</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Negociador</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Situacion</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Operacion</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Moneda</th>
          <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Deuda_Act</th>
          <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Capital_Act</th>
          <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Cuotas</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Fec_Pag</th>
          <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Pago_Ini</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Glosa_Neg</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-slate-100">
        @forelse($rows as $r)
          <tr class="{{ $loop->even ? 'bg-slate-50/60' : 'bg-white' }} hover:bg-emerald-50/40">
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->tipo_neg }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->entidad }}</td>
            <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->fecha }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->cliente }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->telefono }}</td>
            <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->nrodoc }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->negociador }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->estado }}</td>
            <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->operacion }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->moneda }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap font-mono">
              {{ $r->deuda_act!==null ? number_format((float)$r->deuda_act,2) : '' }}
            </td>
            <td class="px-3 py-2 text-right whitespace-nowrap font-mono">
              {{ $r->capital_act!==null ? number_format((float)$r->capital_act,2) : '' }}
            </td>
            <td class="px-3 py-2 text-right whitespace-nowrap font-mono">
              {{ $r->cuotas!==null ? (int)$r->cuotas : '' }}
            </td>
            <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->fec_pag }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap font-mono">
              {{ $r->pago_ini!==null ? number_format((float)$r->pago_ini,2) : '' }}
            </td>
            <td class="px-3 py-2 whitespace-nowrap">{{ $r->glosa_neg }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="16" class="px-3 py-10 text-center text-slate-500">
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
    </div>

    @if(method_exists($rows,'links'))
      {{ $rows->onEachSide(1)->withQueryString()->links() }}
    @endif
  </div>

</div>