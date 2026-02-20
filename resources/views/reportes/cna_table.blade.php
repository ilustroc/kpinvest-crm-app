<div id="tablaCna" class="space-y-3">
  <div id="pagMeta"
       data-page="{{ method_exists($rows,'currentPage') ? $rows->currentPage() : '' }}"
       data-total="{{ method_exists($rows,'total') ? $rows->total() : '' }}"></div>

  <div class="overflow-auto rounded-2xl border border-slate-200 bg-white">
    <table class="min-w-full text-sm">
      <thead class="sticky top-0 bg-slate-50 text-slate-700 border-b border-slate-200">
        <tr>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Documento</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Cliente</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Entidad</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Cna_Nro</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Cna_Fec</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Fondo_Inv</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Año_Mes</th>
          <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Cna_Imp</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Nro_Cuenta</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Nro_Operacion</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Gestor</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Estado</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Gen_Gestor</th>
          <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Apr_Gestor</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-slate-100">
      @forelse($rows as $r)
        <tr class="{{ $loop->even ? 'bg-slate-50/60' : 'bg-white' }} hover:bg-emerald-50/40">
          <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->documento }}</td>
          <td class="px-3 py-2 whitespace-nowrap">{{ $r->cliente }}</td>
          <td class="px-3 py-2 whitespace-nowrap">{{ $r->entidad ?? '' }}</td>
          <td class="px-3 py-2 whitespace-nowrap">{{ $r->cna_nro }}</td>
          <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->cna_fec }}</td>
          <td class="px-3 py-2 whitespace-nowrap">{{ $r->fondo_inv }}</td>
          <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->anio_mes }}</td>
          <td class="px-3 py-2 text-right whitespace-nowrap font-mono">
            {{ $r->cna_imp!==null ? number_format((float)$r->cna_imp,2) : '' }}
          </td>
          <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->nro_cuenta }}</td>
          <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $r->nro_operacion }}</td>
          <td class="px-3 py-2 whitespace-nowrap">{{ $r->gestor }}</td>
          <td class="px-3 py-2 whitespace-nowrap">{{ $r->estado }}</td>
          <td class="px-3 py-2 whitespace-nowrap">{{ $r->gen_gestor }}</td>
          <td class="px-3 py-2 whitespace-nowrap">{{ $r->apr_gestor }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="14" class="px-3 py-10 text-center text-slate-500">
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
      {{-- usa tailwind default o tu paginador custom --}}
      {{ $rows->onEachSide(1)->withQueryString()->links() }}
    @endif
  </div>
</div>