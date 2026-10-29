<div id="tablaPdp" class="overflow-hidden rounded-[10px] border border-kp-border bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
  <div id="pagMeta"
       class="hidden"
       data-page="{{ $rows->currentPage() }}"
       data-last="{{ $rows->lastPage() }}"
       data-total="{{ method_exists($rows,'total') ? $rows->total() : '' }}"
       data-visible="{{ $rows->count() }}"></div>

  <header class="flex flex-wrap items-center justify-between gap-3 border-b border-kp-border px-4 py-3">
    <div class="min-w-0">
      <h2 class="text-[15px] font-semibold text-kp-ink">Detalle de promesas</h2>
      <p class="mt-0.5 text-[11px] text-kp-muted">Ordenado por fecha de registro descendente.</p>
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

  <x-tables.table :flush="true" :contained="true" min-width="min-w-[1480px]">
    <thead>
      <tr>
        <x-tables.th>Tipo</x-tables.th>
        <x-tables.th>Entidad</x-tables.th>
        <x-tables.th>Fecha</x-tables.th>
        <x-tables.th>Cliente</x-tables.th>
        <x-tables.th>Teléfono</x-tables.th>
        <x-tables.th>Nro. doc.</x-tables.th>
        <x-tables.th>Negociador</x-tables.th>
        <x-tables.th>Situación</x-tables.th>
        <x-tables.th>Operación</x-tables.th>
        <x-tables.th>Moneda</x-tables.th>
        <x-tables.th align="right">Deuda act.</x-tables.th>
        <x-tables.th align="right">Capital act.</x-tables.th>
        <x-tables.th align="right">Cuotas</x-tables.th>
        <x-tables.th>Fec. pago</x-tables.th>
        <x-tables.th align="right">Pago inicial</x-tables.th>
        <x-tables.th>Glosa</x-tables.th>
      </tr>
    </thead>
    <tbody class="divide-y divide-kp-border bg-white">
      @forelse($rows as $r)
        @php
          $tipoKey = strtolower((string) $r->tipo_neg);
          $tipoVariant = match ($tipoKey) {
              'convenio' => 'info',
              'cancelacion' => 'warning',
              'parcial' => 'primary',
              default => 'neutral',
          };
          $createdAt = $r->fecha ? \Carbon\Carbon::parse($r->fecha) : null;
          $paymentDate = $r->fec_pag ? \Carbon\Carbon::parse($r->fec_pag) : null;
          $currency = strtoupper(trim((string) ($r->moneda ?? 'PEN'))) ?: 'PEN';
          $currency = str_contains($currency, ',') ? null : $currency;
        @endphp
        <tr class="transition-colors hover:bg-slate-50/80">
          <x-tables.td>
            <x-ui.badge :variant="$tipoVariant" :title="$r->tipo_neg">{{ ucfirst((string) $r->tipo_neg) }}</x-ui.badge>
          </x-tables.td>
          <x-tables.td class="max-w-44 truncate text-slate-600" :title="$r->entidad">{{ $r->entidad ?: '—' }}</x-tables.td>
          <x-tables.td>
            <div class="font-medium tabular-nums text-slate-700">{{ $createdAt?->format('d/m/Y') ?: '—' }}</div>
            <div class="mt-0.5 text-[10.5px] tabular-nums text-kp-muted">{{ $createdAt?->format('H:i') }}</div>
          </x-tables.td>
          <x-tables.td class="min-w-52"><span class="font-semibold text-kp-ink">{{ $r->cliente ?: '—' }}</span></x-tables.td>
          <x-tables.td class="tabular-nums text-slate-600">{{ $r->telefono ?: '—' }}</x-tables.td>
          <x-tables.td><span class="font-mono text-[12px] tabular-nums text-slate-600">{{ $r->nrodoc ?: '—' }}</span></x-tables.td>
          <x-tables.td class="text-slate-600">{{ $r->negociador ?: '—' }}</x-tables.td>
          <x-tables.td><x-clientes.status-badge :status="$r->estado" /></x-tables.td>
          <x-tables.td><span class="font-mono text-[12px] text-slate-600">{{ $r->operacion ?: '—' }}</span></x-tables.td>
          <x-tables.td><span class="text-[11px] font-semibold text-slate-500">{{ strtoupper((string) ($r->moneda ?: '—')) }}</span></x-tables.td>
          <x-tables.td align="right" class="tabular-nums">{{ $r->deuda_act !== null ? \App\Support\Money::format($r->deuda_act, $currency) : '—' }}</x-tables.td>
          <x-tables.td align="right" class="tabular-nums">{{ $r->capital_act !== null ? \App\Support\Money::format($r->capital_act, $currency) : '—' }}</x-tables.td>
          <x-tables.td align="right" class="tabular-nums">{{ $r->cuotas !== null ? (int) $r->cuotas : '—' }}</x-tables.td>
          <x-tables.td class="tabular-nums text-slate-600">{{ $paymentDate?->format('d/m/Y') ?: '—' }}</x-tables.td>
          <x-tables.td align="right" class="font-semibold tabular-nums text-kp-ink">{{ $r->pago_ini !== null ? \App\Support\Money::format($r->pago_ini, $currency) : '—' }}</x-tables.td>
          <x-tables.td class="max-w-64 truncate text-slate-600" :title="$r->glosa_neg">{{ $r->glosa_neg ?: '—' }}</x-tables.td>
        </tr>
      @empty
        <x-tables.empty-row colspan="16">
          <div class="flex flex-col items-center gap-1.5 py-2">
            <span class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-400">
              <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4.5h4.5M7.5 3.75h9A2.25 2.25 0 0 1 18.75 6v13.5l-3-2.25-3 2.25-3-2.25-3 2.25V6A2.25 2.25 0 0 1 7.5 3.75Z" />
              </svg>
            </span>
            <span class="text-[13px] font-semibold text-kp-ink">Sin promesas para mostrar</span>
            <span class="text-[11px] text-kp-muted">Ajusta el periodo, el estado o los filtros de consulta.</span>
          </div>
        </x-tables.empty-row>
      @endforelse
    </tbody>
  </x-tables.table>

  <x-tables.pagination :paginator="$rows" />
</div>
