<?php

namespace App\Support;

use App\Models\CnaSolicitud;
use App\Models\PromesaPago;
use App\Models\User;
use App\Notifications\Workflow\WorkflowActionRequiredNotification;
use App\Notifications\Workflow\WorkflowStatusNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Notification;

class WorkflowNotifier
{
    public static function promesaPendiente(PromesaPago $promesa): void
    {
        $context = WorkflowMailer::promesaContext($promesa);

        self::safeMail(fn () => WorkflowMailer::promesaPendiente($promesa), ['promesa_id' => $promesa->id]);
        self::notifyActionRequired(
            WorkflowMailer::supervisorRecipientUsers($context['asesor']),
            'promesa',
            $promesa->id,
            $context,
            'pendiente',
            'Promesa pendiente de preaprobacion',
            'Tienes una promesa pendiente de preaprobacion.',
            WorkflowMailer::authorizationUrl('promesa', $promesa->id, $context['dni'], 'pendiente'),
        );
        self::notifyStatus(
            $context['asesor'],
            'promesa',
            $promesa->id,
            $context,
            'pendiente',
            'Promesa enviada',
            'La promesa fue registrada y esta pendiente de preaprobacion.',
        );
    }

    public static function promesaPreaprobada(PromesaPago $promesa): void
    {
        $context = WorkflowMailer::promesaContext($promesa);

        self::safeMail(fn () => WorkflowMailer::promesaPreaprobada($promesa), ['promesa_id' => $promesa->id]);
        self::notifyActionRequired(
            WorkflowMailer::adminRecipientUsers(),
            'promesa',
            $promesa->id,
            $context,
            'preaprobada',
            'Promesa preaprobada: requiere aprobacion',
            'Una promesa preaprobada requiere decision de administracion.',
            WorkflowMailer::authorizationUrl('promesa', $promesa->id, $context['dni'], 'preaprobada'),
        );
        self::notifyStatus(
            $context['asesor'],
            'promesa',
            $promesa->id,
            $context,
            'preaprobada',
            'Promesa preaprobada',
            'La promesa paso a revision de administracion.',
        );
    }

    public static function promesaRechazadaSup(PromesaPago $promesa, ?string $note = null): void
    {
        $context = WorkflowMailer::promesaContext($promesa, $note);

        self::safeMail(fn () => WorkflowMailer::promesaRechazadaSup($promesa, $note), ['promesa_id' => $promesa->id]);
        self::notifyStatus(
            $context['asesor'],
            'promesa',
            $promesa->id,
            $context,
            'rechazada_sup',
            'Promesa rechazada por supervisor',
            'La promesa fue rechazada durante la revision de supervisor.',
        );
    }

    public static function promesaResuelta(PromesaPago $promesa, bool $approved, ?string $note = null): void
    {
        $context = WorkflowMailer::promesaContext($promesa, $note);
        $status = $approved ? 'aprobada' : 'rechazada';
        $title = $approved ? 'Promesa aprobada' : 'Promesa rechazada';
        $message = $approved ? 'La promesa fue aprobada.' : 'La promesa fue rechazada.';

        self::safeMail(fn () => WorkflowMailer::promesaResuelta($promesa, $approved, $note), ['promesa_id' => $promesa->id]);
        self::notifyStatus($context['asesor'], 'promesa', $promesa->id, $context, $status, $title, $message);
    }

    public static function cnaPendiente(CnaSolicitud $cna): void
    {
        $context = WorkflowMailer::cnaContext($cna);

        self::safeMail(fn () => WorkflowMailer::cnaPendiente($cna), ['cna_id' => $cna->id]);
        self::notifyActionRequired(
            WorkflowMailer::supervisorRecipientUsers($context['asesor']),
            'cna',
            $cna->id,
            $context,
            'pendiente',
            'CNA pendiente de preaprobacion',
            'Tienes una solicitud CNA pendiente de preaprobacion.',
            WorkflowMailer::authorizationUrl('cna', $cna->id, $context['dni'], 'pendiente'),
        );
        self::notifyStatus(
            $context['asesor'],
            'cna',
            $cna->id,
            $context,
            'pendiente',
            'CNA enviada',
            'La CNA fue registrada y esta pendiente de preaprobacion.',
        );
    }

