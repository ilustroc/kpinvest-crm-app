<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignarCliente extends Model
{
    protected $table = 'asignar_clientes';

    protected $fillable = [
        'numdoc',
        'operacion',
        'name',
    ];

    public $timestamps = false;
}

