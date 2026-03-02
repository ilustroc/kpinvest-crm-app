<?php

namespace App\Services\Clientes;

use Illuminate\Support\Facades\DB;

class ClienteSearchService
{
    private function base()
    {
        return DB::table('clientes_cuentas');
    }

    private function like(string $q): string
    {
        $safe = str_replace(['\\','%','_'], ['\\\\','\%','\_'], $q);
        return "%{$safe}%";
    }

    public function findDniByExactDni(string $dni): ?string
    {
        return $this->base()->where('numdoc', $dni)->value('numdoc') ?: null;
    }

    public function findDniByExactOperacion(string $op): ?string
    {
        return $this->base()->where('operacion', $op)->value('numdoc') ?: null;
    }

    /** devuelve lista de DNIs candidatos (máx $limit) */
    public function candidateDnies(string $q, int $limit = 20)
    {
        $like = $this->like($q);

        return $this->base()
            ->selectRaw('numdoc, MAX(updated_at) as u')
            ->where(function ($w) use ($like) {
                $w->where('numdoc', 'like', $like)
                  ->orWhere('nombre', 'like', $like);
            })
            ->groupBy('numdoc')
            ->orderByDesc('u')
            ->limit($limit)
            ->pluck('numdoc');
    }

    /** lista de filas por DNIs */
    public function rowsByDnies($dnies)
    {
        return $this->base()
            ->whereIn('numdoc', $dnies)
            ->orderByDesc('updated_at')
            ->get(['numdoc as dni', 'nombre', 'cosecha', 'updated_at'])
            ->unique('dni')
            ->values();
    }

    /** filas para autocomplete (máx $limit) */
    public function suggestRows(string $q, int $limit = 5)
    {
        $dnies = $this->candidateDnies($q, $limit);

        if ($dnies->isEmpty()) return collect();

        return $this->base()
            ->whereIn('numdoc', $dnies)
            ->orderByDesc('updated_at')
            ->get(['numdoc as dni', 'nombre', 'operacion', 'cosecha'])
            ->unique('dni')
            ->values();
    }
}