{{-- resources/views/components/cliente-promesas.blade.php --}}
@props(['promesas' => collect()])

@php
  $promCol = $promesas instanceof \Illuminate\Support\Collection ? $promesas : collect($promesas);
@endphp

<div class="promesas">
  <div class="card pad h-100">
    <div class="card-head">
      <h2 class="h6 mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-flag"></i> <span>Promesas de pago</span>
      </h2>

      @if($promCol->count())
        <div class="hint">{{ $promCol->count() }} registro(s)</div>
      @endif
    </div>

    <div class="table-responsive max-h-320">
      <table class="table align-middle tbl-compact" id="tblPromesas">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Operación(es)</th>
            <th class="text-end">Monto negociación</th>
            <th class="text-center">Nota</th>
            <th class="text-center">Campaña</th>
            <th>Usuario creación</th>
            <th>Estado aprobación</th>
          </tr>
        </thead>

        <tbody>
        @forelse($promCol as $pp)
          @php
            $tipoLabel = match($pp->tipo){
              'convenio_balon' => 'Convenio [Cuota Balón]',
              'convenio'       => 'Convenio',
              'cancelacion'    => 'Cancelación',
              default          => strtoupper((string)$pp->tipo)
            };

            $ops = $pp->relationLoaded('operaciones') && $pp->operaciones->count()
              ? $pp->operaciones->pluck('operacion')->all()
              : array_filter(array_map('trim', explode(',', (string)($pp->operacion ?? ''))));

            $montoNeg = (float)($pp->monto ?? 0);
            if ($montoNeg <= 0) {
              $montoNeg = (float)($pp->cuotas->first()->monto ?? 0);
            }

            $estado = ucfirst(str_replace('_',' ', (string)($pp->workflow_estado ?? 'pendiente')));
            $nota   = trim((string)($pp->nota ?? ''));

            $ds = [
              'tipo'   => (string)$pp->tipo,
              'cuotas' => $pp->tipo === 'cancelacion'
                        ? [[ 'nro' => 1, 'fecha' => optional($pp->fecha_pago)->format('Y-m-d'), 'monto' => (float)($pp->monto ?? 0), 'es_balon' => 0 ]]
                        : $pp->cuotas->map(fn($c)=>[
                            'nro'      => (int)$c->nro,
                            'fecha'    => optional($c->fecha)->format('Y-m-d'),
                            'monto'    => (float)$c->monto,
                            'es_balon' => (int)($c->es_balon ?? 0),
                          ])->values(),
            ];
          @endphp

          <tr class="cursor-pointer"
              data-open-cronograma="1"
              data-promesa-id="{{ $pp->id }}"
              data-dataset='@json($ds)'>
            <td class="text-nowrap">{{ optional($pp->created_at)->format('d/m/Y') ?? '—' }}</td>
            <td class="text-nowrap">{{ $tipoLabel }}</td>
            <td class="text-nowrap">
              @if($ops) {{ implode(', ', $ops) }} @else <span class="text-secondary">—</span> @endif
            </td>
            <td class="text-end text-nowrap">S/ {{ number_format($montoNeg, 2) }}</td>

            <td class="text-center">
              @if($nota !== '')
                <i class="bi bi-journal-text" data-bs-toggle="tooltip" title="{{ $nota }}"></i>
              @else
                <span class="text-secondary">—</span>
              @endif
            </td>

            <td class="text-center">
              @if(strtolower($pp->workflow_estado ?? '') === 'aprobada')
                <a class="btn btn-outline-primary btn-sm"
                   href="{{ route('promesas.acuerdo', $pp) }}"
                   target="_blank" data-bs-toggle="tooltip" title="Descargar acuerdo en PDF">
                  <i class="bi bi-filetype-pdf"></i>
                </a>
              @else
                <span class="text-secondary">—</span>
              @endif
            </td>

            <td class="text-nowrap">{{ $pp->user->name ?? '—' }}</td>
            <td class="text-nowrap">{{ $estado }}</td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-secondary">Sin promesas</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>