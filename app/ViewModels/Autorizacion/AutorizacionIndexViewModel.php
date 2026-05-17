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
    ) {
    }

    public function toArray(): array
    {
        return [
            'rows' => $this->rows,
            'cnaRows' => $this->cnaRows,
            'prodByOp' => $this->prodByOp,
            'q' => $this->q,
            'status' => $this->status,
            'isSupervisor' => $this->isSupervisor,
        ];
    }
}
