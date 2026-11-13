<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CcdCliente extends Model
{
    protected $table = 'ccd_clientes';

    protected $fillable = [
        'numdoc',
        'pdf',
        'cosecha',
        'link',
        'codigo',
        'ccd_archivo_id',
    ];

    public $timestamps = false;

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(CcdArchivo::class, 'ccd_archivo_id');
    }

    public function cuentaLinks(): HasMany
    {
        return $this->hasMany(CcdClienteCuenta::class, 'ccd_cliente_id');
    }
}

