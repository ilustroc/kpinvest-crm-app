<?php

namespace App\Services;

use App\Models\AsignarCliente;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AssignmentReportService
{
    /**
     * Query paginable en SQL: relaciones resueltas por joins, sin cargar el
     * historial completo ni ejecutar consultas por fila.
     */
    public function filteredQuery(array $filters): Builder
    {
        $query = DB::table('asignar_clientes as ac')
            ->leftJoin('clientes_cuentas as cc', 'cc.id', '=', 'ac.cliente_cuenta_id')
            ->leftJoin('users as solicitante', 'solicitante.id', '=', 'ac.solicitante_user_id')
            ->leftJoin('users as destino', 'destino.id', '=', 'ac.asesor_destino_user_id')
            ->leftJoin('users as resolvedor', 'resolvedor.id', '=', 'ac.aprobado_por_user_id')
            ->selectRaw("ac.id, ac.fecha_solicitud, ac.numdoc, COALESCE(cc.nombre, 'Cliente registrado') as cliente, ac.operacion, COALESCE(solicitante.name, 'Sistema / Histórico') as solicitante, COALESCE(destino.name, ac.name, 'Sin asignar') as asesor_destino, ac.motivo, ac.origen, ac.estado, COALESCE(resolvedor.name, '') as resuelto_por, ac.fecha_aprobacion");

        foreach (['numdoc', 'operacion'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $query->where("ac.{$field}", 'like', "%{$value}%");
            }
        }

        if (($from = trim((string) ($filters['from'] ?? ''))) !== '') {
            $query->where('ac.fecha_solicitud', '>=', $from.' 00:00:00');
        }
        if (($to = trim((string) ($filters['to'] ?? ''))) !== '') {
            $query->where('ac.fecha_solicitud', '<=', $to.' 23:59:59');
        }
        if (($estado = trim((string) ($filters['estado'] ?? ''))) !== '' && in_array($estado, [AsignarCliente::ESTADO_PENDIENTE, AsignarCliente::ESTADO_APROBADA, AsignarCliente::ESTADO_RECHAZADA], true)) {
            $query->where('ac.estado', $estado);
        }
        if (($origen = trim((string) ($filters['origen'] ?? ''))) !== '' && in_array($origen, [AsignarCliente::ORIGEN_MANUAL, AsignarCliente::ORIGEN_CARGA_MASIVA], true)) {
            $query->where('ac.origen', $origen);
        }
        if (($asesor = trim((string) ($filters['asesor'] ?? ''))) !== '') {
            $query->where(function (Builder $nested) use ($asesor): void {
                $nested->where('solicitante.name', 'like', "%{$asesor}%")
                    ->orWhere('destino.name', 'like', "%{$asesor}%");
            });
        }

        return $query->orderByDesc('ac.fecha_solicitud')->orderByDesc('ac.id');
    }
}
