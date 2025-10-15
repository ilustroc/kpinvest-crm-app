<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\WorkflowMailer;

use App\Models\PromesaPago;
use App\Models\PromesaOperacion;
use App\Models\PromesaCuota;
use App\Models\PagoPropia as Pago;

use App\Models\ClienteCuenta;
use App\Models\CnaSolicitud;

class ClientsControllers extends Controller
{
    public function index(Request $r)
    {
        $q  = trim((string)$r->query('q',''));
        $pp = (int)($r->query('pp', 20)) ?: 20;

        $clientes = ClienteCuenta::query()
            ->select('dni','operacion','titular','updated_at')   // ← sin 'cartera'
            ->when($q !== '', function ($w) use ($q) {
                $w->where('dni','like',"%{$q}%")
                  ->orWhere('operacion','like',"%{$q}%")
                  ->orWhere('titular','like',"%{$q}%");
                  // quitado: orWhere('cartera','like', ...)
            })
            ->orderByDesc('updated_at')
            ->paginate($pp)->withQueryString();

        return view('clientes.index', compact('clientes','q'));
    }

    public function show(string $dni)
    {
        try {
            // ===== Cuentas del cliente
            $cuentas = ClienteCuenta::where('dni',$dni)
                ->orderByDesc('updated_at')
                ->get();

            abort_if($cuentas->isEmpty(), 404);
            $titular = $cuentas->first()->titular;

            // ===== A) Pagos (UNIFICADO)
            $pagos = Pago::where('dni',$dni)->select(
                    DB::raw('DATE(fecha_de_pago) as fecha'),
                    DB::raw('pagado_en_soles as monto'),
                    'operacion as oper',
                    DB::raw("UPPER(COALESCE(gestor, equipos, '-')) as gestor"),
                    DB::raw("UPPER(COALESCE(status, '-')) as estado"),
                    DB::raw("'PAGOS' as fuente")
                )
                ->orderByDesc('fecha_de_pago')
                ->get();

            $totPagos = (float) $pagos->sum('monto');

            // ===== B) Promesas
            $promesas = PromesaPago::where('dni',$dni)
                ->when(method_exists(PromesaPago::class,'scopeWithDecisionRefs'), fn($q)=>$q->withDecisionRefs())
                ->with('operaciones')
                ->orderByDesc('fecha_promesa')
                ->get();

            // ===== C) CCD (opcional si existe tabla)
            $ccd        = collect();
            $ccdByCodigo= collect();

            if (Schema::hasTable('ccd_clientes')) {
                $cols = DB::getSchemaBuilder()->getColumnListing('ccd_clientes');
                $sel  = collect(['id','dni','codigo','documento','nombre','pdf','archivo','ruta','url','created_at'])
                        ->filter(fn($c)=>in_array($c,$cols))->all();

                if (!empty($sel)) {
                    $ccd = DB::table('ccd_clientes')
                        ->select($sel)
                        ->where('dni', $dni)
                        ->orderByDesc('id')
                        ->get();

                    if (in_array('codigo', $cols)) {
                        $ccdByCodigo = $ccd->groupBy('codigo');
                    }
                }
            }

            // ===== D) Métricas por operación (desde pagos unificados)
            $ops = $cuentas->pluck('operacion')->filter()->unique()->values();

            $pagosPorOperacion = collect();
            if ($ops->isNotEmpty()) {
                $pagosFlat = DB::table('pagos')->select([
                        'operacion',
                        DB::raw('fecha_de_pago as fecha'),
                        DB::raw('pagado_en_soles as monto'),
                        DB::raw("'PAGOS' as fuente"),
                    ])
                    ->where('dni',$dni)
                    ->whereIn('operacion',$ops)
                    ->get();

                $pagosPorOperacion = $pagosFlat->groupBy('operacion');

                $cuentas = $cuentas->map(function ($c) use ($pagosPorOperacion) {
                    $opsKey = $c->operacion;
                    $grupo = $opsKey ? ($pagosPorOperacion[$opsKey] ?? collect()) : collect();
                    $c->pagos_count = $grupo->count();
                    $c->pagos_sum   = (float)$grupo->sum('monto');
                    $c->pagos_list  = $grupo->sortByDesc('fecha')->values();
                    return $c;
                });
            } else {
                $cuentas = $cuentas->map(function ($c) {
                    $c->pagos_count = 0;
                    $c->pagos_sum   = 0.0;
                    $c->pagos_list  = collect();
                    return $c;
                });
            }

            // ===== E) CNAs por operación (defensivo)
            $cnasByOperacion = collect();
            if (Schema::hasTable('cna_solicitudes')) {
                $colsCna = DB::getSchemaBuilder()->getColumnListing('cna_solicitudes');

                $want   = ['id','dni','nro_carta','operaciones','workflow_estado','created_at','pdf_path','docx_path'];
                $selCna = collect($want)->filter(fn($c)=>in_array($c,$colsCna))->values()->all();

                $cnas = DB::table('cna_solicitudes')
                    ->select($selCna)
                    ->where('dni',$dni)
                    ->orderByDesc('created_at')
                    ->get();

                $map = [];
                foreach ($cnas as $row) {
                    $opsRaw = $row->operaciones ?? '[]';
                    $opsArr = is_array($opsRaw) ? $opsRaw : (json_decode($opsRaw, true) ?: []);
                    if (!is_array($opsArr)) {
                        $opsArr = array_filter(array_map('trim', explode(',', (string)$opsRaw)));
                    }

                    foreach ($opsArr as $op) {
                        if (!$op) continue;
                        $map[$op] = $map[$op] ?? collect();
                        $map[$op]->push((object)[
                            'id'              => $row->id,
                            'nro_carta'       => $row->nro_carta ?? $row->id,
                            'workflow_estado' => $row->workflow_estado ?? 'pendiente',
                            'created_at'      => $row->created_at,
                            'pdf_path'        => $row->pdf_path   ?? null,
                            'docx_path'       => $row->docx_path  ?? null,
                        ]);
                    }
                }
                $cnasByOperacion = collect($map);
            }

            // ===== F) Próximo N.º de carta CNA (para modal)
            $nextNroCarta = null;
            if (Schema::hasTable('cna_solicitudes')) {
                $maxCorr = (int) DB::table('cna_solicitudes')->max('correlativo');
                $nextNroCarta = str_pad(($maxCorr ?: 0) + 1, 6, '0', STR_PAD_LEFT);
            }

            return view('clientes.show', compact(
                'dni','titular','cuentas','pagos','promesas','ccd','pagosPorOperacion','totPagos'
            ) + [
                'ccdByCodigo'     => $ccdByCodigo,
                'cnasByOperacion' => $cnasByOperacion,
                'nextNroCarta'    => $nextNroCarta,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Clientes.show ERROR', ['dni'=>$dni,'msg'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
            return back()->withErrors('Ocurrió un error cargando el cliente. Revisa los logs.');
        }
    }

    public function storePromesa(string $dni, Request $r)
    {
        $r->merge(['dni' => $dni]);

        if ($r->input('tipo') === 'cancelacion' && $r->filled('fecha_pago_cancel')) {
            $r->merge(['fecha_pago' => $r->input('fecha_pago_cancel')]);
        }

        // ===== VALIDACIÓN
        $rules = [
            'dni'           => 'required|string|max:30',
            'tipo'          => 'required|in:convenio,cancelacion',
            'nota'          => 'nullable|string|max:500',

            // Lista de operaciones obligatoria
            'operaciones'   => 'required|array|min:1',
            'operaciones.*' => 'string|max:50',

            // Cancelación
            'fecha_pago'    => 'exclude_unless:tipo,cancelacion|required|date',
            'monto_cancel'  => 'exclude_unless:tipo,cancelacion|required|numeric|min:0.01',

            // Convenio
            'nro_cuotas'     => 'exclude_unless:tipo,convenio|required|integer|min:1',
            'monto_convenio' => 'exclude_unless:tipo,convenio|required|numeric|min:0.01',
            'cron_fecha'     => 'exclude_unless:tipo,convenio|required|array|min:1',
            'cron_fecha.*'   => 'exclude_unless:tipo,convenio|date',
            'cron_monto'     => 'exclude_unless:tipo,convenio|required|array|min:1',
            'cron_monto.*'   => 'exclude_unless:tipo,convenio|numeric|min:0.01',
            'cron_balon'     => 'exclude_unless:tipo,convenio|nullable|integer|min:1',
        ];
        $r->validate($rules);

        // ===== NORMALIZACIONES
        if ($r->filled('fecha_pago')) {
            $r->merge(['fecha_pago' => $this->toIsoDate($r->input('fecha_pago'))]);
        }

        $cronFechas = array_map(fn($f)=>$this->toIsoDate($f), (array)$r->input('cron_fecha', []));
        $cronMontos = array_map(fn($m)=>$this->normalizeMoney($m), (array)$r->input('cron_monto', []));
        $cronBalon  = (int)$r->input('cron_balon', 0);

        foreach (['monto_convenio','monto_cancel'] as $fld) {
            if ($r->has($fld)) $r->merge([$fld => $this->normalizeMoney($r->input($fld))]);
        }

        // ===== REGLAS CONVENIO
        if ($r->input('tipo') === 'convenio') {
            $n = max(1, (int)$r->input('nro_cuotas'));
            if (count($cronFechas) !== $n || count($cronMontos) !== $n) {
                $cronFechas = array_slice($cronFechas, 0, $n);
                $cronMontos = array_slice($cronMontos, 0, $n);
                while (count($cronFechas) < $n) $cronFechas[] = $cronFechas ? end($cronFechas) : now()->toDateString();
                while (count($cronMontos) < $n) $cronMontos[] = 0;
            }
            $suma = array_sum(array_map('floatval', $cronMontos));
            if (abs($suma - (float)$r->input('monto_convenio')) > 0.01) {
                return back()->withErrors('La suma del cronograma (S/ '.number_format($suma,2).') debe coincidir con el Monto convenio.')
                            ->withInput();
            }
            if ($cronBalon > 0 && $cronBalon > $n) {
                return back()->withErrors('La cuota balón no existe en el cronograma.')->withInput();
            }
        }

        // ===== PERSISTENCIA
        DB::beginTransaction();
        try {
            $base = [
                'dni'                 => $dni,
                'nota'                => $r->input('nota'),
                'tipo'                => $r->input('tipo'),
                'workflow_estado'     => 'pendiente',
                'cumplimiento_estado' => 'pendiente',
                'user_id'             => $r->user()->id ?? null,
            ];

            if ($r->input('tipo') === 'convenio') {
                $n = max(1, (int)$r->input('nro_cuotas'));
                $firstDate = Carbon::parse($cronFechas[0] ?? now());
                $avgCuota = $n > 0 ? (array_sum(array_map('floatval', $cronMontos)) / $n) : 0;

                $data = array_merge($base, [
                    'fecha_promesa'  => now()->toDateString(),
                    'fecha_pago'     => $firstDate->toDateString(),
                    'cuota_dia'      => (int)$firstDate->day,
                    'nro_cuotas'     => $n,
                    'monto_convenio' => $r->input('monto_convenio'),
                    'monto_cuota'    => $avgCuota,
                ]);
            } else { // cancelación
                $fecha = Carbon::parse($r->input('fecha_pago'));
                $data = array_merge($base, [
                    'fecha_promesa' => $fecha->toDateString(),
                    'fecha_pago'    => $fecha->toDateString(),
                    'monto'         => $r->input('monto_cancel'),
                ]);
            }

            /** @var \App\Models\PromesaPago $promesa */
            $promesa = PromesaPago::create($data);

            // --- Operaciones (limpias, únicas) ---
            $ops = collect($r->input('operaciones', []))
                ->map(fn($op)=>trim((string)$op))
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Legacy: guarda TODAS en cadena "op1, op2"
            $promesa->operacion = implode(', ', $ops);
            $promesa->save();

            // Detalle: una fila por operación (sin 'cartera')
            $now = now();
            $rows = [];
            foreach ($ops as $op) {
                $rows[] = [
                    'promesa_id' => $promesa->id,
                    'operacion'  => $op,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            PromesaOperacion::insert($rows);

            // Cronograma (solo convenio)
            if ($promesa->tipo === 'convenio') {
                $rows = [];
                foreach ($cronFechas as $i => $f) {
                    $rows[] = [
                        'promesa_id' => $promesa->id,
                        'nro'        => $i + 1,
                        'fecha'      => Carbon::parse($f)->toDateString(),
                        'monto'      => (float)$cronMontos[$i],
                        'es_balon'   => ($cronBalon === ($i + 1)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                PromesaCuota::insert($rows);
            }

            DB::commit();

            WorkflowMailer::promesaPendiente($promesa);
            return back()->with('ok', 'Propuesta registrada y enviada para autorización.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors($e->getMessage())->withInput();
        }
    }

    /** Normaliza fechas a formato ISO (YYYY-MM-DD). */
    private function toIsoDate(?string $v): ?string
    {
        $v = trim((string)$v);
        if ($v === '') return null;

        if (preg_match('~^\d{1,2}/\d{1,2}/\d{4}$~', $v)) {
            [$d,$m,$y] = explode('/', $v);
            return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
        }
        if (preg_match('~^\d{4}-\d{1,2}-\d{1,2}$~', $v)) {
            return $v;
        }
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /** Normaliza montos: "1.234,56", "S/ 200" → "1234.56". */
    private function normalizeMoney(?string $v): ?string
    {
        $v = trim((string)$v);
        if ($v === '') return null;

        $v = preg_replace('/[^0-9\-\.,]/', '', $v) ?? '';
        $hasComma = strpos($v, ',') !== false;
        $hasDot   = strpos($v, '.') !== false;

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($v, ',');
            $lastDot   = strrpos($v, '.');
            if ($lastComma > $lastDot) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } else {
                $v = str_replace(',', '', $v);
            }
        } elseif ($hasComma && !$hasDot) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(',', '', $v);
        }

        return is_numeric($v) ? $v : null;
    }
}
