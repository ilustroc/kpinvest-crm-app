<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePromesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Documento (numdoc o dni) desde la ruta o input
        $numdoc = (string) ($this->route('numdoc') ?? $this->route('dni') ?? $this->input('numdoc') ?? $this->input('dni') ?? '');

        // Mapear fecha de cancelación => fecha_pago
        $fecha = $this->input('fecha_pago') ?: $this->input('fecha_pago_cancel');

        // Normalizar fecha (soporta dd/mm/yyyy)
        if (is_string($fecha) && $fecha !== '') {
            $fecha = trim($fecha);
            if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $fecha, $m)) {
                $fecha = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            } else {
                try {
                    $fecha = Carbon::parse($fecha, 'America/Lima')->toDateString();
                } catch (\Throwable $e) {
                }
            }
        }

        // Normalizar arrays para evitar nulls
        $ops = array_values(array_filter((array) $this->input('operaciones', [])));
        $rawCf = array_values((array) $this->input('cron_fecha', []));
        $cM = array_values((array) $this->input('cron_monto', []));

        // Normalizar fechas de cronograma si vienen en formato dd/mm/yyyy
        $cF = array_map(function ($f) {
            if (is_string($f) && trim($f) !== '') {
                $f = trim($f);
                if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $f, $m)) {
                    return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
                }
                try {
                    return Carbon::parse($f, 'America/Lima')->toDateString();
                } catch (\Throwable $e) {
                }
            }

            return $f;
        }, $rawCf);

        $this->merge([
            'numdoc' => $numdoc,
            'dni' => $numdoc,
            'fecha_pago' => $fecha,
            'operaciones' => $ops,
            'cron_fecha' => $cF,
            'cron_monto' => $cM,
        ]);
    }

    public function rules(): array
    {
        $todayLima = Carbon::now('America/Lima')->toDateString();

        return [
            'numdoc' => 'required|string|max:30',
            'dni' => 'nullable|string|max:30',
            'tipo' => 'required|in:convenio,convenio_balon,cancelacion',
            'nota' => 'nullable|string|max:500',
            'telefono' => ['required', 'string', 'regex:/^9[0-9]{8}$/'],

            'operaciones' => 'required|array|min:1',
            'operaciones.*' => 'string|max:50',

            'fecha_pago' => [
                'exclude_unless:tipo,cancelacion',
                'required',
                'date',
                'after_or_equal:'.$todayLima,
            ],
            'monto_cancel' => 'exclude_unless:tipo,cancelacion|required|numeric|min:0.01',

            'nro_cuotas' => 'exclude_unless:tipo,convenio,convenio_balon|required|integer|min:1',
            'monto_convenio' => 'exclude_unless:tipo,convenio,convenio_balon|required|numeric|min:0.01',
            'cron_fecha' => 'exclude_unless:tipo,convenio,convenio_balon|required|array|min:1',
            'cron_fecha.*' => [
                'exclude_unless:tipo,convenio,convenio_balon',
                'required',
                'date',
                'after_or_equal:'.$todayLima,
            ],
            'cron_monto' => 'exclude_unless:tipo,convenio,convenio_balon|required|array|min:1',
            'cron_monto.*' => 'exclude_unless:tipo,convenio,convenio_balon|numeric|min:0.01',
            'cron_balon' => 'exclude_unless:tipo,convenio_balon|nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'dni.required' => 'No se recibió el DNI.',
            'telefono.required' => 'El teléfono de contacto es obligatorio.',
            'telefono.regex' => 'El teléfono debe tener exactamente 9 dígitos y comenzar con 9.',
            'fecha_pago.required' => 'La fecha de pago es obligatoria en cancelación.',
            'fecha_pago.after_or_equal' => 'La fecha de pago no puede ser anterior a la fecha actual.',
            'cron_fecha.required' => 'El cronograma de pagos es obligatorio.',
            'cron_fecha.*.required' => 'Todas las cuotas del cronograma deben tener fecha de pago.',
            'cron_fecha.*.after_or_equal' => 'Las fechas del cronograma no pueden ser anteriores a la fecha actual.',
            'monto_cancel.required' => 'El monto a cancelar es obligatorio.',
            'monto_convenio.required' => 'El monto convenio es obligatorio.',
            'operaciones.required' => 'Debes seleccionar al menos una operación.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {

            $dni = (string) $this->input('dni');
            $operations = array_values(array_filter(array_map('strval', (array) $this->input('operaciones', []))));

            if (! empty($operations) && ! empty($dni)) {
                $found = \Illuminate\Support\Facades\DB::table('clientes_cuentas')
                    ->where('numdoc', $dni)
                    ->whereIn('operacion', $operations)
                    ->pluck('operacion')
                    ->map('strval')
                    ->all();

                $missing = array_diff($operations, $found);
                if (! empty($missing)) {
                    $v->errors()->add('operaciones', 'Las operaciones seleccionadas no corresponden al cliente.');
                }
            }

            $todayLima = Carbon::now('America/Lima')->toDateString();

            if ($this->input('tipo') === 'cancelacion') {
                $fechaPago = (string) $this->input('fecha_pago');
                if ($fechaPago !== '' && $fechaPago < $todayLima) {
                    $v->errors()->add('fecha_pago', 'La fecha de pago no puede ser anterior a la fecha actual.');
                }
            } else {
                foreach ((array) $this->input('cron_fecha', []) as $idx => $f) {
                    $fechaStr = (string) $f;
                    if ($fechaStr !== '' && $fechaStr < $todayLima) {
                        $v->errors()->add("cron_fecha.{$idx}", 'Las fechas del cronograma no pueden ser anteriores a la fecha actual.');
                    }
                }
            }

            $tipo = (string) $this->input('tipo');

            if (! in_array($tipo, ['convenio', 'convenio_balon'], true)) {
                return;
            }

            $montoConvenio = round((float) $this->input('monto_convenio', 0), 2);

            $montos = (array) $this->input('cron_monto', []);
            $sumCrono = round(array_sum(array_map(fn ($x) => (float) $x, $montos)), 2);

            // Debe ser EXACTAMENTE igual (con tolerancia 0.01)
            if (abs($sumCrono - $montoConvenio) > 0.01) {
                $v->errors()->add('monto_convenio', 'El Monto convenio debe ser igual a la suma del cronograma.');
                $v->errors()->add('cron_monto', 'La suma del cronograma no coincide con el Monto convenio.');
            }

            // Validación extra: cantidades deben calzar con nro_cuotas
            $n = (int) $this->input('nro_cuotas', 0);
            if ($n > 0) {
                if (count((array) $this->input('cron_fecha', [])) !== $n || count($montos) !== $n) {
                    $v->errors()->add('cron_monto', 'El cronograma debe tener la misma cantidad de cuotas que "Nro cuotas".');
                }
            }

            // En cuota balón: cron_balon debe estar dentro de rango
            if ($tipo === 'convenio_balon') {
                $b = (int) $this->input('cron_balon', 0);
                if ($b < 1 || $b > $n) {
                    $v->errors()->add('cron_balon', 'Índice de cuota balón inválido.');
                }
            }
        });
    }
}
