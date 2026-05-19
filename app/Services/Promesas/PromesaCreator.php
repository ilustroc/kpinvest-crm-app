<?php

namespace App\Services\Promesas;

use App\Http\Requests\StorePromesaRequest;
use App\Services\Promesa\PromesaCreationService;

/**
 * Wrapper legacy de compatibilidad.
 *
 * La implementacion V3 real vive en App\Services\Promesa\PromesaCreationService.
 * Se mantiene esta clase para no romper codigo externo o referencias historicas
 * que aun puedan resolver App\Services\Promesas\PromesaCreator.
 */
class PromesaCreator
{
    public function __construct(private readonly PromesaCreationService $promesas)
    {
    }

    /**
     * Compatibilidad legacy: crea la promesa y retorna [PromesaPago $promesa, string $mensaje].
     */
    public function createFromRequest(string $dni, StorePromesaRequest $request): array
    {
        return $this->promesas->createFromRequest($dni, $request);
    }
}
