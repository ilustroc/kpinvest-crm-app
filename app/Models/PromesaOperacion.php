<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromesaOperacion extends Model
{
    protected $table = 'promesa_operaciones';

    protected $fillable = [
        'promesa_id',
        'operacion',
    ];

    /* ============================
     * Relaciones
     * ============================ */
    public function promesa()
    {
        return $this->belongsTo(PromesaPago::class, 'promesa_id');
    }
}
