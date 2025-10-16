<?php

namespace App\Support;

use App\Models\User;
use App\Models\PromesaPago;
use App\Models\CnaSolicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class WorkflowMailer
{
    /* ======================== PROMESAS ======================== */

    public static function promesaPendiente(PromesaPago $p): void
    {
        [$asesor, $supervisor, $admins, $data] = self::promesaContext($p);

        $views = ['mail.promesas','mail.promesa','emails.promesas','emails.promesa','mail.notification','emails.notification'];

        if ($supervisor && filter_var($supervisor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $supervisor->email, [
                'banner'     => '.: CRM :. Pre-aprobación pendiente',
                'cta'        => 'Abrir Autorización',
                'label_nro'  => 'N° Propuesta',
            ] + $data, ".: CRM :. Pre-aprobación pendiente — DNI {$p->dni} - Cliente: {$data['cliente']}");
        }

        if ($asesor && filter_var($asesor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $asesor->email, [
                'banner'     => 'Tu propuesta fue ENVIADA — esperando a Supervisor',
                'cta'        => 'Ver estado',
                'label_nro'  => 'N° Propuesta',
            ] + $data, 'Propuesta enviada — esperando a Supervisor');
        }
    }

    public static function promesaPreaprobada(PromesaPago $p): void
    {
        [$asesor, $supervisor, $admins, $data] = self::promesaContext($p);
        $views = ['mail.promesas','mail.promesa','emails.promesas','emails.promesa','mail.notification','emails.notification'];

        foreach ($admins as $email) {
            self::send($views, $email, [
                'banner'     => '.: CRM :. Revisión de Administración',
                'cta'        => 'Abrir Autorización',
                'label_nro'  => 'N° Propuesta',
            ] + $data, ".: CRM :. Revisión de Administración — DNI {$p->dni} - Cliente: {$data['cliente']}");
        }

        if ($asesor && filter_var($asesor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $asesor->email, [
                'banner'     => 'Tu propuesta fue PRE-APROBADA — esperando a Administración',
                'cta'        => 'Ver estado',
                'label_nro'  => 'N° Propuesta',
            ] + $data, 'Tu propuesta fue PRE-APROBADA — esperando a Administración');
        }
    }

    public static function promesaRechazadaSup(PromesaPago $p, ?string $nota = null): void
    {
        [$asesor, $supervisor, $admins, $data] = self::promesaContext($p);
        $data['nota'] = trim((string)$nota);
        $views = ['mail.promesas','mail.promesa','emails.promesas','emails.promesa','mail.notification','emails.notification'];

        if ($asesor && filter_var($asesor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $asesor->email, [
                'banner'     => 'Tu propuesta fue RECHAZADA por Supervisor',
                'cta'        => 'Ver estado',
                'label_nro'  => 'N° Propuesta',
            ] + $data, 'Tu propuesta fue RECHAZADA por Supervisor');
        }
    }

    public static function promesaResuelta(PromesaPago $p, bool $aprobada, ?string $nota = null): void
    {
        [$asesor, $supervisor, $admins, $data] = self::promesaContext($p);
        $data['nota'] = trim((string)$nota);
        $titulo = $aprobada ? 'APROBADA' : 'RECHAZADA por Administración';
        $views = ['mail.promesas','mail.promesa','emails.promesas','emails.promesa','mail.notification','emails.notification'];

        if ($asesor && filter_var($asesor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $asesor->email, [
                'banner'     => 'Tu propuesta fue ' . $titulo,
                'cta'        => 'Ver estado',
                'label_nro'  => 'N° Propuesta',
            ] + $data, 'Tu propuesta fue ' . $titulo);
        }
    }

    /* ========================== CNA =========================== */

    public static function cnaPendiente(CnaSolicitud $c): void
    {
        [$asesor, $supervisor, $admins, $data] = self::cnaContext($c);
        $views = ['mail.cna','emails.cna','mail.notification','emails.notification'];

        if ($supervisor && filter_var($supervisor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $supervisor->email, [
                'banner' => '.: CRM :. Solicitud de CNA — Pre-aprobación',
                'cta'    => 'Abrir Autorización',
            ] + $data, ".: CRM :. Solicitud de CNA — DNI {$c->dni} - Cliente: {$data['cliente']}");
        }

        if ($asesor && filter_var($asesor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $asesor->email, [
                'banner' => 'Tu solicitud de CNA fue ENVIADA — esperando a Supervisor',
                'cta'    => 'Ver estado',
            ] + $data, 'CNA enviada — esperando a Supervisor');
        }
    }

    public static function cnaPreaprobada(CnaSolicitud $c): void
    {
        [$asesor, $supervisor, $admins, $data] = self::cnaContext($c);
        $views = ['mail.cna','emails.cna','mail.notification','emails.notification'];

        foreach ($admins as $email) {
            self::send($views, $email, [
                'banner' => '.: CRM :. Solicitud de CNA — Revisión de Administración',
                'cta'    => 'Abrir Autorización',
            ] + $data, ".: CRM :. CNA para revisión — DNI {$c->dni}");
        }

        if ($asesor && filter_var($asesor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $asesor->email, [
                'banner' => 'Tu CNA fue PRE-APROBADA — esperando a Administración',
                'cta'    => 'Ver estado',
            ] + $data, 'CNA PRE-APROBADA — esperando a Administración');
        }
    }

    public static function cnaRechazadaSup(CnaSolicitud $c, ?string $nota = null): void
    {
        [$asesor, $supervisor, $admins, $data] = self::cnaContext($c);
        $data['nota'] = trim((string)$nota);
        $views = ['mail.cna','emails.cna','mail.notification','emails.notification'];

        if ($asesor && filter_var($asesor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $asesor->email, [
                'banner' => 'Tu CNA fue RECHAZADA por Supervisor',
                'cta'    => 'Ver estado',
            ] + $data, 'CNA RECHAZADA por Supervisor');
        }
    }

    public static function cnaResuelta(CnaSolicitud $c, bool $aprobada, ?string $nota = null): void
    {
        [$asesor, $supervisor, $admins, $data] = self::cnaContext($c);
        $data['nota'] = trim((string)$nota);
        $titulo = $aprobada ? 'APROBADA' : 'RECHAZADA por Administración';
        $views = ['mail.cna','emails.cna','mail.notification','emails.notification'];

        if ($asesor && filter_var($asesor->email, FILTER_VALIDATE_EMAIL)) {
            self::send($views, $asesor->email, [
                'banner' => 'Tu CNA fue ' . $titulo,
                'cta'    => 'Ver estado',
            ] + $data, 'Tu CNA fue ' . $titulo);
        }
    }

    /* ========================= Helpers ======================= */

    /** Nombre de cliente desde clientes_cuentas (nuevo esquema). */
    private static function nombreClientePorDni(string $dni): string
    {
        return (string) DB::table('clientes_cuentas')
            ->where('numdoc', $dni)
            ->value('nombre') ?: '';
    }

    private static function promesaContext(PromesaPago $p): array
    {
        $asesor     = User::find($p->user_id);
        $supervisor = $asesor?->supervisor ?: User::where('role','supervisor')->first();
        $admins     = User::where('role','administrador')->pluck('email')->all();

        $cliente = $p->titular ?: self::nombreClientePorDni($p->dni);

        $ops = $p->relationLoaded('operaciones') && $p->operaciones->count()
            ? $p->operaciones->pluck('operacion')->implode(', ')
            : (string)($p->operacion ?? '');

        $data = [
            'tipo'        => $p->tipo === 'cancelacion' ? 'Cancelación' : 'Convenio',
            'dni'         => $p->dni,
            'cliente'     => $cliente ?: '—',
            'nro'         => $p->id,
            'operacion'   => $ops ?: '—',
            'procede'     => $supervisor?->name ?: '—',
            'link'        => route('autorizacion'),
            // opcionales para la vista:
            'nota'        => (string)($p->nota ?? ''),
            'observa'     => (string)($p->nota ?? ''),
            'banner'      => $data['banner'] ?? null,
            'cta'         => $data['cta'] ?? null,
            'label_nro'   => 'N° Propuesta',
        ];

        return [$asesor, $supervisor, $admins, $data];
    }

    private static function cnaContext(CnaSolicitud $c): array
    {
        $asesor     = User::find($c->user_id);
        $supervisor = $asesor?->supervisor ?: User::where('role','supervisor')->first();
        $admins     = User::where('role','administrador')->pluck('email')->all();

        $cliente = $c->titular ?: self::nombreClientePorDni($c->dni);
        $ops = collect((array)($c->operaciones ?? []))->filter()->implode(', ');

        $data = [
            'dni'        => $c->dni,
            'cliente'    => $cliente ?: '—',
            'nro'        => $c->nro_carta,
            'operacion'  => $ops ?: '—',
            'procede'    => $supervisor?->name ?: '—',
            'observa'    => (string)($c->observacion ?? ''),
            'link'       => route('autorizacion') . '#cna',
            // opcionales:
            'banner'     => $data['banner'] ?? null,
            'cta'        => $data['cta'] ?? null,
            'label_nro'  => 'N° Carta',
        ];

        return [$asesor, $supervisor, $admins, $data];
    }

    /**
     * Envía usando la primera vista existente; si ninguna existe, usa Mail::raw.
     *
     * @param string|array $view Nombre de vista o lista de candidatos
     */
    private static function send(string|array $view, string $to, array $data, string $subject): void
    {
        $candidates = is_array($view) ? $view : [$view];
        $chosen = null;
        foreach ($candidates as $v) {
            if (is_string($v) && View::exists($v)) { $chosen = $v; break; }
        }

        if ($chosen) {
            Mail::send($chosen, $data, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
            return;
        }

        // Fallback en texto plano y log para diagnosticar
        \Log::warning('Email view not found. Using raw fallback.', [
            'to'         => $to,
            'subject'    => $subject,
            'candidates' => $candidates,
        ]);

        $text = self::fallbackText($data);
        Mail::raw($text, function ($m) use ($to, $subject) {
            $m->to($to)->subject($subject);
        });
    }

    /** Construye un cuerpo de texto simple como respaldo. */
    private static function fallbackText(array $d): string
    {
        $lines = [];
        if (!empty($d['banner']))   $lines[] = $d['banner'];
        if (!empty($d['tipo']))     $lines[] = "Tipo: {$d['tipo']}";
        if (!empty($d['dni']))      $lines[] = "DNI: {$d['dni']}";
        if (!empty($d['cliente']))  $lines[] = "Cliente: {$d['cliente']}";
        if (!empty($d['nro']))      $lines[] = "Nro: {$d['nro']}";
        if (!empty($d['operacion']))$lines[] = "Operación(es): {$d['operacion']}";
        if (!empty($d['nota']))     $lines[] = "Nota: {$d['nota']}";
        if (!empty($d['observa']))  $lines[] = "Observación: {$d['observa']}";
        if (!empty($d['link']))     $lines[] = "Abrir: {$d['link']}";
        return implode("\n", $lines) ?: 'Notificación';
    }
}
