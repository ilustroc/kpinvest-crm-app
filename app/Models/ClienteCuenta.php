<?php
// app/Models/ClienteCuenta.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClienteCuenta extends Model
{
    protected $table = 'clientes_cuentas';
    protected $guarded = [];

    public function ccdLinks(): HasMany
    {
        return $this->hasMany(CcdClienteCuenta::class, 'cliente_cuenta_id');
    }
}
