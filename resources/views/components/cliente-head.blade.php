{{-- resources/views/components/cliente-head.blade.php --}}

@props([
    'dni',
    'titular',
    'cuentas' => collect(),
    'pagos' => collect(),
    'promesas' => collect(),
    'ccdByDni' => [],
])

@php
    $cuentasCol = collect($cuentas);
    $pagosCol   = collect($pagos);
    $promCol    = collect($promesas);

    $totCapital = (float) $cuentasCol->sum(fn($x)=>(float)($x->deuda_capital ?? $x->saldo_capital ?? 0));
    $totDeuda   = (float) $cuentasCol->sum(fn($x)=>(float)($x->deuda_total ?? 0));
    $totPagos   = (float) $pagosCol->sum(fn($p)=>(float)($p->monto_pagado ?? $p->monto ?? 0));

    $ccdDocs = collect($ccdByDni[$dni] ?? []);
@endphp

<div class="card pad cli-head mb-3 border-0 shadow-sm bg-white/90 relative z-[60]">
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
        
        {{-- Bloque de Identidad --}}
        <div class="min-w-[300px]">
            <h1 class="text-lg font-black text-slate-800 uppercase tracking-tight mb-1 flex items-center gap-2">
                <span class="w-2 h-5 bg-emerald-500 rounded-full"></span> {{ $titular }}
            </h1>
            <div class="flex flex-wrap gap-3 items-center">
                <span class="text-[10px] font-black px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 border border-emerald-200">
                    DNI {{ $dni }}
                </span>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    {{ $cuentasCol->count() }} CUENTAS | {{ $pagosCol->count() }} PAGOS | {{ $promCol->count() }} PROMESAS
                </span>
            </div>
        </div>

        {{-- Bloque de Datos Financieros y Cartas --}}
        <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
            
            <div class="flex gap-2">
                <x-kpi-item label="DEUDA CAPITAL" :value="'S/ '.number_format($totCapital,2)" />
                <x-kpi-item label="DEUDA TOTAL" :value="'S/ '.number_format($totDeuda,2)" />
                <x-kpi-item label="PAGOS" :value="'S/ '.number_format($totPagos,2)" color="emerald" />
            </div>

            {{-- Selección de Cartas con Dropdown (Arreglado) --}}
            <div class="h-14 flex items-center border-l border-slate-200 pl-3 ml-1">
                @if($ccdDocs->isEmpty())
                    <div class="text-[10px] font-bold text-slate-300 italic">SIN CARTAS</div>
                @elseif($ccdDocs->count() === 1)
                    @php
                        $d = $ccdDocs->first();
                        $href = $d->link ?: (preg_match('#^https?://#', (string)$d->pdf) ? $d->pdf : asset('storage/ccd/'.ltrim((string)$d->pdf,'/')));
                    @endphp
                    <a href="{{ $href }}" target="_blank" class="btn btn-sm btn-outline-primary px-4 py-2 rounded-xl text-[10px] font-black">
                        <i class="bi bi-file-earmark-pdf"></i> VER CCD
                    </a>
                @else
                    {{-- Dropdown --}}
                    <div class="dropdown" data-dd="ccd">
                    <button
                        type="button"
                        class="btn btn-sm btn-primary px-4 py-2 rounded-xl text-[10px] font-black shadow-lg shadow-blue-500/20 inline-flex items-center gap-2"
                        data-dd-btn
                        aria-expanded="false"
                    >
                        <i class="bi bi-envelope-paper-fill"></i>
                        CARTAS ({{ $ccdDocs->count() }})
                        <i class="bi bi-chevron-down text-[10px] opacity-80"></i>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end shadow-xl border-0 rounded-2xl p-2 mt-2 z-50" data-dd-menu>
                        @foreach($ccdDocs as $d)
                        @php
                            $href = $d->link ?: (preg_match('#^https?://#', (string)$d->pdf) ? $d->pdf : asset('storage/ccd/'.ltrim((string)$d->pdf,'/')));
                            $name = $d->cosecha ? "CCD {$d->cosecha}" : ($d->pdf ? pathinfo((string)$d->pdf, PATHINFO_FILENAME) : "Carta #{$d->id}");
                        @endphp
                        <li>
                            <a
                            class="dropdown-item rounded-xl py-2 px-3 text-xs font-bold text-slate-600 hover:bg-blue-50 hover:text-blue-700 flex justify-between items-center transition-all"
                            href="{{ $href }}" target="_blank"
                            data-dd-item
                            >
                            <span class="truncate">{{ $name }}</span>
                            <i class="bi bi-box-arrow-up-right opacity-50"></i>
                            </a>
                        </li>
                        @endforeach
                    </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>