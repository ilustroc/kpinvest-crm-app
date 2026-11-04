<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}

