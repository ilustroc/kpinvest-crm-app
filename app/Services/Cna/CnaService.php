<?php

namespace App\Services\Cna;

use App\Models\CnaSolicitud;
use App\Support\WorkflowMailer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CnaService
{
    public function create(string $dni, array $data): CnaSolicitud
    {
        $ops = array_values(array_filter(array_map('strval', $data['operaciones'] ?? [])));
        if (!$ops) throw new \RuntimeException('Selecciona al menos una operación para la CNA.');

        $titular = $data['titular'] ?? DB::table('clientes_cuentas')
            ->where('numdoc', $dni)
            ->value('nombre');

        $rowsOps = DB::table('clientes_cuentas')
            ->select('operacion','cosecha','entidad','producto')
            ->where('numdoc', $dni)
            ->whereIn('operacion', $ops)
            ->get();

        $opsEncontradas = $rowsOps->pluck('operacion')->map('strval')->values();
        $missing = collect($ops)->diff($opsEncontradas);
        if ($missing->isNotEmpty()) {
            throw new \RuntimeException(
                'Las operaciones ('.implode(', ', $missing->all()).') no pertenecen al DNI '.$dni.'.'
            );
        }

        $productoAuto = $rowsOps->pluck('producto')->filter()->unique()->implode(' / ') ?: null;

        $cosechaRef = $rowsOps->pluck('cosecha')->filter()->countBy()->sortDesc()->keys()->first();
        $cosechaRef = $cosechaRef ? (string)$cosechaRef : null;

        // origen
        $origen = $cosechaRef ? $this->originFromCosecha($cosechaRef) : null;
        if (!$origen) {
            $entRef = strtoupper((string)($rowsOps->pluck('entidad')->filter()->first() ?? ''));
            if (str_contains($entRef, 'COMPARTAMOS'))      $origen = 'COMPARTAMOS_1';
            elseif (str_contains($entRef, 'CONFIANZA'))    $origen = 'CONFIANZA_1';
            elseif (str_contains($entRef, 'AREQUIPA'))     $origen = 'AQP1';
            elseif (str_contains($entRef, 'BBVA'))         $origen = 'BBVA_1_2';
        }
        if (!$origen) throw new \RuntimeException('No se pudo determinar el origen para numeración (cosecha/entidad).');

        ['serie' => $serie, 'suffix' => $suffix] = $this->seriesConfig($origen);

        $role = strtolower((string)(Auth::user()->role ?? ''));
        $isAdminAuto = in_array($role, ['administrador','sistemas'], true);
        $now = now();

        return DB::transaction(function () use ($dni, $data, $ops, $titular, $productoAuto, $serie, $suffix, $isAdminAuto, $now) {
            DB::table('cna_solicitudes')->lockForUpdate()->get();
            $next = $this->nextCartaForSerie($serie, $suffix);

            $payload = [
                'correlativo'          => $next['corr'],
                'nro_carta'            => $next['nro'],
                'dni'                  => $dni,
                'titular'              => $titular,
                'producto'             => $productoAuto,
                'operaciones'          => $ops,
                'nota'                 => $data['nota'] ?? null,
                'observacion'          => $data['observacion'] ?? null,
                'fecha_pago_realizado' => $data['fecha_pago_realizado'],
                'monto_pagado'         => $data['monto_pagado'],
                'user_id'              => Auth::id(),
            ];

            if ($isAdminAuto) {
                $payload['workflow_estado']  = 'aprobada';
                $payload['pre_aprobado_por'] = Auth::id();
                $payload['pre_aprobado_at']  = $now;
                $payload['aprobado_por']     = Auth::id();
                $payload['aprobado_at']      = $now;
            } else {
                $payload['workflow_estado']  = 'pendiente';
            }

            return CnaSolicitud::create($payload);
        });
    }

    public function isAdminAuto(): bool
    {
        $role = strtolower((string)(Auth::user()->role ?? ''));
        return in_array($role, ['administrador','sistemas'], true);
    }

    public function notifyPendiente(CnaSolicitud $cna): void
    {
        WorkflowMailer::cnaPendiente($cna);
    }

    // ========= workflow =========
    public function preaprobar(CnaSolicitud $cna, ?string $nota): void
    {
        if ($cna->workflow_estado !== 'pendiente') {
            throw new \RuntimeException('Solo se puede pre-aprobar una solicitud pendiente.');
        }

        $this->appendNota($cna, $nota);

        $cna->update([
            'workflow_estado' => 'preaprobada',
            'pre_aprobado_por'=> auth()->id(),
            'pre_aprobado_at' => now(),
            'rechazado_por'   => null,
            'rechazado_at'    => null,
            'motivo_rechazo'  => null,
        ]);

        WorkflowMailer::cnaPreaprobada($cna);
    }

    public function rechazarSup(CnaSolicitud $cna, ?string $nota): void
    {
        if ($cna->workflow_estado !== 'pendiente') {
            throw new \RuntimeException('Solo se puede rechazar una solicitud pendiente.');
        }

        $nota = mb_substr((string)$nota, 0, 500);

        $cna->update([
            'workflow_estado' => 'rechazada_sup',
            'rechazado_por'   => Auth::id(),
            'rechazado_at'    => now(),
            'motivo_rechazo'  => $nota,
        ]);

        WorkflowMailer::cnaRechazadaSup($cna, $nota);
    }

    public function aprobar(CnaSolicitud $cna, ?string $nota): void
    {
        if ($cna->workflow_estado !== 'preaprobada') {
            throw new \RuntimeException('Solo se puede aprobar una CNA pre-aprobada.');
        }

        $this->appendNota($cna, $nota);

        $cna->update([
            'workflow_estado' => 'aprobada',
            'aprobado_por'    => auth()->id(),
            'aprobado_at'     => now(),
        ]);

        WorkflowMailer::cnaResuelta($cna, true, $nota);
    }

    public function rechazarAdmin(CnaSolicitud $cna, ?string $nota): void
    {
        if ($cna->workflow_estado !== 'preaprobada') {
            throw new \RuntimeException('Solo se puede rechazar una solicitud pre-aprobada.');
        }

        $nota = mb_substr((string)$nota, 0, 500);

        $cna->update([
            'workflow_estado' => 'rechazada',
            'rechazado_por'   => Auth::id(),
            'rechazado_at'    => now(),
            'motivo_rechazo'  => $nota,
        ]);

        WorkflowMailer::cnaResuelta($cna, false, $nota);
    }

    // ========= helpers (se mueven del controller) =========
    public function originFromCosecha(?string $cosecha): ?string
    {
        if (!$cosecha) return null;
        $c = strtoupper(trim($cosecha));

        $faa  = ['BBVA3','BBVA4','BBVA5','BBVA6','CAJAAQP3'];
        $faa2 = ['BBVA7','BBVA8','CONFIANZA_5'];
        $kpi  = [
            'BBVA1','BBVA2','CAJAAQP1','CAJAAQP2','CAJAAQP4','CAJAAQP5','COMPARTAMOS_1','COMPARTAMOS_2','CONFIANZA','CONFIANZA_2','CONFIANZA_3',
            'CONFIANZA_4','CONFIANZA_6','CONFIANZA_7','CONFIANZA_8','CONFIANZA_9','CONFIANZA_10',
            'CONFIANZA_11','CONFIANZA_12','SEMBRANDO',
        ];

        if (in_array($c, $faa, true))  return 'FONDO ACREENCIA AREQUIPA';
        if (in_array($c, $faa2, true)) return 'ACREENCIA II';
        if (in_array($c, $kpi, true))  return 'KP INVEST SAC';
        return null;
    }

    public function seriesConfig(string $origen): array
    {
        $o = strtoupper($origen);
        if (str_contains($o, 'ACREENCIA II')) {
            return ['serie'=>'F2','suffix'=>'F2','template'=>storage_path('app/templates/cna_fondo_acreencia_arequipa_2.docx')];
        }
        if (str_contains($o, 'FONDO ACREENCIA AREQUIPA') || str_contains($o,'FONDO ACREENCIAS AREQUIPA')) {
            return ['serie'=>'F', 'suffix'=>'F', 'template'=>storage_path('app/templates/cna_fondo_acreencia_arequipa.docx')];
        }
        return ['serie'=>'KPI','suffix'=>'', 'template'=>storage_path('app/templates/cna_kpinvest.docx')];
    }

    public function nextCartaForSerie(string $serie, string $suffix): array
    {
        DB::table('cna_solicitudes')->lockForUpdate()->get();

        $query = DB::table('cna_solicitudes')->selectRaw("MAX(CAST(LEFT(nro_carta,6) AS UNSIGNED)) as m");
        if ($suffix !== '') $query->where('nro_carta','like',"%{$suffix}");
        $max  = $query->value('m');

        $corr = (int)($max ?: 0) + 1;
        $nro  = str_pad((string)$corr, 6, '0', STR_PAD_LEFT) . $suffix;

        return ['corr'=>$corr,'nro'=>$nro];
    }

    public function appendNota(CnaSolicitud $cna, ?string $nota): void
    {
        $txt = trim((string)$nota);
        if ($txt !== '') {
            $prefix = '[' . now()->format('Y-m-d H:i') . '] ' . (auth()->user()->name ?? 'sistema') . ': ';
            $cna->nota = trim(($cna->nota ? $cna->nota . "\n" : '') . $prefix . $txt);
            $cna->save();
        }
    }
}