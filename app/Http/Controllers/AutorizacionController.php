<?php

namespace App\Http\Controllers;

use App\Actions\Promesa\ApprovePromesaAction;
use App\Actions\Promesa\PreapprovePromesaAction;
use App\Actions\Promesa\RejectPromesaAction;
use App\Models\PromesaPago;
use App\Services\Autorizacion\AutorizacionIndexService;
use App\Services\Autorizacion\AutorizacionPaymentLookupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutorizacionController extends Controller
{
    public function __construct(
        private readonly AutorizacionIndexService $indexService,
        private readonly AutorizacionPaymentLookupService $payments,
        private readonly PreapprovePromesaAction $preapprovePromesa,
        private readonly ApprovePromesaAction $approvePromesa,
        private readonly RejectPromesaAction $rejectPromesa,
    ) {
    }

    public function index(Request $request)
    {
        return view('autorizacion.index', $this->indexService->fromRequest($request)->toArray());
    }

    public function preaprobar(Request $request, PromesaPago $promesa)
    {
        try {
            $this->preapprovePromesa->execute($request->user(), $promesa, $request->input('nota_estado'));

            return back()->with('ok', 'Promesa pre-aprobada.');
        } catch (\Throwable $e) {
            if ($e instanceof AuthorizationException) {
                throw $e;
            }

            return back()->withErrors($e->getMessage());
        }
    }

    public function rechazarSup(Request $request, PromesaPago $promesa)
    {
        try {
            $this->rejectPromesa->asSupervisor($request->user(), $promesa, $request->input('nota_estado'));

            return back()->with('ok', 'Promesa rechazada por supervisor.');
        } catch (\Throwable $e) {
            if ($e instanceof AuthorizationException) {
                throw $e;
            }

            return back()->withErrors($e->getMessage());
        }
    }

    public function aprobar(Request $request, PromesaPago $promesa)
    {
        try {
            $this->approvePromesa->execute($request->user(), $promesa, $request->input('nota_estado'));

            return back()->with('ok', 'Promesa APROBADA.');
        } catch (\Throwable $e) {
            if ($e instanceof AuthorizationException) {
                throw $e;
            }

            return back()->withErrors($e->getMessage());
        }
    }

    public function rechazarAdmin(Request $request, PromesaPago $promesa)
    {
        try {
            $this->rejectPromesa->asAdministrator($request->user(), $promesa, $request->input('nota_estado'));

            return back()->with('ok', 'Promesa rechazada por administrador.');
        } catch (\Throwable $e) {
            if ($e instanceof AuthorizationException) {
                throw $e;
            }

            return back()->withErrors($e->getMessage());
        }
    }

    public function pagosDni(string $dni)
    {
        try {
            $dni = trim($dni);

            return response()->json([
                'dni' => $dni,
                'pagos' => $this->payments->pagosByDni($dni),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('pagosDni error', [
                'dni' => $dni,
                'msg' => $e->getMessage(),
            ]);

            return response()->json([
                'dni' => $dni,
                'pagos' => [],
                'error' => 'No se pudo obtener los pagos',
            ], 500);
        }
    }
}
