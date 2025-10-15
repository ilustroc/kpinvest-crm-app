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
    // ======= CREAR SOLICITUD =======
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
                'cuenta'                => ['nullable','string','max:50'],
            ], [], [
                'fecha_pago_realizado'  => 'fecha de pago realizado',
                'monto_pagado'          => 'monto pagado',
                'operaciones'           => 'operaciones',
            ]);

            $ops = array_values(array_filter(array_map('strval', $data['operaciones'] ?? [])));
            if (!$ops) {
                return redirect()->route('clientes.show', $dni)
                    ->withErrors('Selecciona al menos una operación para la CNA.');
            }

            // Titular desde clientes_cuentas: usa numdoc (fallback a dni), y toma la columna NOMBRE
            $titular = $data['titular'] ?? DB::table('clientes_cuentas')
                ->where(function ($q) use ($dni) {
                    $q->where('numdoc', $dni)->orWhere('dni', $dni);
                })
                ->value('nombre');

            // Producto de referencia (opcional)
            $productoAuto = DB::table('clientes_cuentas')
                ->whereIn('operacion', $ops)
                ->whereNotNull('producto')
                ->pluck('producto')->filter()->unique()->implode(' / ') ?: null;

            // Cosecha única + entidad (desde las operaciones)
            $rowsOps = DB::table('clientes_cuentas')
                ->select('operacion','cosecha','entidad')
                ->whereIn('operacion', $ops)
                ->get();

            $cosechas = $rowsOps->pluck('cosecha')->filter()->unique()->values();
            if ($cosechas->count() !== 1) {
                return redirect()->route('clientes.show', $dni)
                    ->withErrors('Todas las operaciones deben ser de la MISMA cosecha.');
            }
            $cosecha = (string) $cosechas->first();

            $origen = $this->originFromCosecha($cosecha);
            if (!$origen) {
                return redirect()->route('clientes.show', $dni)
                    ->withErrors('Cosecha no reconocida: "'.$cosecha.'".');
            }

            ['serie' => $serie, 'suffix' => $suffix] = $this->seriesConfig($origen);

            // Crear con correlativo (lock)
            $solicitud = DB::transaction(function () use ($dni, $data, $ops, $titular, $productoAuto, $serie, $suffix) {
                DB::table('cna_solicitudes')->lockForUpdate()->get();
                $next = $this->nextCartaForSerie($serie, $suffix);

                return CnaSolicitud::create([
                    'correlativo'          => $next['corr'],
                    'nro_carta'            => $next['nro'],
                    'dni'                  => $dni,
                    'titular'              => $titular,
                    'producto'             => $productoAuto,
                    'operaciones'          => $ops,               // cast array -> json
                    'nota'                 => $data['nota'] ?? null,
                    'observacion'          => $data['observacion'] ?? null,
                    'fecha_pago_realizado' => $data['fecha_pago_realizado'],
                    'monto_pagado'         => $data['monto_pagado'],
                    'workflow_estado'      => 'pendiente',
                    'user_id'              => Auth::id(),
                ]);
            });

            WorkflowMailer::cnaPendiente($solicitud);

            // Lleva SIEMPRE de vuelta a la ficha del cliente
            return redirect()
                ->route('clientes.show', $dni)
                ->with('ok', "Solicitud de CNA enviada. N.º {$solicitud->nro_carta}");

        } catch (\Throwable $e) {
            Log::error('CNA store error', [
                'dni'  => $dni,
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return redirect()
                ->route('clientes.show', $dni)
                ->withErrors('No se pudo guardar la CNA: '.$e->getMessage());
        }
    }

    // ======= FLUJO (SUPERVISOR) =======
    public function preaprobar(CnaSolicitud $cna)
    {
        $this->authorizeRole('supervisor');
        if ($cna->workflow_estado !== 'pendiente')
            return back()->withErrors('Solo se puede pre-aprobar una solicitud pendiente.');

        $cna->update([
            'workflow_estado' => 'preaprobada',
            'pre_aprobado_por'=> Auth::id(),
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
        if ($cna->workflow_estado !== 'pendiente')
            return back()->withErrors('Solo se puede rechazar una solicitud pendiente.');

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

    // ======= FLUJO (ADMIN) =======
    public function aprobar(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('administrador');
        if ($cna->workflow_estado !== 'preaprobada')
            return back()->withErrors('Solo se puede aprobar una CNA pre-aprobada.');

        $cna->update(['workflow_estado' => 'aprobada', 'aprobado_por' => Auth::id(), 'aprobado_at' => now()]);
        $this->generateOutputsFromTemplate($cna);

        WorkflowMailer::cnaResuelta($cna, true, $request->input('nota_estado'));
        return back()->with('ok', 'CNA aprobada y archivos generados.');
    }

    public function rechazarAdmin(Request $request, CnaSolicitud $cna)
    {
        $this->authorizeRole('administrador');
        if ($cna->workflow_estado !== 'preaprobada')
            return back()->withErrors('Solo se puede rechazar una solicitud pre-aprobada.');

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

    // ======= DESCARGAS =======
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

    // ======= HELPERS =======
    private function authorizeRole(string $role): void
    {
        $user = Auth::user();
        if (!$user || !in_array(strtolower($user->role), [$role, 'sistemas'])) abort(403, 'No autorizado.');
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

    private function generateOutputsFromTemplate(CnaSolicitud $cna): void
    {
        $ops = is_array($cna->operaciones) ? $cna->operaciones : (json_decode($cna->operaciones ?? '[]', true) ?: []);
        $ops = array_values(array_filter(array_map('strval', $ops)));

        $rows = DB::table('clientes_cuentas')
            ->select('operacion','cosecha','entidad')
            ->whereIn('operacion', $ops)
            ->get();

        $cosecha = (string) ($rows->pluck('cosecha')->filter()->unique()->first() ?? '');
        $origen  = $this->originFromCosecha($cosecha) ?? 'KP INVEST SAC';
        $cfg     = $this->seriesConfig($origen);
        $tpl     = $cfg['template'];

        if (!is_file($tpl)) {
            throw new \RuntimeException('Plantilla no encontrada para origen: '.$origen);
        }

        $docxDir = 'cna/docx'; $pdfDir = 'cna/pdfs';
        Storage::makeDirectory($docxDir);
        Storage::makeDirectory($pdfDir);

        $docxName = "CNA {$cna->nro_carta} - {$cna->dni}.docx";
        $pdfName  = "CNA {$cna->nro_carta} - {$cna->dni}.pdf";
        $docxRel  = $docxDir.'/'.$docxName;
        $pdfRel   = $pdfDir.'/'.$pdfName;

        $tp = new TemplateProcessor($tpl);

        // Titular por numdoc (fallback dni) — columna NOMBRE
        $titular = $cna->titular ?: DB::table('clientes_cuentas')
            ->where(function ($q) use ($cna) {
                $q->where('numdoc', $cna->dni)->orWhere('dni', $cna->dni);
            })
            ->value('nombre');

        Carbon::setLocale('es');
        $aprobadoAtStr = ($cna->aprobado_at ? Carbon::parse($cna->aprobado_at) : now())->translatedFormat('d \\de F \\de Y');
        $cuenta  = $ops[0] ?? '';
        $entidad = (string) ($rows->pluck('entidad')->filter()->unique()->first() ?? '');

        $tp->setValue('nro_carta',   $cna->nro_carta);
        $tp->setValue('nombre',      (string) $titular);
        $tp->setValue('numdoc',      $cna->dni);
        $tp->setValue('aprobado_at', $aprobadoAtStr);
        $tp->setValue('cuenta',      $cuenta);
        $tp->setValue('operacion',   implode(', ', $ops));
        $tp->setValue('entidad',     $entidad);
        $tp->saveAs(storage_path('app/'.$docxRel));

        try {
            $this->convertDocxToPdfViaIlovepdf(storage_path('app/'.$docxRel), storage_path('app/'.$pdfRel));
            $cna->pdf_path = $pdfRel;
        } catch (\Throwable $e) {
            Log::error('Error iLovePDF DOCX→PDF: '.$e->getMessage(), ['cna_id' => $cna->id]);
            $cna->pdf_path = null;
        }

        $cna->docx_path = $docxRel;
        $cna->save();
    }

    private function convertDocxToPdfViaIlovepdf(string $docxAbs, string $pdfAbs): void
    {
        $public = config('services.ilovepdf.public');
        $secret = config('services.ilovepdf.secret');
        if (!$public || !$secret) throw new \RuntimeException('Faltan claves de iLovePDF.');

        $sdk  = new Ilovepdf($public, $secret);
        $task = $sdk->newTask('officepdf');
        $task->addFile($docxAbs);
        $task->execute();

        $outDir = dirname($pdfAbs);
        if (!is_dir($outDir)) @mkdir($outDir, 0775, true);
        $task->download($outDir);

        $expected = $outDir.'/'.basename($docxAbs, '.docx').'.pdf';
        if (!is_file($expected)) {
            $latest = collect(glob($outDir.'/*.pdf'))->sortByDesc(fn($p) => filemtime($p))->first();
            if ($latest) $expected = $latest;
        }
        if (!is_file($expected)) throw new \RuntimeException('No se pudo localizar el PDF generado.');

        if ($expected !== $pdfAbs) { @unlink($pdfAbs); rename($expected, $pdfAbs); }
    }

    private function originFromCosecha(?string $cosecha): ?string
    {
        if (!$cosecha) return null;
        $c = strtoupper(trim($cosecha));

        $faa  = ['BBVA3','BBVA4','BBVA5','BBVA6','CAJAAQP3'];
        $faa2 = ['BBVA7','BBVA8','CONFIANZA_5'];
        $kpi  = [
            'BBVA1','BBVA2','CAJAAQP1','CAJAAQP2','COMPARTAMOS_1','CONFIANZA','CONFIANZA_2','CONFIANZA_3',
            'CONFIANZA_4','CONFIANZA_6','CONFIANZA_7','CONFIANZA_8','CONFIANZA_9','CONFIANZA_10',
            'CONFIANZA_11','CONFIANZA_12','SEMBRANDO',
        ];

        if (in_array($c, $faa, true))  return 'FONDO ACREENCIA AREQUIPA';
        if (in_array($c, $faa2, true)) return 'ACREENCIA II';
        if (in_array($c, $kpi, true))  return 'KP INVEST SAC';
        return null;
    }

    private function seriesConfig(string $origen): array
    {
        $o = strtoupper($origen);
        if (str_contains($o, 'ACREENCIA II')) {
            return ['serie'=>'F2','suffix'=>'F2','template'=>storage_path('app/templates/cna_fondo_acreencia_arequipa2.docx')];
        }
        if (str_contains($o, 'FONDO ACREENCIA AREQUIPA') || str_contains($o,'FONDO ACREENCIAS AREQUIPA')) {
            return ['serie'=>'F', 'suffix'=>'F', 'template'=>storage_path('app/templates/cna_fondo_acreencia_arequipa.docx')];
        }
        return ['serie'=>'KPI','suffix'=>'', 'template'=>storage_path('app/templates/cna_kpinvest.docx')];
    }

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
}