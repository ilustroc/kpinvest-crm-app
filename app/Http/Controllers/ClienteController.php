<?php

namespace App\Http\Controllers;

use App\Actions\Cliente\DeleteClientePaymentAction;
use App\Services\Asignacion\ClienteAssignmentService;
use App\Services\Cliente\ClienteProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ClienteController extends Controller
{
    public function __construct(
        private readonly ClienteProfileService $profiles,
        private readonly DeleteClientePaymentAction $deleteClientePayment,
        private readonly ClienteAssignmentService $assignments,
    ) {
    }

    public function show(string $numdoc)
    {
        try {
            return view('clientes.show', $this->profiles->show($numdoc)->toArray());
        } catch (Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            Log::error('ClienteController.show', [
                'numdoc' => $numdoc,
                'dni' => $numdoc,
                'err' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()->withErrors('Error cargando el cliente: '.$e->getMessage());
        }
    }

    public function storeAsignacion(Request $request, string $numdoc)
    {
        $validated = $request->validate([
            'operaciones' => ['required', 'array', 'min:1'],
            'operaciones.*' => ['required', 'string', 'max:50'],
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'operaciones.required' => 'Debes seleccionar al menos una operación para solicitar la asignación.',
            'operaciones.min' => 'Debes seleccionar al menos una operación para solicitar la asignación.',
            'motivo.required' => 'El motivo de la solicitud de asignación es obligatorio.',
            'motivo.min' => 'El motivo de la solicitud debe tener al menos 3 caracteres.',
        ]);

        $created = $this->assignments->requestManualAssignments(
            $request->user(),
            $numdoc,
            $validated['operaciones'],
            (string) $validated['motivo'],
        );

        $count = $created->count();
        $label = $count === 1
            ? "Solicitud de asignación enviada para 1 operación (estado: pendiente)."
            : "Solicitudes de asignación enviadas para {$count} operaciones (estado: pendiente).";

        return redirect()
            ->route('clientes.show', $numdoc)
            ->with('ok', $label)
            ->with('status', $label);
    }

    public function deletePagos(Request $request, string $numdoc)
    {
        $count = $this->deleteClientePayment->execute(
            $request->user(),
            $numdoc,
            $request->input('ids', []),
            $request->input('motivo'),
        );

        if ($count === null) {
            return back()->with('error', 'No seleccionaste pagos.');
        }

        return back()->with('msg', "Pagos eliminados: {$count}");
    }
}
