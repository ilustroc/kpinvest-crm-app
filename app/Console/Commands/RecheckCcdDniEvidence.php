<?php

namespace App\Console\Commands;

use App\Models\CcdCliente;
use App\Services\Ccd\CcdVerifiedAssociationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RecheckCcdDniEvidence extends Command
{
    protected $signature = 'ccd:recheck-dni
        {--input=* : CSV privados previos, por nombre dentro de audits/}
        {--limit=100 : Máximo de CCD a volver a simular, entre 1 y 1000}
        {--after-id=0 : Procesa sólo CCD con ID mayor al indicado}
        {--output= : Nombre del CSV privado de comparación, sin ruta}';

    protected $description = 'Reevalúa sin escritura una muestra previamente marcada con DNI no corroborable';

    public function handle(CcdVerifiedAssociationService $associations): int
    {
        $options = $this->validatedOptions();

        if ($options === null) {
            return self::FAILURE;
        }

        $disk = Storage::disk((string) config('ccd.private_disk'));
        $ids = $this->reviewIds($disk, $options['inputs'], $options['after_id'], $options['limit']);

        if ($ids === null) {
            return self::FAILURE;
        }

        if ($ids === []) {
            $this->warn('Los CSV no contienen CCD pendientes por dni_documental_no_corrobable para los filtros indicados.');

            return self::SUCCESS;
        }

        $output = 'audits/'.$options['output'];

        if ($disk->exists($output)) {
            $this->error('El CSV privado de salida ya existe; no se sobrescribió.');

            return self::FAILURE;
        }

        $temporary = 'audits/.tmp-'.Str::uuid().'.csv';
        $path = $disk->path($temporary);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('No se pudo preparar el directorio privado de auditoría.');

            return self::FAILURE;
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            $this->error('No se pudo crear el CSV privado de comparación.');

            return self::FAILURE;
        }

        fputcsv($handle, [
            'ccd_id',
            'clasificacion_anterior',
            'motivo_anterior',
            'clasificacion_nueva',
            'resultado_comparacion',
            'motivo_nuevo',
            'evidencia_operacion',
            'evidencia_dni',
            'fuente',
        ]);

        $counts = [
            'ahora_elegible' => 0,
            'sigue_revision' => 0,
            'contradiccion' => 0,
            'requiere_validacion_manual' => 0,
            'error' => 0,
        ];
        $rows = CcdCliente::query()
            ->with('archivo:id,disk,path,sha256')
            ->whereIn('id', $ids)
            ->get(['id', 'numdoc', 'pdf', 'cosecha', 'ccd_archivo_id'])
            ->keyBy('id');

        foreach ($ids as $id) {
            $ccd = $rows->get($id);

            if ($ccd === null) {
                $result = $this->technicalResult('ccd_no_encontrada');
            } else {
                try {
                    $result = $associations->analyze($ccd);
                } catch (Throwable $exception) {
                    Log::error('CCD DNI recheck failed.', [
                        'exception_class' => $exception::class,
                        'ccd_id' => $id,
                    ]);
                    $result = $this->technicalResult('procesamiento_tecnico');
                }
            }

            $comparison = $this->comparison($result);
            $counts[$comparison]++;
            fputcsv($handle, array_map([$this, 'safeCsv'], [
                $id,
                'revision',
                'dni_documental_no_corrobable',
                $result['status'],
                $comparison,
                $result['reason'],
                $result['evidence'],
                $result['dni_evidence'],
                $result['source'],
            ]));
        }

        fclose($handle);

        if (! $disk->move($temporary, $output)) {
            $disk->delete($temporary);
            $this->error('No se pudo publicar el CSV privado de comparación.');

            return self::FAILURE;
        }

        $this->table(['Resultado', 'Cantidad'], [
            ['Muestra procesada', count($ids)],
            ['Ahora elegibles', $counts['ahora_elegible']],
            ['Siguen en revisión', $counts['sigue_revision']],
            ['Contradicciones', $counts['contradiccion']],
            ['Validación manual', $counts['requiere_validacion_manual']],
            ['Errores', $counts['error']],
        ]);
        $this->info("CSV privado generado: {$output}");
        $this->comment('Simulación completada: no se insertó, actualizó ni eliminó ninguna asociación.');

        return self::SUCCESS;
    }

    /** @return array{inputs:array<int,string>,limit:int,after_id:int,output:string}|null */
    private function validatedOptions(): ?array
    {
        $inputs = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) $this->option('input'),
        ))));
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        $afterId = filter_var($this->option('after-id'), FILTER_VALIDATE_INT);

        if ($inputs === []) {
            $this->error('Debe indicar al menos un CSV privado mediante --input=archivo.csv.');

            return null;
        }

        foreach ($inputs as $input) {
            if ($input !== basename($input) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/D', $input)) {
                $this->error('Cada --input debe ser un nombre CSV simple dentro de audits/.');

                return null;
            }
        }

        if ($limit === false || $limit < 1 || $limit > 1000 || $afterId === false || $afterId < 0) {
            $this->error('--limit debe estar entre 1 y 1000 y --after-id debe ser un entero no negativo.');

            return null;
        }

        $requestedOutput = trim((string) $this->option('output'));
        $output = $requestedOutput !== ''
            ? $requestedOutput
            : 'ccd-recheck-dni-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.csv';

        if ($output !== basename($output) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/D', $output)) {
            $this->error('--output debe ser un nombre CSV simple, sin directorios.');

            return null;
        }

        return [
            'inputs' => $inputs,
            'limit' => $limit,
            'after_id' => $afterId,
            'output' => $output,
        ];
    }

    /** @param mixed $disk @param array<int,string> $inputs @return array<int,int>|null */
    private function reviewIds(mixed $disk, array $inputs, int $afterId, int $limit): ?array
    {
        $ids = [];

        foreach ($inputs as $input) {
            $path = 'audits/'.$input;

            if (! $disk->exists($path)) {
                $this->error("No existe el CSV privado {$path}.");

                return null;
            }

            $stream = $disk->readStream($path);

            if (! is_resource($stream)) {
                $this->error("No se pudo leer el CSV privado {$path}.");

                return null;
            }

            try {
                $header = fgetcsv($stream);

                if (! is_array($header)) {
                    $this->error("El CSV {$path} no contiene encabezados válidos.");

                    return null;
                }

                $columns = array_flip($header);

                foreach (['ccd_id', 'resultado', 'motivo'] as $required) {
                    if (! isset($columns[$required])) {
                        $this->error("El CSV {$path} no contiene la columna {$required}.");

                        return null;
                    }
                }

                while (($row = fgetcsv($stream)) !== false) {
                    $id = filter_var($row[$columns['ccd_id']] ?? null, FILTER_VALIDATE_INT);

                    if ($id === false || $id <= $afterId
                        || ($row[$columns['resultado']] ?? null) !== 'revision'
                        || ($row[$columns['motivo']] ?? null) !== 'dni_documental_no_corrobable') {
                        continue;
                    }

                    $ids[(int) $id] = (int) $id;

                    if (count($ids) >= $limit) {
                        break 2;
                    }
                }
            } finally {
                fclose($stream);
            }
        }

        ksort($ids);

        return array_values($ids);
    }

    /** @param array{status:string,evidence:string,dni_evidence:string,reason:string,source:string} $result */
    private function comparison(array $result): string
    {
        if ($result['status'] === 'elegible') {
            return 'ahora_elegible';
        }

        if ($result['status'] === 'conflicto'
            || in_array($result['dni_evidence'], ['dni_documental_no_coincide', 'dni_documental_contradictorio'], true)) {
            return 'contradiccion';
        }

        if ($result['status'] === 'revision') {
            return 'sigue_revision';
        }

        if ($result['status'] === 'error') {
            return 'error';
        }

        return 'requiere_validacion_manual';
    }

    /** @return array{status:string,source:string,account_id:null,operacion:null,evidence:string,dni_evidence:string,method:string,reason:string} */
    private function technicalResult(string $reason): array
    {
        return [
            'status' => 'error',
            'source' => 'no_disponible',
            'account_id' => null,
            'operacion' => null,
            'evidence' => 'procesamiento_tecnico',
            'dni_evidence' => 'dni_no_evaluable_error_extraccion',
            'method' => 'operacion_pdf_automatica',
            'reason' => $reason,
        ];
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
