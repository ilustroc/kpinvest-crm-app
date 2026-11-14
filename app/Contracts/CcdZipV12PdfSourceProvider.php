<?php

namespace App\Contracts;

use App\Models\CcdCliente;

/**
 * Adaptador de sólo lectura para instalaciones que conservan CCD dentro de
 * ZIP V12. El lector propio de cada hosting puede implementarlo sin cambiar
 * el controlador de descarga ni mover el PDF original.
 */
interface CcdZipV12PdfSourceProvider
{
    /** Devuelve una ruta temporal/legible al PDF, o null si no lo encuentra. */
    public function sourcePath(CcdCliente $ccd): ?string;
}