    public static function cnaPreaprobada(CnaSolicitud $cna): void
    {
        $context = WorkflowMailer::cnaContext($cna);

        self::safeMail(fn () => WorkflowMailer::cnaPreaprobada($cna), ['cna_id' => $cna->id]);
        self::notifyActionRequired(
            WorkflowMailer::adminRecipientUsers(),
            'cna',
            $cna->id,
            $context,
            'preaprobada',
            'CNA preaprobada: requiere aprobacion',
            'Una solicitud CNA preaprobada requiere decision de administracion.',
            WorkflowMailer::authorizationUrl('cna', $cna->id, $context['dni'], 'preaprobada'),
        );
        self::notifyStatus(
            $context['asesor'],
            'cna',
            $cna->id,
            $context,
            'preaprobada',
            'CNA preaprobada',
            'La CNA paso a revision de administracion.',
        );
    }

    public static function cnaRechazadaSup(CnaSolicitud $cna, ?string $note = null): void
    {
        $context = WorkflowMailer::cnaContext($cna, $note);

        self::safeMail(fn () => WorkflowMailer::cnaRechazadaSup($cna, $note), ['cna_id' => $cna->id]);
        self::notifyStatus(
            $context['asesor'],
            'cna',
            $cna->id,
            $context,
            'rechazada_sup',
            'CNA rechazada por supervisor',
            'La CNA fue rechazada durante la revision de supervisor.',
        );
    }

    public static function cnaResuelta(CnaSolicitud $cna, bool $approved, ?string $note = null): void
    {
        $context = WorkflowMailer::cnaContext($cna, $note);
        $status = $approved ? 'aprobada' : 'rechazada';
        $title = $approved ? 'CNA aprobada' : 'CNA rechazada';
        $message = $approved ? 'La CNA fue aprobada.' : 'La CNA fue rechazada.';

        self::safeMail(fn () => WorkflowMailer::cnaResuelta($cna, $approved, $note), ['cna_id' => $cna->id]);
        self::notifyStatus($context['asesor'], 'cna', $cna->id, $context, $status, $title, $message);
    }

    private static function notifyActionRequired(
        Collection $recipients,
        string $module,
        int $entityId,
        array $context,
        string $estado,
        string $title,
        string $message,
        string $actionUrl,
    ): void {
        $payload = self::payload($module, $entityId, $context, $estado, $title, $message, 'Abrir autorizacion', $actionUrl, true);

        $recipients
            ->filter(fn (User $user) => (bool) $user->active)
            ->unique('id')
            ->each(fn (User $user) => self::notifyUser($user, new WorkflowActionRequiredNotification($payload), [
                'module' => $module,
                'entity_id' => $entityId,
                'type' => $payload['type'],
            ]));
    }

    private static function notifyStatus(
        ?User $recipient,
        string $module,
        int $entityId,
        array $context,
        string $estado,
        string $title,
        string $message,
    ): void {
        if (! $recipient || ! $recipient->active) {
            return;
        }

        $payload = self::payload($module, $entityId, $context, $estado, $title, $message, 'Ver estado', WorkflowMailer::statusUrl($context['dni']), false);

        self::notifyUser($recipient, new WorkflowStatusNotification($payload), [
            'module' => $module,
            'entity_id' => $entityId,
            'type' => $payload['type'],
        ]);
    }

    private static function payload(
        string $module,
        int $entityId,
        array $context,
        string $estado,
        string $title,
        string $message,
        string $actionLabel,
        string $actionUrl,
        bool $actionRequired,
    ): array {
        $asesor = $context['asesor'] ?? null;

        return [
            'type' => $module.($actionRequired ? '_action_required' : '_status'),
            'module' => $module,
            'entity_id' => $entityId,
            'dni' => $context['dni'] ?? '',
            'cliente' => $context['cliente'] ?? '-',
            'estado' => $estado,
            'title' => $title,
            'message' => $message,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'created_by' => $asesor?->id,
            'created_by_name' => $asesor?->name,
        ];
    }

    private static function safeMail(callable $callback, array $context = []): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::error('WorkflowMailer error', $context + [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    private static function notifyUser(User $user, Notification $notification, array $context = []): void
    {
        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::error('WorkflowNotification error', $context + [
                'user_id' => $user->id,
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
