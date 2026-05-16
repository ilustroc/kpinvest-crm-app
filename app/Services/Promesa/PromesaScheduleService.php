<?php

namespace App\Services\Promesa;

use App\Http\Requests\StorePromesaRequest;
use Carbon\Carbon;
use RuntimeException;

class PromesaScheduleService
{
    public function selectedOperations(StorePromesaRequest $request): array
    {
        return collect($request->input('operaciones', []))
            ->map(fn ($operation) => trim((string) $operation))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function normalizedDates(StorePromesaRequest $request): array
    {
        return array_map(
            fn ($date) => Carbon::parse($date)->toDateString(),
            (array) $request->input('cron_fecha', []),
        );
    }

    public function normalizedAmounts(StorePromesaRequest $request): array
    {
        return array_map(
            fn ($amount) => (float) $amount,
            (array) $request->input('cron_monto', []),
        );
    }

    public function assertConventionSchedule(StorePromesaRequest $request, array $amounts, array $dates): void
    {
        $type = (string) $request->input('tipo');

        if (! in_array($type, ['convenio', 'convenio_balon'], true)) {
            return;
        }

        $agreementAmount = round((float) $request->input('monto_convenio', 0), 2);
        $scheduleSum = round(array_sum($amounts), 2);

        if (abs($scheduleSum - $agreementAmount) > 0.01) {
            throw new RuntimeException('El Monto convenio no coincide con la suma del cronograma.');
        }

        $quotaCount = max(1, (int) $request->input('nro_cuotas'));

        if (count($amounts) !== $quotaCount || count($dates) !== $quotaCount) {
            throw new RuntimeException('El cronograma debe tener la misma cantidad de cuotas que "Nro cuotas".');
        }
    }

    public function quotaRows(StorePromesaRequest $request, array $dates, array $amounts): array
    {
        $type = (string) $request->input('tipo');
        $balloonQuota = (int) $request->input('cron_balon', 0);
        $now = now();

        $rows = [];

        foreach ($dates as $index => $date) {
            $number = $index + 1;

            $rows[] = [
                'nro' => $number,
                'fecha' => Carbon::parse($date)->toDateString(),
                'monto' => (float) ($amounts[$index] ?? 0),
                'es_balon' => ($type === 'convenio_balon' && $balloonQuota === $number) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
