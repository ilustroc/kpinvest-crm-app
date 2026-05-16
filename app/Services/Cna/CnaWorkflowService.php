<?php

namespace App\Services\Cna;

use App\Models\CnaSolicitud;
use App\Models\User;
use App\Support\WorkflowMailer;
use DomainException;

class CnaWorkflowService
{
    public function __construct(private readonly CnaDocumentService $documents)
    {
    }

    public function preapprove(CnaSolicitud $cna, User $user, ?string $note = null): void
    {
        if ($cna->workflow_estado !== 'pendiente') {
            throw new DomainException('Solo se puede pre-aprobar una solicitud pendiente.');
        }

        $this->appendNote($cna, $user, $note);

        $cna->update([
            'workflow_estado' => 'preaprobada',
            'pre_aprobado_por' => $user->id,
            'pre_aprobado_at' => now(),
            'rechazado_por' => null,
            'rechazado_at' => null,
            'motivo_rechazo' => null,
        ]);

        WorkflowMailer::cnaPreaprobada($cna);
    }

    public function rejectBySupervisor(CnaSolicitud $cna, User $user, ?string $note = null): void
    {
        if ($cna->workflow_estado !== 'pendiente') {
            throw new DomainException('Solo se puede rechazar una solicitud pendiente.');
        }

        $note = substr((string) $note, 0, 500);

        $cna->update([
            'workflow_estado' => 'rechazada_sup',
            'rechazado_por' => $user->id,
            'rechazado_at' => now(),
            'motivo_rechazo' => $note,
        ]);

        WorkflowMailer::cnaRechazadaSup($cna, $note);
    }

    public function approve(CnaSolicitud $cna, User $user, ?string $note = null): void
    {
        if ($cna->workflow_estado !== 'preaprobada') {
            throw new DomainException('Solo se puede aprobar una CNA pre-aprobada.');
        }

        $this->appendNote($cna, $user, $note);

        $cna->update([
            'workflow_estado' => 'aprobada',
            'aprobado_por' => $user->id,
            'aprobado_at' => now(),
        ]);

        $this->documents->generateOutputsFromTemplate($cna);

        WorkflowMailer::cnaResuelta($cna, true, $note);
    }

    public function rejectByAdministrator(CnaSolicitud $cna, User $user, ?string $note = null): void
    {
        if ($cna->workflow_estado !== 'preaprobada') {
            throw new DomainException('Solo se puede rechazar una solicitud pre-aprobada.');
        }

        $note = substr((string) $note, 0, 500);

        $cna->update([
            'workflow_estado' => 'rechazada',
            'rechazado_por' => $user->id,
            'rechazado_at' => now(),
            'motivo_rechazo' => $note,
        ]);

        WorkflowMailer::cnaResuelta($cna, false, $note);
    }

    private function appendNote(CnaSolicitud $cna, User $user, ?string $note): void
    {
        $text = trim((string) $note);

        if ($text === '') {
            return;
        }

        $prefix = '['.now()->format('Y-m-d H:i').'] '.($user->name ?? 'sistema').': ';
        $cna->nota = trim(($cna->nota ? $cna->nota."\n" : '').$prefix.$text);
    }
}
