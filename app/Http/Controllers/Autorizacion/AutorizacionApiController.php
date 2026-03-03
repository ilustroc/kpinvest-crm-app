<?php

namespace App\Http\Controllers\Autorizacion;

use App\Http\Controllers\Controller;
use App\Services\Autorizacion\AutorizacionPagosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutorizacionApiController extends Controller
{
    public function __construct(private AutorizacionPagosService $pagos) {}

    /**
     * API: Pagos por DNI (pagos_propia).
     * GET /autorizacion/pagos/{dni}?limit=500
     */
    public function pagosDni(Request $r, string $dni)
    {
        try {
            $dni = trim($dni);

            // validación simple
            if ($dni === '' || !preg_match('/^\d{6,12}$/', $dni)) {
                return response()->json([
                    'dni'   => $dni,
                    'pagos' => [],
                    'error' => 'DNI inválido',
                ], 422);
            }

            $limit = (int)$r->query('limit', 500);

            $rows = $this->pagos->pagosPorDni($dni, $limit);

            return response()->json([
                'dni'   => $dni,
                'pagos' => $rows,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('pagosDni error', [
                'dni' => $dni,
                'msg' => $e->getMessage(),
                'file'=> $e->getFile(),
                'line'=> $e->getLine(),
            ]);

            return response()->json([
                'dni'   => $dni,
                'pagos' => [],
                'error' => 'No se pudo obtener los pagos',
            ], 500);
        }
    }
}