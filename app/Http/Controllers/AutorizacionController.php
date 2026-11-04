<?php

namespace App\Http\Controllers;

use App\Actions\Promesa\ApprovePromesaAction;
use App\Actions\Promesa\PreapprovePromesaAction;
use App\Actions\Promesa\RejectPromesaAction;
use App\Models\AsignarCliente;
use App\Models\PromesaPago;
use App\Services\Asignacion\ClienteAssignmentService;
use App\Services\Autorizacion\AutorizacionIndexService;
use App\Services\Autorizacion\AutorizacionPaymentLookupService;
use App\Support\WorkflowNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AutorizacionController extends Controller
{
    public function __construct(
        private readonly AutorizacionIndexService $indexService,
        private readonly AutorizacionPaymentLookupService $payments,
        private readonly PreapprovePromesaAction $preapprovePromesa,
        private readonly ApprovePromesaAction $approvePromesa,
        private readonly RejectPromesaAction $rejectPromesa,
        private readonly ClienteAssignmentService $assignments,
    ) {
    }

    public function index(Request $request)
    {
        return view('autorizacion.index', $this->indexService->fromRequest($request)->toArray());
    }

    public function aprobarAsignacion(Request $request, AsignarCliente $asignacion)
    {
        Gate::forUser($request->user())->authorize('approve-asignacion');

        $nota = $request->input('observacion_resolucion')
            ?? $request->input('nota_estado')
            ?? $request->input('nota');

        try {
            $resolved = $this->assignments->approve(
                $request->user(),
                $asignacion,
                is_string($nota) ? $nota : null,
            );
            WorkflowNotifier::asignacionResuelta($resolved, true);

            return back()
                ->with('ok', 'Solicitud de asignación aprobada correctamente.')
                ->with('status', 'Solicitud de asignación aprobada correctamente.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($e instanceof AuthorizationException) {
                throw $e;
            }

            return back()->withErrors($e->getMessage());
        }
    }

    public function rechazarAsignacion(Request $request, AsignarCliente $asignacion)
    {
        Gate::forUser($request->user())->authorize('reject-asignacion');

        $motivo = trim((string) (
            $request->input('motivo_rechazo')
            ?? $request->input('observacion_resolucion')
            ?? $request->input('nota_estado')
            ?? $request->input('motivo')
            ?? ''
        ));

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo_rechazo' => ['El motivo del rechazo es obligatorio.'],
            ]);
        }

        try {
            $resolved = $this->assignments->reject($request->user(), $asignacion, $motivo);
            WorkflowNotifier::asignacionResuelta($resolved, false);

            return back()
                ->with('ok', 'Solicitud de asignación rechazada.')
                ->with('status', 'Solicitud de asignación rechazada.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($e instanceof AuthorizationException) {
                throw $e;
            }

            return back()->withErrors($e->getMessage());
        }
    }

    public function historialAsignacion(Request $request, AsignarCliente $asignacion)
    {
        Gate::forUser($request->user())->authorize('review-asignaciones');

        $history = AsignarCliente::query()
            ->with(['solicitante:id,name', 'asesorDestino:id,name', 'aprobadoPor:id,name'])
            ->where('numdoc', $asignacion->numdoc)
            ->where('operacion', $asignacion->operacion)
            ->orderByRaw("COALESCE(fecha_solicitud, '1970-01-01 00:00:00') DESC")
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (AsignarCliente $h) => [
                'id' => $h->id,
                'fecha_solicitud' => $h->fecha_solicitud ? $h->fecha_solicitud->format('d/m/Y H:i') : 'Histórico',
                'solicitante' => $h->solicitante_nombre ?? 'Sistema / Histórico',
                'asesor' => $h->asesor_nombre ?? 'Sin asignar',
                'motivo' => $h->motivo ?: 'Asignación histórica',
                'estado' => $h->estado,
                'origen' => $h->origen,
                'aprobado_por' => $h->aprobador_nombre,
                'fecha_aprobacion' => $h->fecha_aprobacion ? $h->fecha_aprobacion->format('d/m/Y H:i') : null,
                'observacion_resolucion' => $h->observacion_resolucion,
            ]);

        return response()->json([
            'numdoc' => $asignacion->numdoc,
            'operacion' => $asignacion->operacion,
            'historial' => $history,
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

            return response()->json([
                'numdoc' => $dni,
                'dni' => $dni,
                'pagos' => $this->payments->pagosByDni($dni),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('pagosDni error', [
                'numdoc' => $dni,
                'dni' => $dni,
                'msg' => $e->getMessage(),
            ]);

            return response()->json([
                'numdoc' => $dni,
                'dni' => $dni,
                'pagos' => [],
                'error' => 'No se pudo obtener los pagos',
            ], 500);
        }
    }
}
