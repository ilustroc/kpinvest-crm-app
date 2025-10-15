<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoPropia extends Model
{
    protected $table = 'pagos_propia';

    protected $fillable = [
        'lote_id','dni','operacion',
        'entidad','nombre_cliente','monto_pagado',
        'fecha','gestor','cosecha',
        'cuenta_recaudo',
    ];

    protected $casts = [
        'fecha'           => 'date',
        'monto_pagado'    => 'decimal:2',
    ];
    
    public function lote() {
        return $this->belongsTo(PagoLote::class, 'lote_id');
    }
}
