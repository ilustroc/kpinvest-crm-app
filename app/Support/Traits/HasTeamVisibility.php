<?php

namespace App\Support\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasTeamVisibility
{
    /** Devuelve los IDs del usuario + su equipo (vacío = sin filtro). */
    protected function teamUserIds(?User $me = null): array
    {
        $me = $me ?: auth()->user();
        if (!$me) return [];

        $role = strtolower((string) $me->role);

        if ($role === 'administrador') {
            return []; // ver todo
        }
        if ($role === 'supervisor') {
            $ids = User::where('supervisor_id', $me->id)->pluck('id')->all();
            $ids[] = $me->id;
            return $ids;
        }
        return [$me->id]; // asesor/soporte: solo lo suyo
    }

    /** Aplica el filtro de equipo a un Builder (si corresponde). */
    protected function applyTeamFilter(Builder $q, ?User $me = null, string $userColumn = 'user_id'): Builder
    {
        $ids = $this->teamUserIds($me);
        if (!empty($ids)) $q->whereIn($userColumn, $ids);
        return $q;
    }
}
