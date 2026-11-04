<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoLote extends Model
{
    protected $table = 'pagos_lotes';
    protected $fillable = ['archivo', 'usuario_id', 'total_registros'];

    public function getTipoAttribute(): ?string
    {
        return $this->attributes['tipo'] ?? 'propia';
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'lote_id');
    }
}
