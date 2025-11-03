<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // ⛳️ SOLO UNA VEZ
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Opcional (si lo usas, puedes guardar el password en texto y se hashea solo)
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Scopes
    public function scopeSupervisores($q){ return $q->where('role','supervisor'); }
    public function scopeAsesores($q){ return $q->where('role','asesor'); }
    
}

