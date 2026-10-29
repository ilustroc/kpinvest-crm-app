<div id="tablaCna" class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
  <div id="pagMeta"
       class="hidden"
       data-page="{{ method_exists($rows,'currentPage') ? $rows->currentPage() : '' }}"
       data-last="{{ method_exists($rows,'lastPage') ? $rows->lastPage() : '' }}"
       data-total="{{ method_exists($rows,'total') ? $rows->total() : '' }}"
       data-visible="{{ $rows->count() }}"></div>

  <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3">
    <div class="min-w-0">
      <h2 class="text-[15px] font-semibold text-kp-ink">Detalle de CNA</h2>
      <p class="mt-0.5 text-[11px] text-kp-muted">Ordenado por fecha de aprobación descendente.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <span class="inline-flex items-center gap-1 text-[10px] font-medium text-kp-muted">
        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0-4.5-4.5M19.5 12 15 16.5" />
        </svg>
        Desliza para ver todas las columnas
      </span>
      <span class="inline-flex items-center rounded-md border border-kp-border bg-kp-surface-muted px-2 py-1 text-[10.5px] font-semibold text-slate-600">
        {{ number_format($rows->total()) }} {{ $rows->total() === 1 ? 'registro' : 'registros' }}
      </span>
      <span class="inline-flex items-center rounded-md border border-kp-border bg-white px-2 py-1 text-[10.5px] font-medium text-slate-500">
        Página {{ $rows->currentPage() }} de {{ $rows->lastPage() }}
      </span>
    </div>
  </header>

  <x-tables.table :flush="true" :contained="true" min-width="min-w-[1440px]">
    <thead>
      <tr>
        <x-tables.th>Documento</x-tables.th>
        <x-tables.th>Cliente</x-tables.th>
        <x-tables.th>Entidad</x-tables.th>
        <x-tables.th>CNA</x-tables.th>
        <x-tables.th>Fecha</x-tables.th>
        <x-tables.th>Fondo de inversión</x-tables.th>
        <x-tables.th>Año-Mes</x-tables.th>
        <x-tables.th align="right">Importe</x-tables.th>
        <x-tables.th>Cuenta</x-tables.th>
        <x-tables.th>Operación</x-tables.th>
        <x-tables.th>Gestor</x-tables.th>
        <x-tables.th>Estado</x-tables.th>
        <x-tables.th>Gen. gestor</x-tables.th>
        <x-tables.th>Apr. gestor</x-tables.th>
      </tr>
    </thead>
    <tbody class="divide-y divide-kp-border bg-white">
      @forelse($rows as $r)
        @php
          $cnaDate = $r->cna_fec ? \Carbon\Carbon::parse($r->cna_fec) : null;
        @endphp
        <tr class="transition-colors hover:bg-slate-50/80">
          <x-tables.td><span class="font-mono text-[12px] tabular-nums text-slate-600">{{ $r->documento ?: '—' }}</span></x-tables.td>
          <x-tables.td class="min-w-52"><span class="font-semibold text-kp-ink">{{ $r->cliente ?: '—' }}</span></x-tables.td>
          <x-tables.td class="text-slate-600">{{ $r->entidad ?: '—' }}</x-tables.td>
          <x-tables.td><span class="font-mono text-[12px] text-slate-600">{{ $r->cna_nro ?: '—' }}</span></x-tables.td>
          <x-tables.td>
            <div class="font-medium tabular-nums text-slate-700">{{ $cnaDate?->format('d/m/Y') ?: '—' }}</div>
            @if($cnaDate)
              <div class="mt-0.5 text-[10.5px] tabular-nums text-kp-muted">{{ $cnaDate->format('H:i') }}</div>
            @endif
          </x-tables.td>
          <x-tables.td class="max-w-72 truncate text-slate-600" :title="$r->fondo_inv">{{ $r->fondo_inv ?: '—' }}</x-tables.td>
          <x-tables.td class="tabular-nums text-slate-600">{{ $r->anio_mes ?: '—' }}</x-tables.td>
          <x-tables.td align="right"><span class="font-semibold tabular-nums text-kp-ink">{{ $r->cna_imp !== null ? \App\Support\Money::format($r->cna_imp, 'PEN') : '—' }}</span></x-tables.td>
          <x-tables.td><span class="font-mono text-[12px] text-slate-500">{{ $r->nro_cuenta ?: '—' }}</span></x-tables.td>
          <x-tables.td><span class="font-mono text-[12px] text-slate-600">{{ $r->nro_operacion ?: '—' }}</span></x-tables.td>
          <x-tables.td class="text-slate-600">{{ $r->gestor ?: '—' }}</x-tables.td>
          <x-tables.td><x-clientes.status-badge :status="$r->estado" /></x-tables.td>
          <x-tables.td class="max-w-40 truncate text-slate-600" :title="$r->gen_gestor">{{ $r->gen_gestor ?: '—' }}</x-tables.td>
          <x-tables.td class="max-w-40 truncate text-slate-600" :title="$r->apr_gestor">{{ $r->apr_gestor ?: '—' }}</x-tables.td>
        </tr>
      @empty
        <x-tables.empty-row colspan="14">
          <div class="flex flex-col items-center gap-1.5 py-2">
            <span class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-400">
              <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5A2.25 2.25 0 0 0 19.5 19.5v-5.25Z" />
              </svg>
            </span>
            <span class="text-[13px] font-semibold text-kp-ink">Sin solicitudes CNA para mostrar</span>
            <span class="text-[11px] text-kp-muted">Ajusta el periodo, el estado o los filtros de consulta.</span>
          </div>
        </x-tables.empty-row>
      @endforelse
    </tbody>
  </x-tables.table>

  <x-tables.pagination :paginator="$rows" />
</div>
