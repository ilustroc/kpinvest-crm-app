<?php

namespace App\Http\Controllers;

use App\Models\CnaSolicitud;
use App\Support\WorkflowMailer;
use Carbon\Carbon;
use Ilovepdf\Ilovepdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class CnaController extends Controller
{
    // =========================================================
    // CREAR SOLICITUD
    // =========================================================
    public function store(Request $request, string $dni)
    {
        try {
            $data = $request->validate([
                'titular'               => ['nullable','string','max:150'],
                'nota'                  => ['nullable','string','max:1000'],
                'observacion'           => ['nullable','string','max:1000'],
                'fecha_pago_realizado'  => ['required','date'],
                'monto_pagado'          => ['required','numeric','min:0.01','max:999999999.99'],
                'operaciones'           => ['required','array','min:1'],
                'operaciones.*'         => ['string','max:50'],
                'cuenta'                => ['nullable','string','max:50'], // se ignora; solo por compat.
            ], [], [
                'fecha_pago_realizado'  => 'fecha de pago realizado',
                'monto_pagado'          => 'monto pagado',
                'operaciones'           => 'operaciones',
            ]);

            $ops = array_values(array_filter(array_map('strval', $data['operaciones'] ?? [])));
            if (!$ops) {
                return back()->withErrors('Selecciona al menos una operación para la CNA.');
            }

            // Titular (en tu tabla el nombre está en 'nombre')
            $titular = $data['titular'] ?? DB::table('clientes_cuentas')
                ->where('numdoc', $dni)
                ->value('nombre');

            // Trae filas del propio DNI para esas operaciones
            $rowsOps = DB::table('clientes_cuentas')
                ->select('operacion','cosecha','entidad','producto')
                ->where('numdoc', $dni)
                ->whereIn('operacion', $ops)
                ->get();

            // Asegura que las ops sí pertenecen al DNI
            $opsEncontradas = $rowsOps->pluck('operacion')->map('strval')->values();
            $missing = collect($ops)->diff($opsEncontradas);
            if ($missing->isNotEmpty()) {
                return back()->withErrors(
                    'Las operaciones ('.implode(', ', $missing->all()).') no pertenecen al DNI '.$dni.'.'
                );
            }

            // Producto de referencia (solo informativo)
            $productoAuto = $rowsOps->pluck('producto')->filter()->unique()->implode(' / ') ?: null;

            // Referencia de cosecha: la más frecuente (no se exige que todas sean iguales)
            $cosechaRef = $rowsOps->pluck('cosecha')->filter()->countBy()->sortDesc()->keys()->first();
            $cosechaRef = $cosechaRef ? (string)$cosechaRef : null;

            // Determinar origen para la serie
            $origen = $cosechaRef ? $this->originFromCosecha($cosechaRef) : null;
            if (!$origen) {
                $entRef = strtoupper((string)($rowsOps->pluck('entidad')->filter()->first() ?? ''));
                if (str_contains($entRef, 'COMPARTAMOS'))      $origen = 'COMPARTAMOS_1';
                elseif (str_contains($entRef, 'CONFIANZA'))    $origen = 'CONFIANZA_1';
                elseif (str_contains($entRef, 'AREQUIPA'))     $origen = 'AQP1';
                elseif (str_contains($entRef, 'BBVA'))         $origen = 'BBVA_1_2';
            }
            if (!$origen) {
                return back()->withErrors('No se pudo determinar el origen para numeración (cosecha/entidad).');
            }

            ['serie' => $serie, 'suffix' => $suffix] = $this->seriesConfig($origen);

            // Detectar si quien crea es administrador.
            $role = strtolower((string)(Auth::user()->role ?? ''));
            $isAdminAuto = $role === 'administrador';
            $now = now();

            // Crear con correlativo (lock para evitar colisiones)
            $solicitud = DB::transaction(function () use ($dni, $data, $ops, $titular, $productoAuto, $serie, $suffix, $isAdminAuto, $now) {
                DB::table('cna_solicitudes')->lockForUpdate()->get();
                $next = $this->nextCartaForSerie($serie, $suffix);

                $payload = [
                    'correlativo'          => $next['corr'],
                    'nro_carta'            => $next['nro'],
                    'dni'                  => $dni,
                    'titular'              => $titular,
                    'producto'             => $productoAuto,
                    'operaciones'          => $ops,
                    'nota'                 => $data['nota'] ?? null,
                    'observacion'          => $data['observacion'] ?? null,
                    'fecha_pago_realizado' => $data['fecha_pago_realizado'],
                    'monto_pagado'         => $data['monto_pagado'],
                    'user_id'              => Auth::id(),
                ];

                if ($isAdminAuto) {
                    // ✅ se aprueba directo
                    $payload['workflow_estado']  = 'aprobada';
                    $payload['pre_aprobado_por'] = Auth::id();
                    $payload['pre_aprobado_at']  = $now;
                    $payload['aprobado_por']     = Auth::id();
                    $payload['aprobado_at']      = $now;
                } else {
                    $payload['workflow_estado']  = 'pendiente';
                }

                return CnaSolicitud::create($payload);
            });

            // ✅ Si es admin: generar DOCX+PDF y NO mandar correos
            if ($isAdminAuto) {
                $this->generateOutputsFromTemplate($solicitud);

                return redirect()->route('clientes.show', $dni)
                    ->with('ok', "CNA APROBADA automáticamente. N.º {$solicitud->nro_carta}");
            }

            // ✅ Caso normal: enviar correo de pendiente
            WorkflowMailer::cnaPendiente($solicitud);

            return redirect()->route('clientes.show', $dni)
                ->with('ok', "Solicitud de CNA enviada. N.º {$solicitud->nro_carta}");

        } catch (\Throwable $e) {
            Log::error('CNA store error', [
                'dni'  => $dni,
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return redirect()->route('clientes.show', $dni)
                ->withErrors('No se pudo guardar la CNA: '.$e->getMessage())
                ->withInput();
        }
    }


    // =========================================================
    // FLUJO DE APROBACIÓN
    // =========================================================
    public function preaprobar(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('supervisor');

        if ($cna->workflow_estado !== 'pendiente') {
            return back()->withErrors('Solo se puede pre-aprobar una solicitud pendiente.');
        }

        $this->appendNota($cna, $request);
        $cna->update([
            'workflow_estado' => 'preaprobada',
            'pre_aprobado_por'=> auth()->id(),
            'pre_aprobado_at' => now(),
            'rechazado_por'   => null,
            'rechazado_at'    => null,
            'motivo_rechazo'  => null,
        ]);

        WorkflowMailer::cnaPreaprobada($cna);
        return back()->with('ok', 'CNA pre-aprobada.');
    }

    public function rechazarSup(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('supervisor');

        if ($cna->workflow_estado !== 'pendiente') {
            return back()->withErrors('Solo se puede rechazar una solicitud pendiente.');
        }

        $nota = substr((string)$request->input('nota_estado',''), 0, 500);

        $cna->update([
            'workflow_estado' => 'rechazada_sup',
            'rechazado_por'   => Auth::id(),
            'rechazado_at'    => now(),
            'motivo_rechazo'  => $nota,
        ]);

        WorkflowMailer::cnaRechazadaSup($cna, $nota);
        return back()->with('ok', 'CNA rechazada por supervisor.');
    }

    public function aprobar(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('administrador');

        if ($cna->workflow_estado !== 'preaprobada') {
            return back()->withErrors('Solo se puede aprobar una CNA pre-aprobada.');
        }

        $this->appendNota($cna, $request);
        $cna->update([
            'workflow_estado' => 'aprobada',
            'aprobado_por'    => auth()->id(),
            'aprobado_at'     => now(),
        ]);

        // Genera DOCX + PDF
        $this->generateOutputsFromTemplate($cna);

        WorkflowMailer::cnaResuelta($cna, true, $request->input('nota_estado'));
        return back()->with('ok', 'CNA aprobada y archivos generados.');
    }

    public function rechazarAdmin(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('administrador');

        if ($cna->workflow_estado !== 'preaprobada') {
            return back()->withErrors('Solo se puede rechazar una solicitud pre-aprobada.');
        }

        $nota = substr((string)$request->input('nota_estado',''), 0, 500);

        $cna->update([
            'workflow_estado' => 'rechazada',
            'rechazado_por'   => Auth::id(),
            'rechazado_at'    => now(),
            'motivo_rechazo'  => $nota,
        ]);

        WorkflowMailer::cnaResuelta($cna, false, $nota);
        return back()->with('ok', 'CNA rechazada por administrador.');
    }

    // =========================================================
    // DESCARGAS
    // =========================================================
    public function pdf(int $id)
    {
        $cna = CnaSolicitud::findOrFail($id);
        if ($cna->workflow_estado !== 'aprobada') abort(403, 'Solo disponible para CNA aprobadas.');
        return $this->downloadPreferred($cna, 'pdf');
    }

    public function docx(int $id)
    {
        $cna = CnaSolicitud::findOrFail($id);
        if ($cna->workflow_estado !== 'aprobada') abort(403, 'Solo disponible para CNA aprobadas.');
        return $this->downloadPreferred($cna, 'docx');
    }

    private function downloadPreferred(CnaSolicitud $cna, string $primary)
    {
        $base = sprintf('CNA %s - %s', $cna->nro_carta, $cna->dni);
        $paths = [
            'pdf'  => ['rel' => "cna/pdfs/{$base}.pdf",  'name' => "{$base}.pdf"],
            'docx' => ['rel' => "cna/docx/{$base}.docx", 'name' => "{$base}.docx"],
        ];

        $first  = $paths[$primary];
        $second = $paths[$primary === 'pdf' ? 'docx' : 'pdf'];

        if (Storage::exists($first['rel']))  return Storage::download($first['rel'],  $first['name']);
        if (Storage::exists($second['rel'])) return Storage::download($second['rel'], $second['name']);

        abort(404, 'Archivo no encontrado.');
    }

    // =========================================================
    // HELPERS (SEGURIDAD / PLANTILLAS / CORRELATIVO)
    // =========================================================
    private function authorizeRole(string $role): void
    {
        $user = Auth::user();
        if (!$user || strtolower($user->role) !== $role) {
            abort(403, 'No autorizado.');
        }
    }

    /**
     * Genera DOCX (plantilla) + PDF (iLovePDF) y guarda rutas.
     * Placeholders: ${nro_carta}, ${nombre}, ${numdoc}, ${aprobado_at}, ${cuenta}, ${operacion}, ${entidad}
     */
    private function generateOutputsFromTemplate(CnaSolicitud $cna): void
    {
        // ---- Operaciones normalizadas
        $ops = is_array($cna->operaciones)
            ? $cna->operaciones
            : (json_decode($cna->operaciones ?? '[]', true) ?: []);
        $ops = array_values(array_filter(array_map('strval', $ops)));
    
        // ---- Trae datos de las operaciones (incluye cuenta)
        $rows = DB::table('clientes_cuentas')
            ->select('operacion','cuenta','cosecha','entidad')
            ->whereIn('operacion', $ops)
            ->get();
    
        $cosecha = (string) ($rows->pluck('cosecha')->filter()->unique()->first() ?? '');
        $origen  = $this->originFromCosecha($cosecha) ?? 'KP INVEST SAC';
        $cfg     = $this->seriesConfig($origen);
        $tpl     = $cfg['template'];
    
        if (!is_file($tpl)) {
            throw new \RuntimeException('Plantilla no encontrada para origen: '.$origen.' ('.$tpl.')');
        }
    
        // ---- Directorios destino
        $docxDir = 'cna/docx'; $pdfDir = 'cna/pdfs';
        Storage::makeDirectory($docxDir);
        Storage::makeDirectory($pdfDir);
    
        $docxRel  = $docxDir."/CNA {$cna->nro_carta} - {$cna->dni}.docx";
        $pdfRel   =  $pdfDir."/CNA {$cna->nro_carta} - {$cna->dni}.pdf";
    
        // ---- Plantilla
        $tp = new TemplateProcessor($tpl);
    
        // Cabecera
        $titular = $cna->titular ?: DB::table('clientes_cuentas')
            ->where('numdoc', $cna->dni)
            ->value('nombre');
    
        Carbon::setLocale('es');
        $aprobadoAtStr = ($cna->aprobado_at ? Carbon::parse($cna->aprobado_at) : now())
            ->translatedFormat('d \\de F \\de Y');
    
        $cuenta  = (string) ($rows->pluck('cuenta')->filter()->unique()->first() ?? ($ops[0] ?? ''));
        $entidad = (string) ($rows->pluck('entidad')->filter()->unique()->first() ?? '');
    
        // Placeholders fijos
        $tp->setValue('nro_carta',   $cna->nro_carta);
        $tp->setValue('nombre',      (string) $titular);
        $tp->setValue('numdoc',      $cna->dni);
        $tp->setValue('aprobado_at', $aprobadoAtStr);
    
        // ---- Tabla: UNA FILA POR OPERACIÓN
        $n = max(count($ops), 1);
    
        // Si la plantilla tiene ${operacion} dentro de la fila de tabla, clonamos por esa clave
        if ($n > 1) {
            // clona por el nombre EXACTO del marcador en la fila (operacion)
            $tp->cloneRow('operacion', $n);
        }
    
        // Relleno numerado (cuenta/entidad repetidos en cada fila)
        for ($i = 0; $i < $n; $i++) {
            $idx = $i + 1;
            $op  = $ops[$i] ?? ($ops[0] ?? '');
    
            // Soporta tanto numerado (#1) como base simple (por si la plantilla no está clonada)
            $tp->setValue("operacion#{$idx}", $op);
            $tp->setValue("cuenta#{$idx}",    $cuenta);
            $tp->setValue("entidad#{$idx}",   $entidad);
        }
        // Fallback (si la plantilla no usó #1, deja valores “simples” también)
        $tp->setValue('operacion', implode(', ', $ops));
        $tp->setValue('cuenta',    $cuenta);
        $tp->setValue('entidad',   $entidad);
    
        // ---- Guarda DOCX
        $tp->saveAs(storage_path('app/'.$docxRel));
    
        // ---- Asegura PDF (iLovePDF → LibreOffice como respaldo)
        $this->ensurePdfFromDocx(storage_path('app/'.$docxRel), storage_path('app/'.$pdfRel));
    
        // Persistir rutas si el PDF existe
        $cna->docx_path = $docxRel;
        $cna->pdf_path  = (is_file(storage_path('app/'.$pdfRel))) ? $pdfRel : null;
        $cna->save();
    }
    private function ensurePdfFromDocx(string $docxAbs, string $pdfAbs): void
    {
        @unlink($pdfAbs);
    
        try {
            $this->convertDocxToPdfViaIlovepdf($docxAbs, $pdfAbs);
        } catch (\Throwable $e) {
            Log::warning('iLovePDF falló, entregando DOCX', [
                'msg' => $e->getMessage(),
                'class' => get_class($e),
                'code' => $e->getCode(),
            ]);
        }
    }
    /** DOCX→PDF con iLovePDF (officepdf). Requiere keys en config/services.php */
    private function convertDocxToPdfViaIlovepdf(string $docxAbs, string $pdfAbs): void
    {
        $public = config('services.ilovepdf.public');
        $secret = config('services.ilovepdf.secret');
        if (!$public || !$secret) {
            throw new \RuntimeException('Faltan claves de iLovePDF.');
        }
    
        $sdk  = new Ilovepdf($public, $secret);
        $task = $sdk->newTask('officepdf');
        $task->addFile($docxAbs);
        $task->execute();
    
        $outDir = dirname($pdfAbs);
        if (!is_dir($outDir)) @mkdir($outDir, 0775, true);
    
        $task->download($outDir);
    
        // Renombra al nombre esperado
        $expected = $outDir.'/'.basename($docxAbs, '.docx').'.pdf';
        if (is_file($expected) && $expected !== $pdfAbs) {
            @unlink($pdfAbs);
            @rename($expected, $pdfAbs);
        }
    
        if (!is_file($pdfAbs)) {
            throw new \RuntimeException('No se pudo localizar el PDF descargado por iLovePDF.');
        }
    }

    /** Cosecha → Origen normalizado */
    private function originFromCosecha(?string $cosecha): ?string
    {
        if (!$cosecha) return null;
        $c = strtoupper(trim($cosecha));

        $faa  = ['BBVA3','BBVA4','BBVA5','BBVA6','CAJAAQP3'];
        $faa2 = ['BBVA7','BBVA8','CONFIANZA_5'];
        $kpi  = [
            'BBVA1','BBVA2','CAJAAQP1','CAJAAQP2','CAJAAQP4','CAJAAQP5','COMPARTAMOS_1','COMPARTAMOS_2','CONFIANZA','CONFIANZA_2','CONFIANZA_3',
            'CONFIANZA_4','CONFIANZA_6','CONFIANZA_7','CONFIANZA_8','CONFIANZA_9','CONFIANZA_10',
            'CONFIANZA_11','CONFIANZA_12','SEMBRANDO','WANDOO_1'
        ];

        if (in_array($c, $faa, true))  return 'FONDO ACREENCIA AREQUIPA';
        if (in_array($c, $faa2, true)) return 'ACREENCIA II';
        if (in_array($c, $kpi, true))  return 'KP INVEST SAC';
        return null;
    }

    /** Origen → serie/sufijo/plantilla */
    private function seriesConfig(string $origen): array
    {
        $o = strtoupper($origen);
        if (str_contains($o, 'ACREENCIA II')) {
            return ['serie'=>'F2','suffix'=>'F2','template'=>storage_path('app/templates/cna_fondo_acreencia_arequipa_2.docx')];
        }
        if (str_contains($o, 'FONDO ACREENCIA AREQUIPA') || str_contains($o,'FONDO ACREENCIAS AREQUIPA')) {
            return ['serie'=>'F', 'suffix'=>'F', 'template'=>storage_path('app/templates/cna_fondo_acreencia_arequipa.docx')];
        }
        return ['serie'=>'KPI','suffix'=>'', 'template'=>storage_path('app/templates/cna_kpinvest.docx')];
    }

    /** Siguiente número por serie (lock). Retorna ['corr'=>int,'nro'=>'000123F'] */
    private function nextCartaForSerie(string $serie, string $suffix): array
    {
        DB::table('cna_solicitudes')->lockForUpdate()->get();

        $query = DB::table('cna_solicitudes')->selectRaw("MAX(CAST(LEFT(nro_carta,6) AS UNSIGNED)) as m");
        if ($suffix !== '') $query->where('nro_carta','like',"%{$suffix}");
        $max  = $query->value('m');

        $corr = (int)($max ?: 0) + 1;
        $nro  = str_pad((string)$corr, 6, '0', STR_PAD_LEFT) . $suffix;
        return ['corr'=>$corr,'nro'=>$nro];
    }

    private function appendNota(CnaSolicitud $cna, Request $r): void
    {
    $txt = trim((string)$r->input('nota_estado', ''));
    if ($txt !== '') {
        $prefix = '[' . now()->format('Y-m-d H:i') . '] ' . (auth()->user()->name ?? 'sistema') . ': ';
        $cna->nota = trim(($cna->nota ? $cna->nota . "\n" : '') . $prefix . $txt);
    }
}
}
