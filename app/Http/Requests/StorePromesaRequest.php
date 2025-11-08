<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            'fecha_pago'  => $fecha,   // ⬅️ clave para que pase la regla required en cancelación
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
}
