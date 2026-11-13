<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CcdClienteCuenta extends Model
{
    public const ESTADO_CANDIDATA = 'candidata';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_EN_REVISION = 'en_revision';

    protected $table = 'ccd_cliente_cuenta';

    protected $fillable = [
        'ccd_cliente_id',
        'cliente_cuenta_id',
        'estado',
        'metodo_verificacion',
        'evidencia',
        'confirmado_por_user_id',
        'confirmado_at',
    ];

    protected $casts = [
        'confirmado_at' => 'datetime',
    ];

    public function ccdCliente(): BelongsTo
    {
        return $this->belongsTo(CcdCliente::class, 'ccd_cliente_id');
    }

    public function clienteCuenta(): BelongsTo
    {
        return $this->belongsTo(ClienteCuenta::class, 'cliente_cuenta_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por_user_id');
    }
}
