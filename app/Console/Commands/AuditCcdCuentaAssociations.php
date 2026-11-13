<?php

namespace App\Console\Commands;

use App\Models\CcdCliente;
use App\Models\User;
use App\Services\Ccd\CcdCuentaAssociationService;
use Illuminate\Console\Command;
use Throwable;

class AuditCcdCuentaAssociations extends Command
{
    protected $signature = 'ccd:associate-audit
        {--ccd-id= : ID exacto de ccd_clientes a auditar}
        {--dni= : Limita la auditoría a un documento}
        {--after-id=0 : Reanuda desde el ID de ccd_clientes indicado}
        {--limit=25 : Máximo de CCD a procesar, entre 1 y 100}
        {--apply : Persiste sólo asociaciones candidatas o confirmadas del lote}
        {--confirm-operation= : Operación verificada manualmente en el PDF}
        {--evidence= : Evidencia de la verificación manual del PDF}
        {--confirmed-by= : ID del usuario que confirmó la operación}';

    protected $description = 'Audita asociaciones CCD-cuenta por DNI/cosecha, con confirmación manual opcional y modo simulación por defecto';

    public function handle(CcdCuentaAssociationService $associations): int
    {
        $options = $this->validatedOptions();

        if ($options === null) {
            return self::FAILURE;
        }

        $query = CcdCliente::query()
            ->orderBy('id')
            ->select(['id', 'numdoc', 'pdf', 'cosecha']);

        if ($options['ccd_id'] !== null) {
            $query->whereKey($options['ccd_id']);
        } else {
            $query->where('id', '>', $options['after_id']);
        }

        if ($options['dni'] !== null) {
            $query->where('numdoc', $options['dni']);
        }

        $ccdRows = $query->limit($options['limit'])->get();

        if ($ccdRows->isEmpty()) {
            $this->warn('No se encontraron CCD para los filtros indicados.');

            return self::SUCCESS;
        }

        $counts = [
            'procesados' => 0,
            'candidatas' => 0,
            'confirmadas' => 0,
            'ambiguas' => 0,
            'sin_candidata' => 0,
            'errores' => 0,
        ];
        $details = [];

        foreach ($ccdRows as $ccd) {
            try {
                $result = $associations->audit(
                    ccd: $ccd,
                    persist: $options['apply'],
                    confirmedOperation: $options['confirm_operation'],
                    confirmedByUserId: $options['confirmed_by'],
                    evidence: $options['evidence'],
                );

                $counts['procesados']++;
                $counts['candidatas'] += $result['candidate_count'];

                if ($result['status'] === 'confirmada') {
                    $counts['confirmadas']++;
                } elseif ($result['status'] === 'ambigua') {
                    $counts['ambiguas']++;
                } elseif ($result['status'] === 'sin_candidata') {
                    $counts['sin_candidata']++;
                }

                $details[] = [
                    $ccd->id,
                    $ccd->numdoc,
                    $ccd->cosecha ?: '—',
                    $result['candidate_count'],
                    $result['status'],
                ];
            } catch (Throwable $exception) {
                report($exception);
                $counts['errores']++;
                $details[] = [$ccd->id, $ccd->numdoc, $ccd->cosecha ?: '—', '—', 'error'];
                $this->error("CCD {$ccd->id}: {$exception->getMessage()}");
            }
        }

        $this->table(['CCD', 'Documento', 'Cosecha', 'Candidatas', 'Resultado'], $details);
        $this->table(['Métrica', 'Cantidad'], [
            ['CCD procesados', $counts['procesados']],
            ['Asociaciones candidatas', $counts['candidatas']],
            ['CCD confirmados', $counts['confirmadas']],
            ['CCD ambiguos', $counts['ambiguas']],
            ['CCD sin candidata', $counts['sin_candidata']],
            ['Errores', $counts['errores']],
        ]);

        $this->info($options['apply']
            ? 'Lote aplicado. Las asociaciones sin evidencia siguen en estado candidata.'
            : 'Simulación completada sin escribir asociaciones.');

        return $counts['errores'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{ccd_id:?int,dni:?string,after_id:int,limit:int,apply:bool,confirm_operation:?string,evidence:?string,confirmed_by:?int}|null */
    private function validatedOptions(): ?array
    {
        $ccdId = $this->positiveIntegerOption('ccd-id');
        $afterId = $this->nonNegativeIntegerOption('after-id');
        $limit = $this->positiveIntegerOption('limit');
        $confirmedBy = $this->positiveIntegerOption('confirmed-by');

        if ($afterId === null || $limit === null || $limit > 100) {
            $this->error('--after-id debe ser un entero no negativo y --limit debe estar entre 1 y 100.');

            return null;
        }

        if ($this->option('ccd-id') !== null && $ccdId === null) {
            $this->error('--ccd-id debe ser un entero positivo.');

            return null;
        }

        $confirmOperation = trim((string) $this->option('confirm-operation')) ?: null;
        $evidence = trim((string) $this->option('evidence')) ?: null;

        if ($confirmOperation !== null) {
            if ($ccdId === null || $evidence === null) {
                $this->error('Una confirmación requiere --ccd-id, --confirm-operation y --evidence.');

                return null;
            }

            if ((bool) $this->option('apply') && $confirmedBy === null) {
                $this->error('Aplicar una confirmación requiere --confirmed-by.');

                return null;
            }

            if ($confirmedBy !== null && ! User::query()->whereKey($confirmedBy)->exists()) {
                $this->error('El usuario indicado en --confirmed-by no existe.');

                return null;
            }
        } elseif ($evidence !== null || $confirmedBy !== null) {
            $this->error('--evidence y --confirmed-by sólo se usan junto con --confirm-operation.');

            return null;
        }

        return [
            'ccd_id' => $ccdId,
            'dni' => trim((string) $this->option('dni')) ?: null,
            'after_id' => $afterId,
            'limit' => $limit,
            'apply' => (bool) $this->option('apply'),
            'confirm_operation' => $confirmOperation,
            'evidence' => $evidence,
            'confirmed_by' => $confirmedBy,
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

    private function nonNegativeIntegerOption(string $name): ?int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT);

        return $value !== false && $value >= 0 ? $value : null;
    }
}
