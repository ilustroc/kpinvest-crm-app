<?php

namespace App\Contracts;

/** Proporciona una razón técnica segura para el CSV privado de auditoría. */
interface CcdZipV12PdfSourceDiagnostics
{
    public function lastFailure(): ?string;
}
