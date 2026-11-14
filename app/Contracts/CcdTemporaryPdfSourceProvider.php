<?php

namespace App\Contracts;

/**
 * Señala que sourcePath() entrega un archivo temporal, no el PDF original.
 * El resolvedor lo libera al terminar la extracción, incluso ante errores.
 */
interface CcdTemporaryPdfSourceProvider extends CcdZipV12PdfSourceProvider
{
    public function releaseTemporarySource(string $path): void;
}
