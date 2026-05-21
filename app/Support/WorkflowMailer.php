<?php

namespace App\Support;

use App\Mail\WorkflowMail;
use App\Models\CnaSolicitud;
use App\Models\PromesaPago;
use App\Models\User;
use App\Support\Authorization\Roles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class WorkflowMailer
{
    public static function promesaPendiente(PromesaPago $promesa): void
    {
        $context = self::promesaContext($promesa);
        $subject = "[CRM] Promesa pendiente de preaprobacion - DNI {$context['dni']} - {$context['cliente']}";

        self::sendActionMail(
            self::supervisorRecipients($context['asesor']),
            $subject,
            'Promesa pendiente de preaprobacion',
            $context['promesa_fields'],
            self::authorizationUrl('promesa', $promesa->id, $promesa->dni, 'pendiente'),
            'Abrir autorización',
            'El asesor registro una promesa que requiere revision de supervisor.',
            'Pendiente',
            'Promesas',
        );

        self::sendInformativeMail(
            $context['asesor'],
            "[CRM] Promesa enviada - DNI {$context['dni']} - {$context['cliente']}",
            'Promesa enviada para preaprobacion',
            $context['promesa_fields'],
            'La promesa fue registrada y esta pendiente de preaprobacion.',
            'Pendiente',
            'Promesas',
        );
    }

    public static function promesaPreaprobada(PromesaPago $promesa): void
    {
        $context = self::promesaContext($promesa);
        $subject = "[CRM] Promesa preaprobada - requiere aprobacion - DNI {$context['dni']} - {$context['cliente']}";

        self::sendActionMail(
            self::adminRecipients(),
            $subject,
            'Promesa preaprobada: requiere aprobacion',
            $context['promesa_fields'],
            self::authorizationUrl('promesa', $promesa->id, $promesa->dni, 'preaprobada'),
            'Abrir autorización',
            'La promesa fue preaprobada por supervision y requiere decision de administracion.',
            'Preaprobada',
            'Promesas',
        );

        self::sendInformativeMail(
            $context['asesor'],
            "[CRM] Promesa preaprobada - DNI {$context['dni']} - {$context['cliente']}",
            'Promesa preaprobada',
            $context['promesa_fields'],
            'La promesa fue preaprobada y paso a revision de administracion.',
            'Preaprobada',
            'Promesas',
        );
    }

    public static function promesaRechazadaSup(PromesaPago $promesa, ?string $note = null): void
    {
        $context = self::promesaContext($promesa, $note);

        self::sendInformativeMail(
            $context['asesor'],
            "[CRM] Promesa rechazada por supervisor - DNI {$context['dni']} - {$context['cliente']}",
            'Promesa rechazada por supervisor',
            $context['promesa_fields'],
            'La promesa fue rechazada durante la revision de supervisor.',
            'Rechazada por supervisor',
            'Promesas',
        );
    }

    public static function promesaResuelta(PromesaPago $promesa, bool $approved, ?string $note = null): void
    {
        $context = self::promesaContext($promesa, $note);
        $status = $approved ? 'Aprobada' : 'Rechazada';

        self::sendInformativeMail(
            $context['asesor'],
            "[CRM] Promesa ".strtolower($status)." - DNI {$context['dni']} - {$context['cliente']}",
            "Promesa {$status}",
            $context['promesa_fields'],
            "La promesa fue {$status}.",
            $status,
            'Promesas',
        );
    }

    public static function cnaPendiente(CnaSolicitud $cna): void
    {
        $context = self::cnaContext($cna);
        $subject = "[CRM] CNA pendiente de preaprobacion - DNI {$context['dni']} - {$context['cliente']}";

        self::sendActionMail(
            self::supervisorRecipients($context['asesor']),
            $subject,
            'CNA pendiente de preaprobacion',
            $context['cna_fields'],
            self::authorizationUrl('cna', $cna->id, $cna->dni, 'pendiente'),
            'Abrir autorización',
            'El asesor registro una solicitud CNA que requiere revision de supervisor.',
            'Pendiente',
            'CNA',
        );

        self::sendInformativeMail(
            $context['asesor'],
            "[CRM] CNA enviada - DNI {$context['dni']} - {$context['cliente']}",
            'CNA enviada para preaprobacion',
            $context['cna_fields'],
            'La CNA fue registrada y esta pendiente de preaprobacion.',
            'Pendiente',
            'CNA',
        );
    }

    public static function cnaPreaprobada(CnaSolicitud $cna): void
    {
        $context = self::cnaContext($cna);
        $subject = "[CRM] CNA preaprobada - requiere aprobacion - DNI {$context['dni']} - {$context['cliente']}";

        self::sendActionMail(
            self::adminRecipients(),
            $subject,
            'CNA preaprobada: requiere aprobacion',
            $context['cna_fields'],
            self::authorizationUrl('cna', $cna->id, $cna->dni, 'preaprobada'),
            'Abrir autorización',
            'La CNA fue preaprobada por supervision y requiere decision de administracion.',
            'Preaprobada',
            'CNA',
        );

        self::sendInformativeMail(
            $context['asesor'],
            "[CRM] CNA preaprobada - DNI {$context['dni']} - {$context['cliente']}",
            'CNA preaprobada',
            $context['cna_fields'],
            'La CNA fue preaprobada y paso a revision de administracion.',
            'Preaprobada',
            'CNA',
        );
    }

    public static function cnaRechazadaSup(CnaSolicitud $cna, ?string $note = null): void
    {
        $context = self::cnaContext($cna, $note);

        self::sendInformativeMail(
            $context['asesor'],
            "[CRM] CNA rechazada por supervisor - DNI {$context['dni']} - {$context['cliente']}",
            'CNA rechazada por supervisor',
            $context['cna_fields'],
            'La CNA fue rechazada durante la revision de supervisor.',
            'Rechazada por supervisor',
            'CNA',
        );
    }

    public static function cnaResuelta(CnaSolicitud $cna, bool $approved, ?string $note = null): void
    {
        $context = self::cnaContext($cna, $note);
        $status = $approved ? 'Aprobada' : 'Rechazada';

        self::sendInformativeMail(
            $context['asesor'],
            "[CRM] CNA ".strtolower($status)." - DNI {$context['dni']} - {$context['cliente']}",
            "CNA {$status}",
            $context['cna_fields'],
            "La CNA fue {$status}.",
            $status,
            'CNA',
        );
    }

    public static function promesaContext(PromesaPago $promesa, ?string $note = null): array
    {
        $promesa->loadMissing('operaciones', 'user');

        $asesor = $promesa->user ?: User::find($promesa->user_id);
        $cliente = self::nombreClientePorDni((string) $promesa->dni);
        $operations = $promesa->operaciones->pluck('operacion')->filter()->implode(', ')
            ?: (string) ($promesa->operacion ?? '');
        $amount = $promesa->tipo === 'cancelacion'
            ? (float) ($promesa->monto ?? 0)
            : (float) ($promesa->monto_convenio ?? 0);

        $fields = [
            'Cliente' => $cliente ?: '-',
            'Documento' => (string) $promesa->dni,
            'Operacion(es)' => $operations ?: '-',
            'Tipo de promesa' => $promesa->tipo === 'cancelacion' ? 'Cancelacion' : 'Convenio',
            'Monto' => 'S/ '.number_format($amount, 2),
            'Fecha de pago' => optional($promesa->fecha_pago)->format('Y-m-d') ?: '-',
            'Asesor' => $asesor?->name ?: '-',
        ];

        if ($promesa->nota) {
            $fields['Observacion'] = (string) $promesa->nota;
        }

        if (trim((string) $note) !== '') {
            $fields['Nota de decision'] = trim((string) $note);
        }

        return [
            'asesor' => $asesor,
            'dni' => (string) $promesa->dni,
            'cliente' => $cliente ?: '-',
            'fields' => $fields,
            'promesa_fields' => $fields,
        ];
    }

    public static function cnaContext(CnaSolicitud $cna, ?string $note = null): array
    {
        $asesor = User::find($cna->user_id);
        $cliente = $cna->titular ?: self::nombreClientePorDni((string) $cna->dni);
        $operations = collect((array) ($cna->operaciones ?? []))
            ->map(fn ($operation) => trim((string) $operation))
            ->filter()
            ->implode(', ');

        $fields = [
            'Nro. carta' => (string) $cna->nro_carta,
            'Cliente' => $cliente ?: '-',
            'Documento' => (string) $cna->dni,
            'Operacion(es)' => $operations ?: '-',
            'Procede de / asesor' => $asesor?->name ?: '-',
        ];

        if ($cna->observacion) {
            $fields['Observacion'] = (string) $cna->observacion;
        }

        if (trim((string) $note) !== '') {
            $fields['Nota de decision'] = trim((string) $note);
        }

        return [
            'asesor' => $asesor,
            'dni' => (string) $cna->dni,
            'cliente' => $cliente ?: '-',
            'fields' => $fields,
            'cna_fields' => $fields,
        ];
    }

    private static function supervisorRecipients(?User $asesor): array
    {
        return self::supervisorRecipientUsers($asesor)
            ->pluck('email')
            ->all();
    }

    public static function supervisorRecipientUsers(?User $asesor): Collection
    {
        $supervisor = $asesor?->supervisor;

        if ($supervisor && self::isActiveRecipient($supervisor)) {
            return collect([$supervisor]);
        }

        return self::adminRecipientUsers();
    }

    private static function adminRecipients(): array
    {
        return self::adminRecipientUsers()
            ->pluck('email')
            ->all();
    }

    public static function adminRecipientUsers(): Collection
    {
        return User::query()
            ->where('role', Roles::ADMINISTRADOR)
            ->where('active', 1)
            ->get()
            ->filter(fn (User $user) => self::isActiveRecipient($user))
            ->unique('id')
            ->values();
    }

    private static function sendInformativeMail(
        ?User $recipient,
        string $subject,
        string $title,
        array $fields,
        string $intro,
        string $status,
        string $module,
    ): void {
        if (! self::isActiveRecipient($recipient)) {
            return;
        }

        self::sendActionMail(
            [$recipient->email],
            $subject,
            $title,
            $fields,
            route('clientes.show', $fields['Documento']),
            'Ver estado',
            $intro,
            $status,
            $module,
        );
    }

    private static function sendActionMail(
        array $recipients,
        string $subject,
        string $title,
        array $fields,
        string $actionUrl,
        string $actionText,
        string $intro,
        string $status,
        string $module,
    ): void {
        foreach (array_unique(array_filter($recipients)) as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            Mail::to($email)->send(new WorkflowMail(
                subject: $subject,
                title: $title,
                fields: $fields,
                actionUrl: $actionUrl,
                actionText: $actionText,
                intro: $intro,
                status: $status,
                module: $module,
            ));
        }
    }

    public static function authorizationUrl(string $type, int|string $id, string $dni, string $status): string
    {
        return route('autorizacion', [
            'tipo' => $type,
            'id' => $id,
            'q' => $dni,
            'status' => $status,
        ]);
    }

    public static function statusUrl(string $dni): string
    {
        return route('clientes.show', $dni);
    }

    private static function nombreClientePorDni(string $dni): string
    {
        return (string) DB::table('clientes_cuentas')
            ->where('numdoc', trim($dni))
            ->value('nombre') ?: '';
    }

    private static function isActiveRecipient(?User $user): bool
    {
        return $user
            && (bool) $user->active
            && filter_var($user->email, FILTER_VALIDATE_EMAIL);
    }
}
