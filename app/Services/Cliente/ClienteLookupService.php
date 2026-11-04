<?php

namespace App\Services\Cliente;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClienteLookupService
{
    public function quickLookup(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'type' => 'error',
                'message' => 'Ingresa DNI, Operacion o Nombre.',
            ];
        }

        $base = DB::table('clientes_cuentas');

        if (preg_match('/^[A-Za-z0-9\-]{5,}$/', $query)) {
            $numdoc = (clone $base)->where('numdoc', $query)->value('numdoc');
            if ($numdoc) {
                return ['type' => 'redirect', 'numdoc' => $numdoc, 'dni' => $numdoc];
            }

            $numdocByOperation = (clone $base)->where('operacion', $query)->value('numdoc');
            if ($numdocByOperation) {
                return ['type' => 'redirect', 'numdoc' => $numdocByOperation, 'dni' => $numdocByOperation];
            }
        }

        $candidates = $this->candidateDnis($query, 20);

        if ($candidates->isEmpty()) {
            return [
                'type' => 'error',
                'message' => 'Cliente no ubicado.',
            ];
        }

        if ($candidates->count() === 1) {
            $first = $candidates->first();
            return [
                'type' => 'redirect',
                'numdoc' => $first,
                'dni' => $first,
            ];
        }

        return [
            'type' => 'error',
            'message' => 'Hay mas de un cliente. Usa el autocompletado o ingresa DNI/operacion exacta.',
        ];
    }

    public function suggest(string $query): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        return $this->candidateRows($this->candidateDnis($query, 8))
            ->map(fn ($row) => [
                'numdoc' => $row->numdoc ?? $row->dni,
                'dni' => $row->numdoc ?? $row->dni,
                'nombre' => $row->nombre,
                'operacion' => $row->operacion,
                'cosecha' => $row->cosecha,
                'url' => route('clientes.show', $row->numdoc ?? $row->dni),
            ]);
    }

    private function candidateDnis(string $query, int $limit): Collection
    {
        return DB::table('clientes_cuentas')
            ->selectRaw('numdoc, MAX(updated_at) as u')
            ->where(function ($where) use ($query) {
                $where->where('numdoc', 'like', "%{$query}%")
                    ->orWhere('operacion', 'like', "%{$query}%")
                    ->orWhere('nombre', 'like', "%{$query}%");
            })
            ->groupBy('numdoc')
            ->orderByDesc('u')
            ->limit($limit)
            ->pluck('numdoc');
    }

    private function candidateRows(Collection $dnis): Collection
    {
        return DB::table('clientes_cuentas')
            ->whereIn('numdoc', $dnis)
            ->orderByDesc('updated_at')
            ->get(['numdoc', 'numdoc as dni', 'nombre', 'operacion', 'cosecha', 'updated_at'])
            ->unique('dni')
            ->values();
    }
}
