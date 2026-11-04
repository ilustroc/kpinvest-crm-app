<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CnaSolicitud extends Model
{
    protected $table = 'cna_solicitudes';

    protected $fillable = [
    'correlativo','nro_carta','numdoc','dni','titular','producto',
    'operaciones','nota','observacion','fecha_pago_realizado','monto_pagado',
    'workflow_estado','user_id',
    'pre_aprobado_por','pre_aprobado_at',
    'aprobado_por','aprobado_at',
    'rechazado_por','rechazado_at','motivo_rechazo',
    'docx_path','pdf_path',
    ];

    private static ?bool $hasNumdocColumn = null;

    public function getDniAttribute(): ?string
    {
        return $this->attributes['numdoc'] ?? $this->attributes['dni'] ?? null;
    }

    public function getNumdocAttribute(): ?string
    {
        return $this->attributes['numdoc'] ?? $this->attributes['dni'] ?? null;
    }

    public function setDniAttribute($value): void
    {
        if (self::$hasNumdocColumn === null) {
            self::$hasNumdocColumn = \Illuminate\Support\Facades\Schema::hasColumn('cna_solicitudes', 'numdoc');
        }
        $col = self::$hasNumdocColumn ? 'numdoc' : 'dni';
        $this->attributes[$col] = $value;
    }

    public function setNumdocAttribute($value): void
    {
        if (self::$hasNumdocColumn === null) {
            self::$hasNumdocColumn = \Illuminate\Support\Facades\Schema::hasColumn('cna_solicitudes', 'numdoc');
        }
        $col = self::$hasNumdocColumn ? 'numdoc' : 'dni';
        $this->attributes[$col] = $value;
    }

    protected $casts = [
        'operaciones'          => 'array',
        'fecha_pago_realizado' => 'date',
        'monto_pagado'         => 'decimal:2',
        'pre_aprobado_at'      => 'datetime',
        'aprobado_at'          => 'datetime',
        'rechazado_at'         => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Services\Panel\PanelDashboardService::invalidate();
        });

        static::deleted(function () {
            \App\Services\Panel\PanelDashboardService::invalidate();
        });
    }

    // Relaciones útiles
    public function solicitante()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
    public function preAprobadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'pre_aprobado_por');
    }
    public function aprobadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'aprobado_por');
    }
    public function rechazadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'rechazado_por');
    }
}
