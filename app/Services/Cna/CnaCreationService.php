<?php

namespace App\Services\Cna;

use App\Models\CnaSolicitud;
use App\Models\User;
use App\Support\Authorization\Roles;
use App\Support\WorkflowMailer;
use DomainException;
use Illuminate\Support\Facades\DB;

class CnaCreationService
{
    public function __construct(
        private readonly CnaNumberingService $numbering,
        private readonly CnaDocumentService $documents,
    ) {
    }

    public function createFromData(string $dni, array $data, User $user): array
    {
        $operations = array_values(array_filter(array_map('strval', $data['operaciones'] ?? [])));

        if ($operations === []) {
            throw new DomainException('Selecciona al menos una operacion para la CNA.');
        }

        $titular = $data['titular'] ?? DB::table('clientes_cuentas')
            ->where('numdoc', $dni)
            ->value('nombre');

        $accountRows = DB::table('clientes_cuentas')
            ->select('operacion', 'cosecha', 'entidad', 'producto')
            ->where('numdoc', $dni)
            ->whereIn('operacion', $operations)
            ->get();

        $foundOperations = $accountRows->pluck('operacion')->map('strval')->values();
        $missing = collect($operations)->diff($foundOperations);

        if ($missing->isNotEmpty()) {
            throw new DomainException(
                'Las operaciones ('.implode(', ', $missing->all()).') no pertenecen al DNI '.$dni.'.'
            );
        }

        $product = $accountRows->pluck('producto')->filter()->unique()->implode(' / ') ?: null;
        $origin = $this->numbering->originFromAccountRows($accountRows);

        if (! $origin) {
            throw new DomainException('No se pudo determinar el origen para numeracion (cosecha/entidad).');
        }

        ['serie' => $serie, 'suffix' => $suffix] = $this->numbering->seriesConfig($origin);

        $role = Roles::normalize($user->role);
        $isAdminAuto = $role === Roles::ADMINISTRADOR;
        $isSupervisorAuto = $role === Roles::SUPERVISOR;
        $now = now();

        $solicitud = DB::transaction(function () use (
            $dni,
            $data,
            $operations,
            $titular,
            $product,
            $serie,
            $suffix,
            $isAdminAuto,
            $isSupervisorAuto,
            $now,
            $user,
        ) {
            $next = $this->numbering->nextCartaForSerie($serie, $suffix);

            $payload = [
                'correlativo' => $next['corr'],
                'nro_carta' => $next['nro'],
                'dni' => $dni,
                'titular' => $titular,
                'producto' => $product,
                'operaciones' => $operations,
                'nota' => $data['nota'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'fecha_pago_realizado' => $data['fecha_pago_realizado'],
                'monto_pagado' => $data['monto_pagado'],
                'user_id' => $user->id,
            ];

            if ($isAdminAuto) {
                $payload['workflow_estado'] = 'aprobada';
                $payload['pre_aprobado_por'] = $user->id;
                $payload['pre_aprobado_at'] = $now;
                $payload['aprobado_por'] = $user->id;
                $payload['aprobado_at'] = $now;
            } elseif ($isSupervisorAuto) {
                $payload['workflow_estado'] = 'preaprobada';
                $payload['pre_aprobado_por'] = $user->id;
                $payload['pre_aprobado_at'] = $now;
            } else {
                $payload['workflow_estado'] = 'pendiente';
            }

            return CnaSolicitud::create($payload);
        });

        if ($isAdminAuto) {
            $this->documents->generateOutputsFromTemplate($solicitud);
            WorkflowMailer::cnaResuelta($solicitud, true);

            return [$solicitud, "CNA APROBADA automaticamente. N. {$solicitud->nro_carta}"];
        }

        if ($isSupervisorAuto) {
            WorkflowMailer::cnaPreaprobada($solicitud);

            return [$solicitud, "Solicitud de CNA PRE-APROBADA. N. {$solicitud->nro_carta}"];
        }

        WorkflowMailer::cnaPendiente($solicitud);

        return [$solicitud, "Solicitud de CNA enviada. N. {$solicitud->nro_carta}"];
    }
}
