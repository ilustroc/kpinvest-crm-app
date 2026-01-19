<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

class StorePromesaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        // DNI desde la ruta
        $dni = (string) $this->route('dni');

        // Teléfono limpio
        $tel = preg_replace('/[^0-9\+]/', '', (string) $this->input('telefono',''));

        // Mapear fecha de cancelación => fecha_pago
        $fecha = $this->input('fecha_pago') ?: $this->input('fecha_pago_cancel');

        // Normalizar fecha (soporta dd/mm/yyyy)
        if (is_string($fecha) && $fecha !== '') {
            $fecha = trim($fecha);
            if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $fecha, $m)) {
                $fecha = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            } else {
                try { $fecha = Carbon::parse($fecha, 'America/Lima')->toDateString(); } catch (\Throwable $e) {}
            }
        }

        // Normalizar arrays para evitar nulls
        $ops   = array_values(array_filter((array) $this->input('operaciones', [])));
        $cF    = array_values((array) $this->input('cron_fecha', []));
        $cM    = array_values((array) $this->input('cron_monto', []));

        $this->merge([
            'dni'         => $dni,
            'telefono'    => $tel,
            'fecha_pago'  => $fecha,
            'operaciones' => $ops,
            'cron_fecha'  => $cF,
            'cron_monto'  => $cM,
        ]);
    }

    public function rules(): array
    {
        return [
            'dni'           => 'required|string|max:30',
            'tipo'          => 'required|in:convenio,convenio_balon,cancelacion',
            'nota'          => 'nullable|string|max:500',
            'telefono'      => 'required|string|max:30',

            'operaciones'   => 'required|array|min:1',
            'operaciones.*' => 'string|max:50',

            'fecha_pago'    => 'exclude_unless:tipo,cancelacion|required|date',
            'monto_cancel'  => 'exclude_unless:tipo,cancelacion|required|numeric|min:0.01',

            'nro_cuotas'     => 'exclude_unless:tipo,convenio,convenio_balon|required|integer|min:1',
            'monto_convenio' => 'exclude_unless:tipo,convenio,convenio_balon|required|numeric|min:0.01',
            'cron_fecha'     => 'exclude_unless:tipo,convenio,convenio_balon|required|array|min:1',
            'cron_fecha.*'   => 'exclude_unless:tipo,convenio,convenio_balon|date',
            'cron_monto'     => 'exclude_unless:tipo,convenio,convenio_balon|required|array|min:1',
            'cron_monto.*'   => 'exclude_unless:tipo,convenio,convenio_balon|numeric|min:0.01',
            'cron_balon'     => 'exclude_unless:tipo,convenio_balon|nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'dni.required'        => 'No se recibió el DNI.',
            'fecha_pago.required' => 'La fecha de pago es obligatoria en cancelación.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {

            $tipo = (string) $this->input('tipo');

            if (!in_array($tipo, ['convenio','convenio_balon'], true)) {
                return;
            }

            $montoConvenio = round((float) $this->input('monto_convenio', 0), 2);

            $montos = (array) $this->input('cron_monto', []);
            $sumCrono = round(array_sum(array_map(fn($x)=>(float)$x, $montos)), 2);

            // Debe ser EXACTAMENTE igual (con tolerancia 0.01)
            if (abs($sumCrono - $montoConvenio) > 0.01) {
                $v->errors()->add('monto_convenio', 'El Monto convenio debe ser igual a la suma del cronograma.');
                $v->errors()->add('cron_monto', 'La suma del cronograma no coincide con el Monto convenio.');
            }

            // Validación extra: cantidades deben calzar con nro_cuotas
            $n = (int) $this->input('nro_cuotas', 0);
            if ($n > 0) {
                if (count((array)$this->input('cron_fecha', [])) !== $n || count($montos) !== $n) {
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
