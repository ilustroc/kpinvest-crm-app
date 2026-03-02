{{-- resources/views/autorizacion/partials/_tab-cna.blade.php --}}
<section data-tab="cna" class="autz-card autz-tab hidden">
    <div class="autz-card-head">
        <div class="autz-title">Solicitudes de CNA</div>
    </div>

    <div class="autz-table-wrap">
        <table class="autz-table">
            <thead>
                <tr>
                    <th class="text-center">DNI</th>
                    <th>Producto</th>
                    <th class="text-center">Operación</th>
                    <th class="text-center">Fecha</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($cnaRows as $cna)
                @php
                    $ops = collect((array)($cna->operaciones ?? []))->filter()->values();
                    $productos = $ops->map(fn($op) => $prodByOp[(string)$op] ?? null)->filter()->unique()->values();
                    $productoTxt = $productos->isEmpty() ? '—' : $productos->implode(' · ');
                @endphp
                <tr>
                    <td class="text-center font-semibold">{{ $cna->dni }}</td>
                    <td>{{ $productoTxt }}</td>
                    <td class="text-center">{{ $ops->implode(', ') }}</td>
                    <td class="text-center">{{ optional($cna->created_at)->format('Y-m-d') }}</td>
                    <td class="text-end">
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" class="autz-btn autz-btn-sm autz-btn-ghost js-ver-cna"
                                data-dni="{{ $cna->dni }}" data-nrocarta="{{ $cna->nro_carta }}" data-producto="{{ $productoTxt }}"
                                data-operaciones='@json($ops)' data-fecha="{{ optional($cna->created_at)->format('Y-m-d') }}"
                                data-fecha-pago="{{ $cna->fecha_pago_realizado }}" data-monto-pagado="{{ (float)$cna->monto_pagado }}"
                                data-observacion="{{ $cna->observacion }}">Ficha</button>
                            
                            @if($isSupervisor)
                                <button type="button" class="autz-btn autz-btn-sm autz-btn-primary js-open-nota" data-action="{{ route('cna.preaprobar', $cna) }}">Pre-aprobar</button>
                            @else
                                <button type="button" class="autz-btn autz-btn-sm autz-btn-primary js-open-nota" data-action="{{ route('cna.aprobar', $cna) }}">Aprobar</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-slate-500">Sin solicitudes de CNA.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>