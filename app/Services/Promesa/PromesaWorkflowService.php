<?php

namespace App\Services\Promesa;

use App\Models\PromesaPago;
use App\Support\WorkflowNotifier;
use Illuminate\Support\Facades\Log;

class PromesaWorkflowService
{
    public function preapprove(PromesaPago $promesa, int $userId, ?string $note = null): void
    {
        if (($promesa->workflow_estado ?? 'pendiente') !== 'pendiente') {
            throw new \RuntimeException('Solo se puede pre-aprobar una promesa Pendiente.');
        }

        $promesa->update([
            'workflow_estado' => 'preaprobada',
            'pre_aprobado_por' => $userId,
            'pre_aprobado_at' => now(),
            'nota_preaprobacion' => $note ? trim($note) : null,
            'rechazado_por' => null,
            'rechazado_at' => null,
            'nota_rechazo' => null,
        ]);

        $this->safe(fn () => WorkflowNotifier::promesaPreaprobada($promesa), ['promesa_id' => $promesa->id]);
    }

    public function rejectBySupervisor(PromesaPago $promesa, int $userId, ?string $note = null): void
    {
        if (($promesa->workflow_estado ?? 'pendiente') !== 'pendiente') {
            throw new \RuntimeException('Solo se puede rechazar una promesa Pendiente.');
        }

        $promesa->update([
            'workflow_estado' => 'rechazada_sup',
            'rechazado_por' => $userId,
            'rechazado_at' => now(),
            'nota_rechazo' => $note ? mb_substr($note, 0, 500) : null,
        ]);

        $this->safe(fn () => WorkflowNotifier::promesaRechazadaSup($promesa, $note), ['promesa_id' => $promesa->id]);
    }

    public function approve(PromesaPago $promesa, int $userId, ?string $note = null): void
    {
        if (($promesa->workflow_estado ?? '') !== 'preaprobada') {
            throw new \RuntimeException('Solo se puede aprobar una promesa Pre-aprobada.');
        }

        $promesa->update([
            'workflow_estado' => 'aprobada',
            'aprobado_por' => $userId,
            'aprobado_at' => now(),
            'nota_aprobacion' => $note ? trim($note) : null,
            'rechazado_por' => null,
            'rechazado_at' => null,
            'nota_rechazo' => null,
        ]);

        $this->safe(fn () => WorkflowNotifier::promesaResuelta($promesa, true, $note), ['promesa_id' => $promesa->id]);
    }

    public function rejectByAdministrator(PromesaPago $promesa, int $userId, ?string $note = null): void
    {
        if (($promesa->workflow_estado ?? '') !== 'preaprobada') {
            throw new \RuntimeException('Solo se puede rechazar una promesa Pre-aprobada.');
        }

        $promesa->update([
            'workflow_estado' => 'rechazada',
            'rechazado_por' => $userId,
            'rechazado_at' => now(),
            'nota_rechazo' => $note ? mb_substr($note, 0, 500) : null,
        ]);

        $this->safe(fn () => WorkflowNotifier::promesaResuelta($promesa, false, $note), ['promesa_id' => $promesa->id]);
    }

    private function safe(callable $callback, array $context = []): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::error('WorkflowNotifier error', $context + [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
