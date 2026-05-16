<?php

namespace App\Services\Cna;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CnaNumberingService
{
    public function originFromAccountRows(Collection $rows): ?string
    {
        $cosechaRef = $rows->pluck('cosecha')->filter()->countBy()->sortDesc()->keys()->first();
        $origin = $cosechaRef ? $this->originFromCosecha((string) $cosechaRef) : null;

        if ($origin) {
            return $origin;
        }

        $entity = strtoupper((string) ($rows->pluck('entidad')->filter()->first() ?? ''));

        if (str_contains($entity, 'COMPARTAMOS')) {
            return 'COMPARTAMOS_1';
        }

        if (str_contains($entity, 'CONFIANZA')) {
            return 'CONFIANZA_1';
        }

        if (str_contains($entity, 'AREQUIPA')) {
            return 'AQP1';
        }

        if (str_contains($entity, 'BBVA')) {
            return 'BBVA_1_2';
        }

        return null;
    }

    public function originFromCosecha(?string $cosecha): ?string
    {
        if (! $cosecha) {
            return null;
        }

        $value = strtoupper(trim($cosecha));

        $fondoAcreenciaArequipa = ['BBVA3', 'BBVA4', 'BBVA5', 'BBVA6', 'CAJAAQP3'];
        $acreenciaII = ['BBVA7', 'BBVA8', 'CONFIANZA_5'];
        $kpInvest = [
            'BBVA1',
            'BBVA2',
            'CAJAAQP1',
            'CAJAAQP2',
            'CAJAAQP4',
            'CAJAAQP5',
            'COMPARTAMOS_1',
            'COMPARTAMOS_2',
            'CONFIANZA',
            'CONFIANZA_2',
            'CONFIANZA_3',
            'CONFIANZA_4',
            'CONFIANZA_6',
            'CONFIANZA_7',
            'CONFIANZA_8',
            'CONFIANZA_9',
            'CONFIANZA_10',
            'CONFIANZA_11',
            'CONFIANZA_12',
            'SEMBRANDO',
            'WANDOO_1',
        ];

        if (in_array($value, $fondoAcreenciaArequipa, true)) {
            return 'FONDO ACREENCIA AREQUIPA';
        }

        if (in_array($value, $acreenciaII, true)) {
            return 'ACREENCIA II';
        }

        if (in_array($value, $kpInvest, true)) {
            return 'KP INVEST SAC';
        }

        return null;
    }

    public function seriesConfig(string $origin): array
    {
        $origin = strtoupper($origin);

        if (str_contains($origin, 'ACREENCIA II')) {
            return [
                'serie' => 'F2',
                'suffix' => 'F2',
                'template' => storage_path('app/templates/cna_fondo_acreencia_arequipa_2.docx'),
            ];
        }

        if (str_contains($origin, 'FONDO ACREENCIA AREQUIPA') || str_contains($origin, 'FONDO ACREENCIAS AREQUIPA')) {
            return [
                'serie' => 'F',
                'suffix' => 'F',
                'template' => storage_path('app/templates/cna_fondo_acreencia_arequipa.docx'),
            ];
        }

        return [
            'serie' => 'KPI',
            'suffix' => '',
            'template' => storage_path('app/templates/cna_kpinvest.docx'),
        ];
    }

    public function nextCartaForSerie(string $serie, string $suffix): array
    {
        DB::table('cna_solicitudes')->lockForUpdate()->get();

        $query = DB::table('cna_solicitudes')
            ->selectRaw('MAX(CAST(LEFT(nro_carta,6) AS UNSIGNED)) as m');

        if ($suffix !== '') {
            $query->where('nro_carta', 'like', "%{$suffix}");
        }

        $max = $query->value('m');
        $correlative = (int) ($max ?: 0) + 1;
        $number = str_pad((string) $correlative, 6, '0', STR_PAD_LEFT).$suffix;

        return [
            'corr' => $correlative,
            'nro' => $number,
        ];
    }
}
