<?php

namespace App\Http\Controllers;

use App\Models\PromesaPago;
use App\Services\Promesas\PromesaAgreementDocumentService;
use Illuminate\Support\Facades\Log;
use Throwable;

class PromesaPdfController extends Controller
{
    public function __construct(private PromesaAgreementDocumentService $svc) {}

    public function acuerdo(PromesaPago $promesa)
    {
        try {
            $res = $this->svc->build($promesa);

            if (!empty($res['pdfOk']) && !empty($res['pdfAbs']) && is_file($res['pdfAbs'])) {
                return response()->file($res['pdfAbs'], [
                    'Content-Type'  => 'application/pdf',
                    'Cache-Control' => 'private, max-age=0, no-store, no-cache, must-revalidate',
                ]);
            }

            // fallback DOCX
            if (!empty($res['docxAbs']) && is_file($res['docxAbs'])) {
                return response()->download($res['docxAbs'], "Conv_{$res['dni']}.docx");
            }

            abort(500, 'No se pudo generar el archivo.');

        } catch (Throwable $e) {
            Log::error('Error generando Conv PDF', [
                'promesa_id' => $promesa->id ?? null,
                'dni'        => $promesa->dni ?? null,
                'msg'        => $e->getMessage(),
                'exception'  => get_class($e),
            ]);

            abort(500, 'No se pudo generar el PDF del acuerdo.');
        }
    }
}