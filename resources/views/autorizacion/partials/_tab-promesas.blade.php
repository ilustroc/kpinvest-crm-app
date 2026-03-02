{{-- resources/views/autorizacion/partials/_tab-promesas.blade.php --}}
<section data-tab="promesas" class="autz-card autz-tab">
    <div class="autz-card-head">
        <div>
            <div class="autz-title">Pendientes de autorización</div>
        </div>
    </div>

    {{-- DESKTOP --}}
    <div class="hidden md:block">
        <div class="autz-table-wrap">
            <table class="autz-table autz-table-fixed">
                <colgroup>
                    <col style="width:110px">
                    <col style="width:140px">
                    <col style="width:120px">
                    <col style="width:130px">
                    <col>
                    <col style="width:260px">
                </colgroup>
                <thead>
                    <tr>
                        <th class="text-center">DNI</th>
                        <th class="text-center">Operación(es)</th>
                        <th class="text-center">Fecha</th>
                        <th class="text-end">Monto (S/)</th>
                        <th>Nota</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $p)
                    @php
                        $fechaYmd = $p->fecha_promesa ? substr((string)$p->fecha_promesa,0,10) : '—';
                        $fechaDmy = $p->fecha_promesa ? \Carbon\Carbon::parse($p->fecha_promesa)->format('d/m/Y') : '';
                        $montoMostrar = (float)($p->monto > 0 ? $p->monto : $p->monto_convenio);
                        $notaFull = trim((string)($p->nota ?? ''));
                    @endphp
                    <tr>
                        <td class="text-center font-semibold text-slate-900">{{ $p->dni }}</td>
                        <td class="text-center">{{ $p->operacion ?: '—' }}</td>
                        <td class="text-center">{{ $fechaYmd }}</td>
                        <td class="text-end font-extrabold text-slate-900">{{ number_format($montoMostrar, 2) }}</td>
                        <td>
                            <div class="autz-note-cell">
                                <div class="autz-note-preview">{{ $notaFull ?: '—' }}</div>
                                @if($notaFull !== '')
                                    <button type="button" class="autz-link js-show-note" data-title="Nota (DNI {{ $p->dni }})" data-text='@json($notaFull)'>Ver más</button>
                                @endif
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="autz-actions-btns">
                                <button type="button" class="autz-btn autz-btn-sm autz-btn-ghost js-ver-ficha" 
                                    data-tipo="{{ $p->tipo }}" data-dni="{{ $p->dni }}" data-operacion="{{ $p->operacion ?? '' }}"
                                    data-fecha="{{ $fechaDmy }}" data-asesor="{{ $p->asesor_nombre ?: $p->creador_nombre ?: '—' }}"
                                    data-titular="{{ $p->titular ?? '—' }}" data-deuda="{{ number_format((float)($p->deuda_total ?? 0),2) }}"
                                    data-negociado="{{ number_format($montoMostrar, 2) }}" data-nota-sup="{{ $p->nota_preaprobacion ?? '' }}"
                                    data-nota-gen="{{ $p->nota ?? '' }}" data-crono='@json($p->cuotas_json ?? [])'
                                    data-hasbalon="{{ ($p->has_balon ?? false) ? 1 : 0 }}" data-cuentas='@json($p->cuentas_cliente_json ?? [])'>
                                    Ficha
                                </button>
                                @if($isSupervisor)
                                    <button type="button" class="autz-btn autz-btn-sm autz-btn-primary js-open-nota" data-title="Pre-aprobar" data-action="{{ route('autorizacion.preaprobar',$p) }}">Pre-aprobar</button>
                                    <button type="button" class="autz-btn autz-btn-sm autz-btn-danger js-open-rechazo" data-action="{{ route('autorizacion.rechazar.sup',$p) }}">Rechazar</button>
                                @else
                                    <button type="button" class="autz-btn autz-btn-sm autz-btn-primary js-open-nota" data-title="Aprobar" data-action="{{ route('autorizacion.aprobar',$p) }}">Aprobar</button>
                                    <button type="button" class="autz-btn autz-btn-sm autz-btn-danger js-open-rechazo" data-action="{{ route('autorizacion.rechazar.admin',$p) }}">Rechazar</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Sin pendientes.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MOBILE --}}
    <div class="md:hidden space-y-3">
        @foreach($rows as $p)
            {{-- (Misma lógica de datos que desktop, resumida para cards móviles) --}}
            <div class="autz-card autz-card-row">
                <div class="flex justify-between items-start">
                    <div class="text-sm font-extrabold">{{ $p->dni }}</div>
                    <div class="text-lg font-black text-emerald-700">S/ {{ number_format($p->monto > 0 ? $p->monto : $p->monto_convenio, 2) }}</div>
                </div>
                <div class="mt-3 flex gap-2">
                    <button type="button" class="autz-btn autz-btn-sm autz-btn-ghost flex-1 js-ver-ficha" {{-- data-attributes iguales --}}>Ficha</button>
                    <button type="button" class="autz-btn autz-btn-sm autz-btn-primary flex-1 js-open-nota" {{-- data-attributes --}}>{{ $isSupervisor ? 'Pre-aprob' : 'Aprobar' }}</button>
                </div>
            </div>
        @endforeach
    </div>

    @if(method_exists($rows,'links'))
        <div class="pt-3">{{ $rows->withQueryString()->onEachSide(1)->links() }}</div>
    @endif
</section>