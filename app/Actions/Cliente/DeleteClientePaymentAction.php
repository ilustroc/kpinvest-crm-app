<?php

namespace App\Actions\Cliente;

use App\Models\User;
use App\Services\Cliente\ClientePaymentService;
use Illuminate\Support\Facades\Gate;

class DeleteClientePaymentAction
{
    public function __construct(private readonly ClientePaymentService $payments)
    {
    }

    public function execute(?User $user, string $numdoc, mixed $ids, ?string $motivo = null): ?int
    {
        if (! $user || ! Gate::forUser($user)->allows('delete-client-payments')) {
            abort(403, 'No autorizado para eliminar pagos');
        }

        $ids = $this->payments->normalizePaymentIds($ids);

        if ($ids->isEmpty()) {
            return null;
        }

        return $this->payments->deleteForNumdoc($user, $numdoc, $ids, $motivo);
    }
}
