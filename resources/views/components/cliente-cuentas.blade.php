{{-- resources/views/components/cliente-cuentas.blade.php --}}
@props([
  'dni',
  'cuentas' => collect(),
  'cnasByCuenta' => [],
  'ccdByDni' => [],
])

@php
  $cuentasCol = $cuentas instanceof \Illuminate\Support\Collection ? $cuentas : collect($cuentas);
@endphp

<div class="card pad mb-4 border-0 shadow-sm bg-white/90 overflow-hidden">
  {{-- Header tipo Cliente-Header --}}
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
    <div class="min-w-[260px]">
      <div class="flex items-center gap-2">
        <span class="w-2 h-5 bg-emerald-500 rounded-full"></span>
        <h2 class="text-base font-black text-slate-800 uppercase tracking-tight">
          Cuentas
        </h2>
      </div>
      <div class="mt-1 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
        {{ $cuentasCol->count() }} cuentas registradas · selecciona operaciones para generar propuesta
      </div>
    </div>

    <button
      class="btn btn-primary shadow-lg shadow-emerald-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
      id="btnPropuesta"
      type="button"
      data-modal-open="#modalPropuesta"
      disabled
    >
      <i class="bi bi-plus-circle-fill"></i>
      Generar Propuesta
      <span class="ms-2 px-2 py-0.5 rounded-full bg-white/20 text-[10px] font-black" id="selCount">0</span>
    </button>
  </div>

  {{-- Tabla --}}
  <div class="table-responsive max-h-[420px] border border-slate-200/60 rounded-2xl">
    <table class="table table-sm table-hover align-middle tbl-compact mb-0 w-full" id="tblCuentas">
      <thead>
        <tr>
          <th class="text-center" style="width:42px">
            <input type="checkbox" id="chkAll" class="rounded border-slate-300">
          </th>

          <th class="text-nowrap">Operación</th>
          <th class="text-nowrap">Asesor</th>
          <th>Entidad / Producto</th>
          <th class="text-nowrap">Cosecha</th>
          <th class="text-end text-nowrap">Capital</th>
          <th class="text-end text-nowrap">Total</th>
          <th class="text-center text-nowrap">CNA</th>
          <th class="text-end text-nowrap">Pagos</th>
        </tr>
      </thead>

      <tbody>
      @foreach ($cuentasCol as $c)
        @php
          $cnt = (int)($c->pagos_count ?? 0);
          $sum = (float)($c->pagos_sum ?? 0);

          $hasList = isset($c->pagos_list) && (
            ($c->pagos_list instanceof \Illuminate\Support\Collection && $c->pagos_list->count()) ||
            (is_array($c->pagos_list) && count($c->pagos_list))
          );

          $cnas   = collect($cnasByCuenta[$c->cuenta] ?? []);
          $last   = $cnas->sortByDesc(fn($x) => $x->created_at)->first();
          $estado = strtolower((string)($last->workflow_estado ?? ''));

          $collapseId = 'pagos-'.$loop->index;
        @endphp

        <tr
          class="group"
          data-cuenta="{{ $c->cuenta }}"
          data-oper="{{ $c->operacion }}"
          data-cosecha="{{ $c->cosecha }}"
          data-entidad="{{ $c->entidad }}"
        >
          {{-- check --}}
          <td class="text-center">
            <input
              type="checkbox"
              class="chkOp rounded border-slate-300 text-emerald-600"
              value="{{ $c->operacion }}"
              {{ empty($c->operacion) ? 'disabled' : '' }}
            >
          </td>

          {{-- operación --}}
          <td class="text-nowrap">
            <span class="inline-flex items-center gap-2">
              <span class="font-black text-slate-800 tabular-nums">
                {{ $c->operacion ?? '—' }}
              </span>
            </span>
          </td>

          {{-- asesor --}}
          <td class="text-nowrap">
            @if($c->asesor)
              <span class="font-semibold text-slate-700">{{ $c->asesor }}</span>
            @else
              <span class="text-slate-400 italic">Sin asignar</span>
            @endif
          </td>

          {{-- entidad --}}
          <td class="min-w-[180px]">
            <div class="text-xs font-semibold text-slate-600 uppercase">
              {{ $c->entidad ?? '—' }}
            </div>
            @if(!empty($c->producto))
              <div class="text-[11px] text-slate-400">
                {{ $c->producto }}
              </div>
            @endif
          </td>

          {{-- cosecha --}}
          <td class="text-nowrap">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black
                         bg-slate-50 text-slate-600 border border-slate-200/70">
              {{ $c->cosecha ?? '—' }}
            </span>
          </td>

          {{-- capital --}}
          <td class="text-end text-nowrap font-bold text-slate-700 tabular-nums">
            S/ {{ number_format((float)($c->deuda_capital ?? $c->saldo_capital ?? 0), 2) }}
          </td>

          {{-- total --}}
          <td class="text-end text-nowrap font-black text-slate-900 tabular-nums">
            S/ {{ number_format((float)($c->deuda_total ?? 0), 2) }}
          </td>

          {{-- CNA --}}
          <td class="text-center text-nowrap">
            @if($last && (str_contains($estado,'pend') || str_contains($estado,'pre')))
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black
                           bg-amber-100 text-amber-700 border border-amber-200 uppercase">
                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                Pendiente
              </span>

            @elseif($last && str_contains($estado,'aprob') && !str_contains($estado,'pre'))
              <a
                href="{{ route('cna.pdf', $last->id) }}"
                class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black
                       bg-rose-100 text-rose-700 border border-rose-200 uppercase hover:bg-rose-200 transition"
                title="Descargar CNA aprobada (PDF)"
              >
                <i class="bi bi-file-earmark-pdf me-1"></i> Aprobada
              </a>

            @else
              <button
                type="button"
                class="genCnaBtn inline-flex items-center justify-center w-9 h-9 rounded-xl
                       bg-emerald-600/10 text-emerald-700 border border-emerald-600/20
                       hover:bg-emerald-600 hover:text-white transition"
                title="Generar CNA"
                data-dni="{{ $dni }}"
                data-cuenta="{{ $c->cuenta }}"
                data-oper="{{ $c->operacion }}"
                data-cosecha="{{ $c->cosecha }}"
                data-entidad="{{ $c->entidad }}"
                data-bs-toggle="modal"
                data-bs-target="#modalCna"
              >
                <i class="bi bi-hammer"></i>
              </button>
            @endif
          </td>

          {{-- Pagos --}}
          <td class="text-end text-nowrap">
            <div class="flex flex-col items-end">
              <span class="text-[11px] font-black text-emerald-600 leading-none tabular-nums">
                S/ {{ number_format($sum, 2) }}
              </span>

              <div class="mt-1 flex items-center gap-2">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                  {{ $cnt }} pagos
                </span>

                @if($hasList)
                  <button
                    class="btn btn-sm btn-outline-secondary !px-2 !py-1"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#{{ $collapseId }}"
                    aria-expanded="false"
                    aria-controls="{{ $collapseId }}"
                  >
                    Ver
                    <i class="bi bi-chevron-down ms-1"></i>
                  </button>
                @endif
              </div>
            </div>

            @if($hasList)
              <div class="collapse mt-2" id="{{ $collapseId }}">
                <div class="rounded-xl border border-slate-200/70 bg-white/70 p-2 text-left">
                  <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">
                    Detalle de pagos
                  </div>

                  <div class="space-y-1">
                    @foreach($c->pagos_list as $p)
                      @php
                        $f = !empty($p->fecha) ? \Carbon\Carbon::parse($p->fecha)->format('d/m/Y') : '—';
                        $m = number_format((float)($p->monto ?? 0), 2);
                        $src = $p->fuente ?? null;
                      @endphp

                      <div class="flex items-center justify-between text-[11px]">
                        <span class="font-mono text-slate-400">{{ $f }}</span>
                        <span class="font-black text-slate-700 tabular-nums">S/ {{ $m }}</span>
                      </div>

                      @if($src)
                        <div class="text-[10px] text-slate-400 -mt-0.5">{{ $src }}</div>
                      @endif

                      <div class="border-b border-slate-200/50"></div>
                    @endforeach
                  </div>
                </div>
              </div>
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>