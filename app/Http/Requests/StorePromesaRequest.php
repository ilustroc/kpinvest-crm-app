<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromesaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

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

    protected function prepareForValidation(): void
    {
        $tel = preg_replace('/[^0-9\+]/', '', (string)($this->input('telefono','')));
        $this->merge(['telefono'=>$tel]);
    }
}
