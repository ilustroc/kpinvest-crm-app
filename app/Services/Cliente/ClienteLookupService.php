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

        if (preg_match('/^\d{6,}$/', $query)) {
            $dni = (clone $base)->where('numdoc', $query)->value('numdoc');
            if ($dni) {
                return ['type' => 'redirect', 'dni' => $dni];
            }

            $dniByOperation = (clone $base)->where('operacion', $query)->value('numdoc');
            if ($dniByOperation) {
                return ['type' => 'redirect', 'dni' => $dniByOperation];
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
            return [
                'type' => 'redirect',
                'dni' => $candidates->first(),
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
                'dni' => $row->dni,
                'nombre' => $row->nombre,
                'operacion' => $row->operacion,
                'cosecha' => $row->cosecha,
                'url' => route('clientes.show', $row->dni),
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
            ->get(['numdoc as dni', 'nombre', 'operacion', 'cosecha', 'updated_at'])
            ->unique('dni')
            ->values();
    }
}
