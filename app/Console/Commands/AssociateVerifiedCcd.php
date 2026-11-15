<?php

namespace App\Console\Commands;

use App\Models\CcdCliente;
use App\Services\Ccd\CcdAutoAssociationApplyGuard;
use App\Services\Ccd\CcdVerifiedAssociationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AssociateVerifiedCcd extends Command
{
    private const APPLY_AUTHORIZATION = 'CONFIRMAR_ASOCIACION_AUTOMATICA';

    protected $signature = 'ccd:associate-verified
        {--ccd-id= : ID exacto de ccd_clientes a procesar}
        {--dni= : Limita el lote a un documento}
        {--after-id=0 : Reanuda desde el ID de ccd_clientes indicado}
        {--limit=100 : Máximo de CCD por lote, entre 1 y 100}
        {--apply : Persiste únicamente confirmaciones verificadas de nuevo}
        {--authorization= : Requerido junto con --apply}
        {--output= : Nombre del CSV privado, sin ruta}';

    protected $description = 'Simula o aplica asociaciones CCD verificadas de nuevo contra el PDF';

    public function handle(CcdVerifiedAssociationService $associations, CcdAutoAssociationApplyGuard $applyGuard): int
    {
        $options = $this->validatedOptions();

        if ($options === null) {
            return self::FAILURE;
        }

        if ($options['apply'] && ($reason = $applyGuard->rejectionReason(DB::connection())) !== null) {
            $this->error($reason);

            return self::FAILURE;
        }

        $ccdRows = $this->query($options)->get();

        if ($ccdRows->isEmpty()) {
            $this->warn('No se encontraron CCD para los filtros indicados. No se modificó ninguna asociación.');

            return self::SUCCESS;
        }

        $disk = Storage::disk((string) config('ccd.private_disk'));
        $output = 'audits/'.$options['output'];

        if ($disk->exists($output)) {
            $this->error('El CSV privado indicado ya existe; elija otro nombre para no sobrescribirlo.');

            return self::FAILURE;
        }

        $temporary = 'audits/.tmp-'.Str::uuid().'.csv';
        $path = $disk->path($temporary);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('No se pudo preparar el directorio privado para el CSV de auditoría.');

            return self::FAILURE;
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            $this->error('No se pudo crear el CSV privado de asociación verificada.');

            return self::FAILURE;
        }

        $counts = [];

        try {
            fputcsv($handle, [
                'ccd_id',
                'cliente_cuenta_id',
                'operacion',
                'fuente',
                'resultado',
                'motivo',
                'evidencia',
                'metodo_verificacion',
                'modo',
            ]);

            foreach ($ccdRows as $ccd) {
                try {
                    $result = $options['apply'] ? $associations->apply($ccd) : $associations->analyze($ccd);
                } catch (Throwable $exception) {
                    Log::error('CCD verified association failed.', ['exception_class' => $exception::class]);
                    $result = [
                        'status' => 'error',
                        'source' => 'no_disponible',
                        'account_id' => null,
                        'operacion' => null,
                        'evidence' => 'procesamiento_tecnico',
                        'method' => 'operacion_pdf_automatica',
                        'reason' => 'procesamiento_tecnico',
                    ];
                }

                $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
                fputcsv($handle, array_map([$this, 'safeCsv'], [
                    $ccd->id,
                    $result['account_id'],
                    $result['operacion'],
                    $result['source'],
                    $result['status'],
                    $result['reason'],
                    $result['evidence'],
                    $result['method'],
                    $options['apply'] ? 'aplicacion' : 'simulacion',
                ]));
            }
        } catch (Throwable $exception) {
            Log::error('CCD verified association CSV failed.', ['exception_class' => $exception::class]);
            fclose($handle);
            $disk->delete($temporary);
            $this->error('No se pudo completar el CSV privado de asociación verificada.');

            return self::FAILURE;
        }

        fclose($handle);
        $disk->move($temporary, $output);

        $details = [['Procesados', array_sum($counts)]];
        foreach (['elegible', 'confirmada', 'ya_confirmada', 'conflicto', 'revision', 'no_elegible', 'error'] as $status) {
            $details[] = [str_replace('_', ' ', ucfirst($status)), $counts[$status] ?? 0];
        }

        $this->table(['Resultado', 'Cantidad'], $details);
        $this->info("CSV privado generado: {$output}");
        $this->comment($options['apply']
            ? 'Aplicación completada: cada confirmación fue releída y verificada dentro de transacción.'
            : 'Simulación completada: no se insertó, actualizó ni eliminó ninguna asociación.');

        return self::SUCCESS;
    }

    /** @param array{ccd_id:?int,dni:?string,after_id:int,limit:int,apply:bool,output:string} $options */
    private function query(array $options)
    {
        $query = CcdCliente::query()
            ->orderBy('id')
            ->select(['id', 'numdoc', 'pdf', 'cosecha', 'ccd_archivo_id']);

        if ($options['ccd_id'] !== null) {
            $query->whereKey($options['ccd_id']);
        } else {
            $query->where('id', '>', $options['after_id']);
        }

        if ($options['dni'] !== null) {
            $query->where('numdoc', $options['dni']);
        }

        return $query->limit($options['limit']);
    }

    /** @return array{ccd_id:?int,dni:?string,after_id:int,limit:int,apply:bool,output:string}|null */
    private function validatedOptions(): ?array
    {
        $ccdId = $this->positiveIntegerOption('ccd-id');
        $afterId = filter_var($this->option('after-id'), FILTER_VALIDATE_INT);
        $limit = $this->positiveIntegerOption('limit');
        $apply = (bool) $this->option('apply');
        $requestedOutput = trim((string) $this->option('output'));

        if ($afterId === false || $afterId < 0 || $limit === null || $limit > 100) {
            $this->error('--after-id debe ser un entero no negativo y --limit debe estar entre 1 y 100.');

            return null;
        }

        if ($this->option('ccd-id') !== null && $ccdId === null) {
            $this->error('--ccd-id debe ser un entero positivo.');

            return null;
        }

        if ($apply && ! hash_equals(self::APPLY_AUTHORIZATION, trim((string) $this->option('authorization')))) {
            $this->error('--apply exige --authorization='.self::APPLY_AUTHORIZATION.'.');

            return null;
        }

        if (! $apply && $this->option('authorization') !== null) {
            $this->error('--authorization sólo se usa junto con --apply.');

            return null;
        }

        if ($requestedOutput !== '' && $requestedOutput !== basename($requestedOutput)) {
            $this->error('--output debe ser un nombre de archivo CSV simple, sin directorios.');

            return null;
        }

        $output = $requestedOutput === ''
            ? 'ccd-associate-verified-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.csv'
            : $requestedOutput;

        if (! str_ends_with(strtolower($output), '.csv') || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.csv$/', $output)) {
            $this->error('--output debe ser un nombre de archivo CSV simple, sin directorios.');

            return null;
        }

        return [
            'ccd_id' => $ccdId,
            'dni' => trim((string) $this->option('dni')) ?: null,
            'after_id' => $afterId,
            'limit' => $limit,
            'apply' => $apply,
            'output' => $output,
        ];
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $raw = $this->option($name);

        if ($raw === null || $raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);

        return $value !== false && $value > 0 ? $value : null;
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
