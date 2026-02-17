<?php

namespace App\Services;

use App\Models\PagoPropia as Pago;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;

class PaymentReportService
{
    public function getFilteredQuery(array $filters): Builder
    {
        $qb = Pago::query();

        // Filtros de fecha
        if (!empty($filters['from']) && !empty($filters['to'])) {
            $qb->whereBetween('fecha', [$filters['from'], $filters['to']]);
        } elseif (!empty($filters['from'])) {
            $qb->where('fecha', '>=', $filters['from']);
        } elseif (!empty($filters['to'])) {
            $qb->where('fecha', '<=', $filters['to']);
        }

        // Filtros de selección múltiple (Gestor, Cosecha, Entidad)
        foreach (['gestor', 'cosecha', 'entidad'] as $key) {
            $selected = $this->normSel($filters[$key] ?? []);
            if (!empty($selected)) {
                $qb->whereIn($key, $selected);
            }
        }

        // Búsqueda de texto (Q)
        $q = trim($filters['q'] ?? '');
        if ($q !== '') {
            $qb->where(function ($qq) use ($q) {
                if (ctype_digit($q)) {
                    $qq->where('dni', $q)->orWhere('dni', 'like', "%{$q}%");
                } else {
                    $qq->where('nombre_cliente', 'like', "%{$q}%");
                }
            });
        }

        return $qb;
    }

    public function getFacets(array $filters): array
    {
        $key = 'rpt_pagos_facets_v3:' . md5(json_encode($filters));

        return Cache::remember($key, 120, function () use ($filters) {
            return [
                'gestores'  => $this->fetchFacet('gestor', $filters),
                'cosechas'  => $this->fetchFacet('cosecha', $filters),
                'entidades' => $this->fetchFacet('entidad', $filters),
            ];
        });
    }

    private function fetchFacet(string $field, array $filters): array
    {
        // Para cascada: ignoramos el filtro del propio campo que estamos listando
        $tempFilters = $filters;
        unset($tempFilters[$field]);

        return $this->getFilteredQuery($tempFilters)
            ->whereNotNull($field)
            ->where($field, '<>', '')
            ->select($field)
            ->distinct()
            ->orderBy($field)
            ->pluck($field)
            ->values()
            ->all();
    }

    private function normSel(array $arr): array
    {
        return array_values(array_filter(array_map('trim', $arr), fn($v) => $v !== ''));
    }
}