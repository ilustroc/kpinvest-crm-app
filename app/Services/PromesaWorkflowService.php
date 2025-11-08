<?php

namespace App\Services;

use App\Models\PromesaPago;
use App\Support\WorkflowMailer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PromesaWorkflowService
{
    public static function preaprobar(PromesaPago $p, ?string $nota = null): void
    {
        if (($p->workflow_estado ?? 'pendiente') !== 'pendiente') {
            throw new \RuntimeException('Solo se puede pre-aprobar una promesa Pendiente.');
        }

        $p->update([
            'workflow_estado'    => 'preaprobada',
            'pre_aprobado_por'   => Auth::id(),
            'pre_aprobado_at'    => now(),
            'nota_preaprobacion' => $nota ? trim($nota) : null,
            'rechazado_por'      => null,
            'rechazado_at'       => null,
            'nota_rechazo'       => null,
        ]);

        self::safe(fn() => WorkflowMailer::promesaPreaprobada($p), ['promesa_id' => $p->id]);
    }

    public static function rechazarSup(PromesaPago $p, ?string $nota = null): void
    {
        if (($p->workflow_estado ?? 'pendiente') !== 'pendiente') {
            throw new \RuntimeException('Solo se puede rechazar una promesa Pendiente.');
        }

        $p->update([
            'workflow_estado' => 'rechazada_sup',
            'rechazado_por'   => Auth::id(),
            'rechazado_at'    => now(),
            'nota_rechazo'    => $nota ? mb_substr($nota, 0, 500) : null,
        ]);

        self::safe(fn() => WorkflowMailer::promesaRechazadaSup($p, $nota), ['promesa_id' => $p->id]);
    }

    public static function aprobar(PromesaPago $p, ?string $nota = null): void
    {
        if (($p->workflow_estado ?? '') !== 'preaprobada') {
            throw new \RuntimeException('Solo se puede aprobar una promesa Pre-aprobada.');
        }

        $p->update([
            'workflow_estado' => 'aprobada',
            'aprobado_por'    => Auth::id(),
            'aprobado_at'     => now(),
            'nota_aprobacion' => $nota ? trim($nota) : null,
            'rechazado_por'   => null,
            'rechazado_at'    => null,
            'nota_rechazo'    => null,
        ]);

        self::safe(fn() => WorkflowMailer::promesaResuelta($p, true, $nota), ['promesa_id' => $p->id]);
    }

    public static function rechazarAdmin(PromesaPago $p, ?string $nota = null): void
    {
        if (($p->workflow_estado ?? '') !== 'preaprobada') {
            throw new \RuntimeException('Solo se puede rechazar una promesa Pre-aprobada.');
        }

        $p->update([
            'workflow_estado' => 'rechazada',
            'rechazado_por'   => Auth::id(),
            'rechazado_at'    => now(),
            'nota_rechazo'    => $nota ? mb_substr($nota, 0, 500) : null,
        ]);

        self::safe(fn() => WorkflowMailer::promesaResuelta($p, false, $nota), ['promesa_id' => $p->id]);
    }

    private static function safe(callable $fn, array $ctx = []): void
    {
        try { $fn(); }
        catch (\Throwable $e) {
            Log::error('WorkflowMailer error', $ctx + [
                'msg' => $e->getMessage(),
                'file'=> $e->getFile(),
                'line'=> $e->getLine(),
            ]);
        }
    }
}
