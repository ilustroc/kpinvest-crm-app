<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'lote_id',
        'numdoc',
        'dni',
        'operacion',
        'entidad',
        'nombre_cliente',
        'monto_pagado',
        'fecha',
        'gestor',
        'cosecha',
        'cuenta_recaudo',
    ];

    public function getDniAttribute(): ?string
    {
        return $this->attributes['numdoc'] ?? $this->attributes['dni'] ?? null;
    }

    public function setDniAttribute($value): void
    {
        $this->attributes['numdoc'] = $value;
    }

    protected $casts = [
        'fecha'        => 'date',
        'monto_pagado' => 'decimal:2',
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

    public function lote()
    {
        return $this->belongsTo(PagoLote::class, 'lote_id');
    }
}
