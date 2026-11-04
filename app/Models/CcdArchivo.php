<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CcdArchivo extends Model
{
    protected $table = 'ccd_archivos';

    protected $fillable = [
        'disk',
        'path',
        'sha256',
        'size_bytes',
        'mime_type',
        'nombre_original',
        'verified_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function clientes(): HasMany
    {
        return $this->hasMany(CcdCliente::class, 'ccd_archivo_id');
    }

    public static function pathForHash(string $sha256): string
    {
        $hash = strtolower($sha256);

        return sprintf('sha256/%s/%s/%s.pdf', substr($hash, 0, 2), substr($hash, 2, 2), $hash);
    }
}
