<?php

namespace App\Console\Commands;

use App\Services\Ccd\CcdAqpV3AuditReader;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SampleAuditedAqpCcd extends Command
{
    protected $signature = 'ccd:sample-aqp-audit
        {--input=* : CSV privados producidos por ccd:recheck-dni V3 dentro de audits/}
        {--size-per-cosecha=20 : Cantidad aleatoria por cada cosecha}
        {--expected-total=10224}
        {--expected-inputs=12}
        {--expected-aqp4=7024}
        {--expected-aqp5=3200}
        {--output= : Nombre del CSV privado de revisión humana}';

    protected $description = 'Genera una muestra AQP aleatoria y estratificada sin exponer DNI ni operaciones';

    public function handle(CcdAqpV3AuditReader $reader): int
    {
        $inputs = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) $this->option('input'),
        ))));
        $size = $this->integerOption('size-per-cosecha', 4, 400);
        $expectedTotal = $this->integerOption('expected-total', 1, 100_000);
        $expectedInputs = $this->integerOption('expected-inputs', 1, 100);
        $expectedAqp4 = $this->integerOption('expected-aqp4', 0, 100_000);
        $expectedAqp5 = $this->integerOption('expected-aqp5', 0, 100_000);
        $output = trim((string) $this->option('output')) ?: 'ccd-aqp-human-sample-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.csv';

        if ($inputs === [] || $size === null || $expectedTotal === null || $expectedInputs === null
            || $expectedAqp4 === null || $expectedAqp5 === null
            || $expectedAqp4 + $expectedAqp5 !== $expectedTotal
            || $output !== basename($output)
            || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/D', $output)) {
            $this->error('Las opciones de muestreo AQP son inválidas.');

            return self::FAILURE;
        }

        try {
            $audit = $reader->read($inputs);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($audit['total'] !== $expectedTotal || count($audit['reports']) !== $expectedInputs
            || $audit['counts']['AQP4'] !== $expectedAqp4 || $audit['counts']['AQP5'] !== $expectedAqp5) {
            $this->error('Los totales de los CSV no coinciden con la auditoría V3 esperada.');

            return self::FAILURE;
        }

        $sample = [];

        foreach (['AQP4', 'AQP5'] as $harvest) {
            $rows = array_values(array_filter(
                $audit['candidates'],
                static fn (array $candidate): bool => $candidate['cosecha'] === $harvest,
            ));
            $sample = array_merge($sample, $this->stratifiedSample($rows, $size));
        }

        usort($sample, static fn (array $left, array $right): int => [$left['cosecha'], $left['ccd_id']] <=> [$right['cosecha'], $right['ccd_id']]);
        $disk = Storage::disk((string) config('ccd.private_disk'));
        $path = 'audits/'.$output;

        if ($disk->exists($path)) {
            $this->error('El CSV privado de muestra ya existe; no se sobrescribió.');

            return self::FAILURE;
        }

        $temporary = 'audits/.tmp-'.Str::uuid().'.csv';
        $stream = fopen($disk->path($temporary), 'wb');

        if ($stream === false) {
            $this->error('No se pudo crear el CSV privado de muestra.');

            return self::FAILURE;
        }

        fputcsv($stream, ['ccd_id', 'cosecha', 'tramo_historico', 'auditoria_v3_sha256', 'identidad_revisada', 'operacion_revisada', 'resultado_manual', 'observacion']);

        foreach ($sample as $candidate) {
            fputcsv($stream, [
                $candidate['ccd_id'],
                $candidate['cosecha'],
                $candidate['stratum'],
                $candidate['source_sha256'],
                '',
                '',
                '',
                '',
            ]);
        }

        fclose($stream);

        if (! $disk->move($temporary, $path)) {
            $disk->delete($temporary);
            $this->error('No se pudo publicar el CSV privado de muestra.');

            return self::FAILURE;
        }

        $this->info("Muestra privada generada: {$path}");
        $this->comment('El CSV no contiene DNI, nombres ni operaciones; la revisión debe realizarse mediante las rutas privadas existentes.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int,array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string}>  $rows
     * @return array<int,array{ccd_id:int,cosecha:string,source_report:string,source_sha256:string,stratum:string}>
     */
    private function stratifiedSample(array $rows, int $size): array
    {
        $quarters = array_chunk($rows, (int) ceil(count($rows) / 4));
        $base = intdiv($size, 4);
        $remainder = $size % 4;
        $sample = [];

        foreach ($quarters as $index => $quarter) {
            $take = min(count($quarter), $base + ($index < $remainder ? 1 : 0));

            for ($selected = 0; $selected < $take; $selected++) {
                $key = random_int(0, count($quarter) - 1);
                $candidate = array_splice($quarter, $key, 1)[0];
                $candidate['stratum'] = 'Q'.($index + 1);
                $sample[] = $candidate;
            }
        }

        return $sample;
    }

    private function integerOption(string $name, int $minimum, int $maximum): ?int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT);

        return $value !== false && $value >= $minimum && $value <= $maximum ? $value : null;
    }
}
