<?php

namespace App\ViewModels\Cliente;

use Illuminate\Support\Collection;

class ClienteShowViewModel
{
    public readonly string $dni;
    public readonly string $numdoc;
    public function __construct(
        string $numdoc,
        public readonly string $titular,
        public readonly Collection $cuentas,
        public readonly Collection $pagos,
        public readonly Collection $promesas,
        public readonly Collection $ccdDocs,
        public readonly Collection $ccdByDni,
        public readonly Collection $ccdByCodigo,
        public readonly Collection $ccdByCosecha,
        public readonly array $ccdCosechaKeys,
        public readonly Collection $cnasByCuenta,
        public readonly Collection $cnasByOperacion,
        public readonly Collection $pagosGrouped,
        public readonly ?string $nextNroCarta,
        public readonly float $totPagos,
        ?string $dni = null,
    ) {
        $this->numdoc = $numdoc ?: (string) $dni;
        $this->dni = $dni ?: $this->numdoc;
    }

    public function toArray(): array
    {
        return [
            'numdoc' => $this->numdoc,
            'dni' => $this->dni,
            'titular' => $this->titular,
            'cuentas' => $this->cuentas,
            'pagos' => $this->pagos,
            'promesas' => $this->promesas,
            'ccdDocs' => $this->ccdDocs,
            'ccdByDni' => $this->ccdByDni,
            'ccdByCodigo' => $this->ccdByCodigo,
            'ccdByCosecha' => $this->ccdByCosecha,
            'ccdCosechaKeys' => $this->ccdCosechaKeys,
            'cnasByCuenta' => $this->cnasByCuenta,
            'cnasByOperacion' => $this->cnasByOperacion,
            'pagosGrouped' => $this->pagosGrouped,
            'nextNroCarta' => $this->nextNroCarta,
            'totPagos' => $this->totPagos,
        ];
    }
}
