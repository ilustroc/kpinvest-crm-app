<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\WorkflowMailer;
use App\Models\PromesaPago;
use App\Models\PromesaOperacion;
use App\Models\PromesaCuota;
use App\Models\PagoPropia as Pago;
use App\Models\ClienteCuenta;
use App\Models\CcdCliente;
use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ClientsControllers extends Controller
{
    public function quickLookup(Request $r)
    {
        $q = trim((string)$r->query('q', ''));
        if ($q === '') {
            return back()->withInput()->with('quick_error', 'Ingresa DNI, Operación o Nombre.');
        }

        $base = DB::table('clientes_cuentas');

        // 1) DNI exacto (solo dígitos largos)
        if (preg_match('/^\d{6,}$/', $q)) {
            $dni = (clone $base)->where('numdoc', $q)->value('numdoc');
            if ($dni) return redirect()->route('clientes.show', $dni);

            // 2) Operación exacta
            $dniOp = (clone $base)->where('operacion', $q)->value('numdoc');
            if ($dniOp) return redirect()->route('clientes.show', $dniOp);
        }

        // 3) Coincidencias por cualquier campo (LIKE)
        $cands = $this->candidateDnies($q, 20); // devuelve collection de DNIs

        if ($cands->isEmpty()) {
            return back()->withInput()->with('quick_error', 'Cliente no ubicado.');
        }
        if ($cands->count() === 1) {
            return redirect()->route('clientes.show', $cands->first());
        }

        // 4) Armar lista (un row representativo por DNI)
        $rows = DB::table('clientes_cuentas')
            ->whereIn('numdoc', $cands)
            ->orderByDesc('updated_at')
            ->get(['numdoc as dni','nombre','operacion','cosecha','updated_at'])
            ->unique('dni')
            ->values();

        return back()->withInput()->with('quick_list', $rows->toArray());
    }

    /** Endpoint para autocompletar (JSON) */
    public function suggest(Request $r)
    {
        $q = trim((string)$r->query('q', ''));
        if ($q === '') return response()->json([]);

        $dnies = $this->candidateDnies($q, 8);

        $rows = DB::table('clientes_cuentas')
            ->whereIn('numdoc', $dnies)
            ->orderByDesc('updated_at')
            ->get(['numdoc as dni','nombre','operacion','cosecha'])
            ->unique('dni')
            ->values()
            ->map(function($r){
                return [
                    'dni'       => $r->dni,
                    'nombre'    => $r->nombre,
                    'operacion' => $r->operacion,
                    'cosecha'   => $r->cosecha,
                    'url'       => route('clientes.show', $r->dni),
                ];
            });

        return response()->json($rows);
    }

    /** Busca DNIs candidatos por DNI/operación/nombre (LIKE + agrupación) */
    private function candidateDnies(string $q, int $limit = 10)
    {
        $q = trim($q);
        $base = DB::table('clientes_cuentas');

        return (clone $base)
            ->selectRaw('numdoc, MAX(updated_at) as u')
            ->where(function($w) use ($q){
                $w->where('numdoc', 'like', "%{$q}%")
                ->orWhere('operacion', 'like', "%{$q}%")
                ->orWhere('nombre', 'like', "%{$q}%");
            })
            ->groupBy('numdoc')
            ->orderByDesc('u')
            ->limit($limit)
            ->pluck('numdoc');
    }


    /* ==========================
     * Ficha del cliente
     * ========================== */
    public function show(string $dni)
    {
        try {
            /* ===== CUENTAS (por DNI) ===== */
            $cuentas = ClienteCuenta::query()
                ->where('numdoc', $dni)
                ->orderByDesc('updated_at')
                ->get([
                    'numdoc','nombre','cuenta','operacion','entidad','producto','cosecha',
                    'deuda_capital','interes','deuda_total',
                ]);

            abort_if($cuentas->isEmpty(), 404);
            $titular = $cuentas->first()->nombre ?? '—';

            /* ===== PAGOS (por DNI) ===== */
            $pagos = Pago::query()
                ->where('dni', $dni)
                ->orderByDesc('fecha')
                ->get([
                    'fecha','dni','operacion','entidad','nombre_cliente','monto_pagado',
                    'gestor','cosecha','cuenta_recaudo',
                ]);
            $totPagos = (float) $pagos->sum('monto_pagado');

            /* ===== CCD (SIEMPRE por DNI/numdoc) ===== */
            $ccdDocs    = collect();
            $ccdByDni   = collect();
            $ccdByCodigo= collect();

            if (Schema::hasTable('ccd_clientes')) {
                // Usa solo columnas reales (si falta 'codigo' no pasa nada)
                $colsCcd = DB::getSchemaBuilder()->getColumnListing('ccd_clientes');
                $selCcd  = collect(['id','numdoc','pdf','cosecha','link','codigo'])
                            ->filter(fn($c)=>in_array($c, $colsCcd))->all();

                $ccdDocs = CcdCliente::query()
                    ->where('numdoc', $dni)
                    ->orderByDesc('id')
                    ->get($selCcd);

                // Fuerza la clave exacta para que la vista haga $ccdByDni[$dni] sin notice
                $ccdByDni    = collect([$dni => $ccdDocs->values()]);
                if (in_array('codigo', $colsCcd)) {
                    $ccdByCodigo = $ccdDocs->groupBy('codigo');
                }
            }

            /* ===== Mapeos de pagos para UI ===== */
            $op2cta = $pagos->groupBy('operacion')->map(function ($grp) {
                $freq = $grp->pluck('cuenta_recaudo')->filter()->countBy();
                return $freq->isEmpty() ? null : $freq->sortDesc()->keys()->first();
            });
            $pagosGrouped = $pagos->groupBy('operacion');

            // Enriquecer filas de cuentas y asegurar "cuenta"
            $cuentas = $cuentas->map(function ($c) use ($pagosGrouped, $op2cta) {
                $grupo = $pagosGrouped->get($c->operacion) ?? collect();
                $c->pagos_count = $grupo->count();
                $c->pagos_sum   = (float) $grupo->sum('monto_pagado');
                $c->pagos_list  = $grupo->sortByDesc('fecha')->take(10)->map(function ($r) {
                    return (object)[
                        'fecha'  => $r->fecha,
                        'monto'  => (float) $r->monto_pagado,
                        'fuente' => 'PAGO',
                    ];
                })->values();

                $ctaDb  = trim((string)($c->cuenta ?? ''));
                $ctaMap = trim((string)($op2cta[$c->operacion] ?? ''));
                $c->cuenta = $ctaDb !== '' ? $ctaDb : ($ctaMap !== '' ? $ctaMap : $c->operacion);
                return $c;
            });

            /* ===== PROMESAS ===== */
            $promesas = PromesaPago::query()
                ->where('dni', $dni)
                ->when(method_exists(PromesaPago::class, 'scopeWithDecisionRefs'), fn($q) => $q->withDecisionRefs())
                ->with('operaciones')
                ->orderByDesc('fecha_promesa')
                ->get();

            /* ===== CNAs ===== */
            $cnasByCuenta    = collect();
            $cnasByOperacion = collect();

            if (Schema::hasTable('cna_solicitudes')) {
                $colsCna = DB::getSchemaBuilder()->getColumnListing('cna_solicitudes');
                $want    = ['id','dni','nro_carta','operaciones','workflow_estado','created_at','pdf_path','docx_path'];
                $selCna  = collect($want)->filter(fn($c)=>in_array($c,$colsCna))->values()->all();

                $cnas = DB::table('cna_solicitudes')
                    ->select($selCna)
                    ->where('dni', $dni)
                    ->orderByDesc('created_at')
                    ->get();

                $mapCta = [];
                $mapOp  = [];

                foreach ($cnas as $row) {
                    $opsRaw = $row->operaciones ?? '[]';
                    $opsArr = is_array($opsRaw) ? $opsRaw : (json_decode($opsRaw, true) ?: []);
                    if (!is_array($opsArr)) {
                        $opsArr = array_filter(array_map('trim', explode(',', (string)$opsRaw)));
                    }

                    foreach ($opsArr as $op) {
                        // Por operación
                        $mapOp[$op] = $mapOp[$op] ?? collect();
                        $mapOp[$op]->push((object)[
                            'id'              => $row->id,
                            'nro_carta'       => $row->nro_carta ?? $row->id,
                            'workflow_estado' => $row->workflow_estado ?? 'pendiente',
                            'created_at'      => $row->created_at,
                            'pdf_path'        => $row->pdf_path   ?? null,
                            'docx_path'       => $row->docx_path  ?? null,
                        ]);

                        // Por cuenta (usa la cuenta calculada para esa operación)
                        $ctaDb = optional($cuentas->firstWhere('operacion', $op))->cuenta;
                        $cta   = (string)($ctaDb ?: ($op2cta[$op] ?? $op));
                        $mapCta[$cta] = $mapCta[$cta] ?? collect();
                        $mapCta[$cta]->push((object)[
                            'id'              => $row->id,
                            'nro_carta'       => $row->nro_carta ?? $row->id,
                            'workflow_estado' => $row->workflow_estado ?? 'pendiente',
                            'created_at'      => $row->created_at,
                            'pdf_path'        => $row->pdf_path   ?? null,
                            'docx_path'       => $row->docx_path  ?? null,
                        ]);
                    }
                }

                $cnasByCuenta    = collect($mapCta);
                $cnasByOperacion = collect($mapOp);
            }

            /* ===== Próximo N.º de carta (solo si la columna existe) ===== */
            $nextNroCarta = null;
            if (Schema::hasTable('cna_solicitudes')) {
                $colsCna = DB::getSchemaBuilder()->getColumnListing('cna_solicitudes');
                if (in_array('correlativo', $colsCna)) {
                    $maxCorr = (int) DB::table('cna_solicitudes')->max('correlativo');
                    $nextNroCarta = str_pad(($maxCorr ?: 0) + 1, 6, '0', STR_PAD_LEFT);
                }
            }

            return view('clientes.show', [
                'dni'               => $dni,
                'titular'           => $titular,
                'cuentas'           => $cuentas,
                'pagos'             => $pagos,
                'promesas'          => $promesas,
                'ccd'               => $ccdDocs,      // por si quieres depurar
                'ccdByDni'          => $ccdByDni,     // LA VISTA USARÁ ESTO
                'ccdByCodigo'       => $ccdByCodigo,
                'cnasByCuenta'      => $cnasByCuenta,
                'cnasByOperacion'   => $cnasByOperacion,
                'pagosPorOperacion' => $pagosGrouped,
                'nextNroCarta'      => $nextNroCarta,
                'totPagos'          => $totPagos,
            ]);

        } catch (Throwable $e) {
            // No atrapamos 404 ni otros HttpException (para que no "rebote" silenciosamente)
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            Log::error('Clientes.show ERROR', [
                'dni'  => $dni,
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return back()->withErrors('Error cargando el cliente: '.$e->getMessage());
        }
    }
    
    /* ==========================
     * Guardar Promesa (conv./cancel.)
     * ========================== */
    public function storePromesa(string $dni, Request $r)
    {
        $r->merge(['dni' => $dni]);

        if ($r->input('tipo') === 'cancelacion' && $r->filled('fecha_pago_cancel')) {
            $r->merge(['fecha_pago' => $r->input('fecha_pago_cancel')]);
        }

        // ===== VALIDACIÓN
        $rules = [
            'dni'           => 'required|string|max:30',
            'tipo'          => 'required|in:convenio,convenio_balon,cancelacion',
            'nota'          => 'nullable|string|max:500',
            'telefono'      => 'required|string|max:30',

            'operaciones'   => 'required|array|min:1',
            'operaciones.*' => 'string|max:50',

            // Cancelación
            'fecha_pago'    => 'exclude_unless:tipo,cancelacion|required|date',
            'monto_cancel'  => 'exclude_unless:tipo,cancelacion|required|numeric|min:0.01',

            // Convenio y Convenio (cuota balón)
            'nro_cuotas'     => 'exclude_unless:tipo,convenio,convenio_balon|required|integer|min:1',
            'monto_convenio' => 'exclude_unless:tipo,convenio,convenio_balon|required|numeric|min:0.01',
            'cron_fecha'     => 'exclude_unless:tipo,convenio,convenio_balon|required|array|min:1',
            'cron_fecha.*'   => 'exclude_unless:tipo,convenio,convenio_balon|date',
            'cron_monto'     => 'exclude_unless:tipo,convenio,convenio_balon|required|array|min:1',
            'cron_monto.*'   => 'exclude_unless:tipo,convenio,convenio_balon|numeric|min:0.01',
            'cron_balon'     => 'exclude_unless:tipo,convenio_balon|nullable|integer|min:1',
        ];
        $r->validate($rules);

        // ===== NORMALIZACIONES
        if ($r->filled('fecha_pago')) {
            $r->merge(['fecha_pago' => $this->toIsoDate($r->input('fecha_pago'))]);
        }

        $cronFechas = array_map(fn($f)=>$this->toIsoDate($f), (array)$r->input('cron_fecha', []));
        $cronMontos = array_map(fn($m)=>$this->normalizeMoney($m), (array)$r->input('cron_monto', []));
        $cronBalon  = (int)$r->input('cron_balon', 0); // 1-based cuando es convenio_balon

        foreach (['monto_convenio','monto_cancel'] as $fld) {
            if ($r->has($fld)) $r->merge([$fld => $this->normalizeMoney($r->input($fld))]);
        }

        // Operaciones (sanitizar)
        $opsSel = collect($r->input('operaciones', []))
            ->map(fn($op)=>trim((string)$op))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $tipo = $r->input('tipo'); // se guardará tal cual (convenio o convenio_balon)

        // ===== REGLAS CONVENIO / CONVENIO_BALON
        if (in_array($tipo, ['convenio','convenio_balon'], true)) {
            $n = max(1, (int)$r->input('nro_cuotas'));

            // Si es balón, esperamos n + 1 filas; si no, n filas.
            $esperadas = $n + ($tipo === 'convenio_balon' ? 1 : 0);
            if (count($cronFechas) !== $esperadas || count($cronMontos) !== $esperadas) {
                $cronFechas = array_slice($cronFechas, 0, $esperadas);
                $cronMontos = array_slice($cronMontos, 0, $esperadas);
                while (count($cronFechas) < $esperadas) $cronFechas[] = $cronFechas ? end($cronFechas) : now()->toDateString();
                while (count($cronMontos) < $esperadas) $cronMontos[] = 0;
            }

            // Validación de suma: regular == monto_convenio (excluye la cuota balón)
            $suma = array_sum(array_map('floatval', $cronMontos));
            $sumaReg = $suma;

            if ($tipo === 'convenio_balon') {
                // Corregimos índice y validamos rango
                if ($cronBalon < 1 || $cronBalon > count($cronMontos)) {
                    return back()->withErrors('Índice de cuota balón inválido.')->withInput();
                }
                $sumaReg -= (float)$cronMontos[$cronBalon - 1];
            } else {
                $cronBalon = 0; // no hay balón
            }

            if (abs($sumaReg - (float)$r->input('monto_convenio')) > 0.01) {
                return back()
                    ->withErrors('La suma de las cuotas regulares debe coincidir con el Monto convenio.')
                    ->withInput();
            }
        }

        // ===== PERSISTENCIA
        DB::beginTransaction();
        try {
            $telefono = preg_replace('/[^0-9\+]/', '', (string)$r->input('telefono', ''));
            $base = [
                'dni'                 => $dni,
                'nota'                => $r->input('nota'),
                'tipo'                => $tipo, // se guarda 'convenio' o 'convenio_balon'
                'telefono'            => $telefono,
                'workflow_estado'     => 'pendiente',
                'cumplimiento_estado' => 'pendiente',
                'user_id'             => $r->user()->id ?? null,
            ];

            if (in_array($tipo, ['convenio','convenio_balon'], true)) {
                $firstDate = Carbon::parse($cronFechas[0] ?? now());
                $nReg = max(1, (int)$r->input('nro_cuotas'));                 // nro de cuotas regulares
                $sumaReg = ($tipo === 'convenio_balon' && $cronBalon)
                    ? array_sum($cronMontos) - (float)$cronMontos[$cronBalon - 1]
                    : array_sum($cronMontos);

                $avgCuota = $nReg > 0 ? ($sumaReg / $nReg) : 0;

                $data = array_merge($base, [
                    'fecha_promesa'  => now()->toDateString(),
                    'fecha_pago'     => $firstDate->toDateString(),
                    'cuota_dia'      => (int)$firstDate->day,
                    'nro_cuotas'     => $nReg,
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

            // Operaciones (relación)
            $promesa->operacion = implode(', ', $opsSel);
            $promesa->save();

            $now = now();

            // Detalle operaciones
            if ($opsSel) {
                $rowsOps = [];
                foreach ($opsSel as $op) {
                    $rowsOps[] = [
                        'promesa_id' => $promesa->id,
                        'operacion'  => $op,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                PromesaOperacion::insert($rowsOps);
            }

            // Cronograma
            if (in_array($tipo, ['convenio','convenio_balon'], true)) {
                $rows = [];
                foreach ($cronFechas as $i => $f) {
                    $rows[] = [
                        'promesa_id' => $promesa->id,
                        'nro'        => $i + 1,
                        'fecha'      => Carbon::parse($f)->toDateString(),
                        'monto'      => (float)($cronMontos[$i] ?? 0),
                        'es_balon'   => ($tipo === 'convenio_balon' && $cronBalon === ($i + 1)) ? 1 : 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                PromesaCuota::insert($rows);
            }

            DB::commit();

            WorkflowMailer::promesaPendiente($promesa);
            return back()->with('ok', 'Propuesta registrada y enviada para autorización.');
        } catch (Throwable $e) {
            DB::rollBack();
            return back()->withErrors($e->getMessage())->withInput();
        }
    }


    /* ==========================
     * Helpers
     * ========================== */
    /** Normaliza fechas a formato ISO (YYYY-MM-DD). */
    private function toIsoDate(?string $v, string $tz = 'America/Lima'): ?string
    {
        $v = trim((string)$v);
        if ($v === '') return null;

        // 1) d/m/Y (con o sin hora)
        if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$~', $v, $m)) {
            [$all,$d,$mth,$y] = $m;
            if (checkdate((int)$mth, (int)$d, (int)$y)) {
                return sprintf('%04d-%02d-%02d', (int)$y, (int)$mth, (int)$d);
            }
            return null;
        }

        // 2) d-m-Y (con o sin hora)
        if (preg_match('~^(\d{1,2})-(\d{1,2})-(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$~', $v, $m)) {
            [$all,$d,$mth,$y] = $m;
            if (checkdate((int)$mth, (int)$d, (int)$y)) {
                return sprintf('%04d-%02d-%02d', (int)$y, (int)$mth, (int)$d);
            }
            return null;
        }

        // 3) Y-m-d (con o sin hora)
        if (preg_match('~^(\d{4})-(\d{1,2})-(\d{1,2})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$~', $v, $m)) {
            [$all,$y,$mth,$d] = $m;
            if (checkdate((int)$mth, (int)$d, (int)$y)) {
                return sprintf('%04d-%02d-%02d', (int)$y, (int)$mth, (int)$d);
            }
            return null;
        }

        // 4) Fallback: dejar que Carbon intente parsear
        try {
            return Carbon::parse($v, $tz)->toDateString();
        } catch (Throwable $e) {
            return null;
        }
    }

    private function normalizeMoney(?string $v): ?float
    {
        $v = trim((string)$v);
        if ($v === '') return null;

        $negative = false;
        if ($v[0] === '(' && substr($v, -1) === ')') {
            $negative = true;
            $v = substr($v, 1, -1);
        }

        $v = preg_replace('/[^\d\-\.,]/', '', $v) ?? '';

        $v = preg_replace('/\s+/', '', $v);
        $v = preg_replace('/^-+/', '-', $v);

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
        } elseif (!$hasComma && $hasDot) {
            if (preg_match('~^\d{1,3}(\.\d{3})+$~', $v)) {
                $v = str_replace('.', '', $v);
            }
        } else {
            // Solo dígitos: nada que hacer
        }

        if (!is_numeric($v)) return null;
        $n = (float) $v;
        if ($negative) $n = -$n;

        return $n;
    }

}
