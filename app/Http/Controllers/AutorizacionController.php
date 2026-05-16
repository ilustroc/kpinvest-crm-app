<?php

namespace App\Http\Controllers;

use App\Actions\Promesa\ApprovePromesaAction;
use App\Actions\Promesa\PreapprovePromesaAction;
use App\Actions\Promesa\RejectPromesaAction;
use App\Models\PagoPropia as Pago;
use App\Models\PromesaPago;
use App\Services\Cna\CnaQueryService;
use App\Services\Promesa\PromesaQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AutorizacionController extends Controller
{
    public function __construct(
        private readonly PromesaQueryService $promesas,
        private readonly CnaQueryService $cnas,
        private readonly PreapprovePromesaAction $preapprovePromesa,
        private readonly ApprovePromesaAction $approvePromesa,
        private readonly RejectPromesaAction $rejectPromesa,
    ) {
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $q = trim((string) ($request->q ?? ''));
        $status = $request->status;

        $rows = $this->promesas->authorizationRows($user, $q, $status);
        $cnaData = $this->cnas->authorizationData($user, $q, $status);

        return view('autorizacion.index', [
            'rows' => $rows,
            'cnaRows' => $cnaData['cnaRows'],
            'prodByOp' => $cnaData['prodByOp'],
            'q' => $q,
            'isSupervisor' => strtolower((string) $user->role) === 'supervisor',
        ]);
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

            $rows = Pago::query()
                ->where('dni', $dni)
                ->orderByDesc('lote_id')
                ->orderByDesc('fecha')
                ->get([
                    'operacion',
                    'fecha',
                    'monto_pagado',
                    'gestor',
                    'entidad',
                    'cosecha',
                    'cuenta_recaudo',
                    'nombre_cliente',
                ])
                ->map(fn ($row) => [
                    'operacion' => (string) ($row->operacion ?? ''),
                    'fecha' => $row->fecha ? (string) $row->fecha : null,
                    'monto_pagado' => (float) ($row->monto_pagado ?? 0),
                    'gestor' => (string) ($row->gestor ?? ''),
                    'entidad' => (string) ($row->entidad ?? ''),
                    'cosecha' => (string) ($row->cosecha ?? ''),
                    'cuenta_recaudo' => (string) ($row->cuenta_recaudo ?? ''),
                    'nombre_cliente' => (string) ($row->nombre_cliente ?? ''),
                ]);

            return response()->json([
                'dni' => $dni,
                'pagos' => $rows,
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
