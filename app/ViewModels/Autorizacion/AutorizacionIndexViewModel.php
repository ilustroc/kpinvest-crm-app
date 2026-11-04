<?php

namespace App\ViewModels\Autorizacion;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AutorizacionIndexViewModel
{
    public function __construct(
        public readonly Collection $rows,
        public readonly LengthAwarePaginator $cnaRows,
        public readonly array $prodByOp,
        public readonly string $q,
        public readonly ?string $status,
        public readonly bool $isSupervisor,
        public readonly array $pagosByDni = [],
        public readonly ?LengthAwarePaginator $asignacionRows = null,
        public readonly bool $canReviewPromesasCna = true,
        public readonly bool $canReviewAsignaciones = true,
        public readonly string $roleLabel = 'Administrador',
    ) {
    }

    public function toArray(): array
    {
        return [
            'rows' => $this->rows,
            'cnaRows' => $this->cnaRows,
            'prodByOp' => $this->prodByOp,
            'pagosByDni' => $this->pagosByDni,
            'q' => $this->q,
            'status' => $this->status,
            'isSupervisor' => $this->isSupervisor,
            'asignacionRows' => $this->asignacionRows,
            'canReviewPromesasCna' => $this->canReviewPromesasCna,
            'canReviewAsignaciones' => $this->canReviewAsignaciones,
            'roleLabel' => $this->roleLabel,
        ];
    }
}
