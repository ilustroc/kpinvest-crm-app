<?php

namespace App\Http\Controllers;

use App\Actions\Cliente\DeleteClientePaymentAction;
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
    ) {
    }

    public function show(string $dni)
    {
        try {
            return view('clientes.show', $this->profiles->show($dni)->toArray());
        } catch (Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            Log::error('ClienteController.show', [
                'dni' => $dni,
                'err' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()->withErrors('Error cargando el cliente: '.$e->getMessage());
        }
    }

    public function deletePagos(Request $request, string $dni)
    {
        $count = $this->deleteClientePayment->execute(
            $request->user(),
            $dni,
            $request->input('ids', []),
        );

        if ($count === null) {
            return back()->with('error', 'No seleccionaste pagos.');
        }

        return back()->with('msg', "Pagos eliminados: {$count}");
    }
}
