<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignarCliente extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADA = 'aprobada';
    public const ESTADO_RECHAZADA = 'rechazada';

    public const ORIGEN_MANUAL = 'manual';
    public const ORIGEN_CARGA_MASIVA = 'carga_masiva';

    protected $table = 'asignar_clientes';

    public $timestamps = true;

    protected $fillable = [
        'numdoc',
        'operacion',
        'cliente_cuenta_id',
        'name',
        'solicitante_user_id',
        'asesor_destino_user_id',
        'fecha_solicitud',
        'motivo',
        'estado',
        'aprobado_por_user_id',
        'fecha_aprobacion',
        'observacion_resolucion',
        'origen',
    ];

    protected $casts = [
        'cliente_cuenta_id' => 'integer',
        'solicitante_user_id' => 'integer',
        'asesor_destino_user_id' => 'integer',
        'aprobado_por_user_id' => 'integer',
        'fecha_solicitud' => 'datetime',
        'fecha_aprobacion' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $asignacion): void {
            throw new DomainException('No se permite eliminar físicamente registros del historial de asignaciones.');
        });

        static::updating(function (self $asignacion): void {
            $originalEstado = (string) $asignacion->getOriginal('estado');

            if ($originalEstado === self::ESTADO_APROBADA) {
                throw new DomainException('No se permite editar una asignación ya aprobada. Toda corrección debe registrarse como una nueva asignación.');
            }

            if ($originalEstado === self::ESTADO_RECHAZADA) {
                throw new DomainException('No se permite modificar una solicitud de asignación que ya fue rechazada.');
            }

            $immutableFields = [
                'numdoc',
                'operacion',
                'cliente_cuenta_id',
                'solicitante_user_id',
                'asesor_destino_user_id',
                'fecha_solicitud',
                'motivo',
                'origen',
            ];

            foreach ($immutableFields as $field) {
                if ($asignacion->isDirty($field) && $asignacion->getOriginal($field) !== null) {
                    throw new DomainException("No se permite alterar el campo histórico '{$field}' de una solicitud de asignación.");
                }
            }
        });
    }

    public function clienteCuenta(): BelongsTo
    {
        return $this->belongsTo(ClienteCuenta::class, 'cliente_cuenta_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }

    public function asesorDestino(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_destino_user_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_user_id');
    }

    public function getAsesorNombreAttribute(): ?string
    {
        if ($this->relationLoaded('asesorDestino') && $this->asesorDestino) {
            return trim(str_replace("\xC2\xA0", ' ', (string) $this->asesorDestino->name));
        }

        $rawName = $this->attributes['name'] ?? null;
        if ($rawName !== null && trim((string) $rawName) !== '') {
            return trim(str_replace("\xC2\xA0", ' ', (string) $rawName));
        }

        return $this->asesorDestino
            ? trim(str_replace("\xC2\xA0", ' ', (string) $this->asesorDestino->name))
            : null;
    }

    public function getSolicitanteNombreAttribute(): ?string
    {
        if ($this->relationLoaded('solicitante') && $this->solicitante) {
            return trim(str_replace("\xC2\xA0", ' ', (string) $this->solicitante->name));
        }

        return $this->solicitante
            ? trim(str_replace("\xC2\xA0", ' ', (string) $this->solicitante->name))
            : null;
    }

    public function getAprobadorNombreAttribute(): ?string
    {
        if ($this->relationLoaded('aprobadoPor') && $this->aprobadoPor) {
            return trim(str_replace("\xC2\xA0", ' ', (string) $this->aprobadoPor->name));
        }

        return $this->aprobadoPor
            ? trim(str_replace("\xC2\xA0", ' ', (string) $this->aprobadoPor->name))
            : null;
    }

    public function isPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function isAprobada(): bool
    {
        return $this->estado === self::ESTADO_APROBADA;
    }

    public function isRechazada(): bool
    {
        return $this->estado === self::ESTADO_RECHAZADA;
    }
}
