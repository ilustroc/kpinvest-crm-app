<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcdCliente extends Model
{
    protected $table = 'ccd_clientes';

    protected $fillable = [
        'numdoc',
        'pdf',
        'cosecha',
        'link',
        'codigo',
    ];

    public $timestamps = false;
}

