<div id="tablaPagos" class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
  <div id="pagMeta"
       class="hidden"
       data-page="{{ $rows->currentPage() }}"
       data-last="{{ $rows->lastPage() }}"
       data-total="{{ $rows->total() }}"
       data-visible="{{ $rows->count() }}"></div>

  <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3">
    <div class="min-w-0">
      <h2 class="text-[15px] font-semibold text-kp-ink">Detalle de pagos</h2>
      <p class="mt-0.5 text-[11px] text-kp-muted">Ordenado por fecha de pago descendente.</p>
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

  <x-tables.table :flush="true" :contained="true" min-width="min-w-[1040px]">
    <thead>
      <tr>
        <x-tables.th>Fecha</x-tables.th>
        <x-tables.th>DNI</x-tables.th>
        <x-tables.th>Cliente</x-tables.th>
        <x-tables.th>Operación</x-tables.th>
        <x-tables.th align="right">Monto</x-tables.th>
        <x-tables.th>Gestor</x-tables.th>
        <x-tables.th>Cosecha</x-tables.th>
        <x-tables.th>Cuenta recaudo</x-tables.th>
        <x-tables.th>Entidad financiera</x-tables.th>
      </tr>
    </thead>
    <tbody class="divide-y divide-kp-border bg-white">
      @forelse($rows as $r)
        <tr class="transition-colors hover:bg-slate-50/80">
          <x-tables.td class="tabular-nums font-medium text-slate-600">{{ optional($r->fecha)->format('d/m/Y') ?: '—' }}</x-tables.td>
          <x-tables.td><span class="font-mono text-[12px] tabular-nums text-slate-600">{{ $r->dni ?: '—' }}</span></x-tables.td>
          <x-tables.td class="min-w-56"><span class="font-semibold text-kp-ink">{{ $r->nombre_cliente ?: '—' }}</span></x-tables.td>
          <x-tables.td><span class="font-mono text-[12px] text-slate-600">{{ $r->operacion ?: '—' }}</span></x-tables.td>
          <x-tables.td align="right"><span class="font-semibold tabular-nums text-kp-ink">{{ \App\Support\Money::format($r->monto_pagado, 'PEN') }}</span></x-tables.td>
          <x-tables.td class="text-slate-600">{{ $r->gestor ?: '—' }}</x-tables.td>
          <x-tables.td class="text-slate-600">{{ $r->cosecha ?: '—' }}</x-tables.td>
          <x-tables.td><span class="font-mono text-[12px] text-slate-500">{{ $r->cuenta_recaudo ?: '—' }}</span></x-tables.td>
          <x-tables.td class="text-slate-600">{{ $r->entidad ?: '—' }}</x-tables.td>
        </tr>
      @empty
        <x-tables.empty-row colspan="9">
          <div class="flex flex-col items-center gap-1.5 py-2">
            <span class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-400">
              <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7.5 15l3-3 2.25 2.25L18 9" />
              </svg>
            </span>
            <span class="text-[13px] font-semibold text-kp-ink">Sin pagos para mostrar</span>
            <span class="text-[11px] text-kp-muted">Ajusta el periodo o los filtros de consulta.</span>
          </div>
        </x-tables.empty-row>
      @endforelse
    </tbody>
  </x-tables.table>

  <x-tables.pagination :paginator="$rows" />
</div>
