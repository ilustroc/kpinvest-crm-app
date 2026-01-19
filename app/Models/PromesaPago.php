<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

class PromesaPago extends Model
{
    // relación
    public function cuotas()
    {
        return $this->hasMany(\App\Models\PromesaCuota::class, 'promesa_id')->orderBy('nro');
    }
    
    protected $table = 'promesas_pago';

    protected $fillable = [
        'dni','operacion','fecha_promesa','monto','nota','user_id','telefono',
        // Estados
        'workflow_estado',        // pendiente|preaprobada|aprobada|rechazada|rechazada_sup
        'estado',                 // legacy (espejo de workflow_estado)

        // Tipo (convenio|cancelacion)
        'tipo',

        // Convenio
        'nro_cuotas','monto_cuota','monto_convenio','fecha_pago','cuota_dia',

        // Auditoría de flujo
        'pre_aprobado_por','pre_aprobado_at',
        'aprobado_por','aprobado_at',
        'rechazado_por','rechazado_at','nota_rechazo',

        // Notas positivas
        'nota_preaprobacion','nota_aprobacion',
    ];

    protected $casts = [
        'fecha_promesa'   => 'date',
        'fecha_pago'      => 'date',

        'pre_aprobado_at' => 'datetime',
        'aprobado_at'     => 'datetime',
        'rechazado_at'    => 'datetime',

        'monto'           => 'decimal:2',
        'monto_cuota'     => 'decimal:2',
        'monto_convenio'  => 'decimal:2',

        'nro_cuotas'      => 'integer',
        'cuota_dia'       => 'integer',
    ];

    /** Atributos calculados expuestos a la vista */
    protected $appends = [
        // Decisión (nota, fecha, autor y css)
        'decision_nota','decision_at','decision_user_name','decision_css_class',
        // Nombres por id
        'aprobado_por_name','pre_aprobado_por_name','rechazado_por_name',
        // Labels / badges
        'workflow_estado_label','tipo_label',
        'workflow_badge_class','tipo_badge_class',
    ];

    /* -------------------------------- Hooks -------------------------------- */

    protected static function booted()
    {
        static::saving(function (self $m) {
            // espejo legacy
            if (!is_null($m->workflow_estado) && $m->workflow_estado !== '') {
                $m->attributes['estado'] = $m->workflow_estado;
            }
            // normalizar tipo
            if (!in_array($m->tipo, ['convenio','cancelacion'], true)) {
                $m->tipo = 'convenio';
            }
        });
    }

    /* ------------------------------ Relaciones ----------------------------- */

    public function operaciones()
    {
        return $this->hasMany(\App\Models\PromesaOperacion::class, 'promesa_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(\App\Models\User::class,'pre_aprobado_por');
    }

    public function administrador()
    {
        return $this->belongsTo(\App\Models\User::class,'aprobado_por');
    }

    public function revisor()
    {
        return $this->belongsTo(\App\Models\User::class,'rechazado_por');
    }
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
    /* -------------------------------- Scopes -------------------------------- */

    public function scopeWithDecisionRefs(Builder $q): Builder
    {
        return $q->with(['supervisor:id,name','administrador:id,name','revisor:id,name']);
    }
    public function scopePendientes(Builder $q)   { return $q->where('workflow_estado','pendiente'); }
    public function scopePreAprobadas(Builder $q) { return $q->where('workflow_estado','preaprobada'); }
    public function scopeAprobadas(Builder $q)    { return $q->where('workflow_estado','aprobada'); }
    public function scopeRechazadas(Builder $q)   { return $q->whereIn('workflow_estado',['rechazada','rechazada_sup']); }

    /* ------------------------------ Helpers -------------------------------- */

    public function esConvenio(): bool    { return $this->tipo === 'convenio'; }
    public function esCancelacion(): bool { return $this->tipo === 'cancelacion'; }

    /* ----------------- Compatibilidad legacy (estado) ---------------------- */

    // leer siempre desde workflow_estado
    public function getEstadoAttribute()
    {
        return $this->attributes['workflow_estado'] ?? null;
    }

    // si alguien setea "estado", lo espejamos en workflow_estado
    public function setEstadoAttribute($value): void
    {
        $this->attributes['estado'] = $value;
        $this->attributes['workflow_estado'] = $value;
    }

    /* ------------------------- Badges/labels (arrays) ---------------------- */

    protected function workflowBadge(): Attribute
    {
        return Attribute::get(function () {
            $map = [
                'pendiente'     => ['warning','Pendiente'],
                'preaprobada'   => ['info','Pre-aprobada'],
                'aprobada'      => ['success','Aprobada'],
                'rechazada'     => ['danger','Rechazada'],
                'rechazada_sup' => ['secondary','Rechazada (Sup)'],
            ];
            $k = $this->workflow_estado ?: 'pendiente';
            return $map[$k] ?? ['light', ucfirst(str_replace('_',' ', (string)$k))];
        });
    }

    protected function tipoBadge(): Attribute
    {
        return Attribute::get(function () {
            $map = [
                'convenio'     => ['warning','Convenio'],
                'cancelacion'  => ['success','Cancelación'],
            ];
            $k = $this->tipo ?: 'convenio';
            return $map[$k] ?? ['light', ucfirst(str_replace('_',' ', (string)$k))];
        });
    }

    protected function estadoBadge(): Attribute
    {
        return $this->workflowBadge();
    }

    /* -------------------------- Datos de decisión -------------------------- */

    protected function decisionNota(): Attribute
    {
        return Attribute::get(function () {
            return match ($this->workflow_estado) {
                'aprobada'                  => $this->nota_aprobacion,
                'preaprobada'               => $this->nota_preaprobacion,
                'rechazada', 'rechazada_sup'=> $this->nota_rechazo,
                default                     => null,
            };
        });
    }

    protected function decisionAt(): Attribute
    {
        return Attribute::get(function () {
            return match ($this->workflow_estado) {
                'aprobada'                  => $this->aprobado_at,
                'preaprobada'               => $this->pre_aprobado_at,
                'rechazada', 'rechazada_sup'=> $this->rechazado_at,
                default                     => null,
            };
        });
    }

    protected function decisionUserName(): Attribute
    {
        return Attribute::get(function () {
            return match ($this->workflow_estado) {
                'aprobada'                  => $this->administrador?->name ?? ($this->aprobado_por ? ('#'.$this->aprobado_por) : null),
                'preaprobada'               => $this->supervisor?->name   ?? ($this->pre_aprobado_por ? ('#'.$this->pre_aprobado_por) : null),
                'rechazada', 'rechazada_sup'=> $this->revisor?->name      ?? ($this->rechazado_por ? ('#'.$this->rechazado_por) : null),
                default                     => null,
            };
        });
    }

    protected function aprobadoPorName(): Attribute
    {
        return Attribute::get(fn() => $this->administrador?->name);
    }
    protected function preAprobadoPorName(): Attribute
    {
        return Attribute::get(fn() => $this->supervisor?->name);
    }
    protected function rechazadoPorName(): Attribute
    {
        return Attribute::get(fn() => $this->revisor?->name);
    }

    public function getWorkflowUserNameAttribute(): ?string
    {
        return $this->decision_user_name;
    }

    /* ------------------------------- Labels -------------------------------- */

    protected function workflowEstadoLabel(): Attribute
    {
        return Attribute::get(function () {
            $map = [
                'pendiente'      => 'Pendiente',
                'preaprobada'    => 'Pre-aprobada',
                'aprobada'       => 'Aprobada',
                'rechazada'      => 'Rechazada',
                'rechazada_sup'  => 'Rechazada (Sup)',
            ];
            $v = (string)($this->workflow_estado ?? 'pendiente');
            return $map[$v] ?? ucfirst(str_replace('_',' ', $v));
        });
    }

    protected function tipoLabel(): Attribute
    {
        return Attribute::get(function () {
            $map = ['convenio' => 'Convenio', 'cancelacion' => 'Cancelación'];
            $v = (string)($this->tipo ?? 'convenio');
            return $map[$v] ?? ucfirst(str_replace('_',' ', $v));
        });
    }

    /* ----------------------------- CSS badges ------------------------------ */

    protected function workflowBadgeClass(): Attribute
    {
        return Attribute::get(function () {
            $map = [
                'pendiente'      => 'badge-pp-warning',
                'preaprobada'    => 'badge-pp-info',
                'aprobada'       => 'badge-pp-success',
                'rechazada'      => 'badge-pp-danger',
                'rechazada_sup'  => 'badge-pp-secondary',
            ];
            $v = (string)($this->workflow_estado ?? 'pendiente');
            return $map[$v] ?? 'text-bg-light';
        });
    }

    protected function tipoBadgeClass(): Attribute
    {
        return Attribute::get(function () {
            $map = [
                'convenio'    => 'badge-pp-warning',
                'cancelacion' => 'badge-pp-success',
                // legacy (por si quedara algo antiguo en DB)
                'parcial'     => 'badge-pp-info',
                'abono_libre' => 'badge-pp-secondary',
            ];
            $v = (string)($this->tipo ?? 'convenio');
            return $map[$v] ?? 'text-bg-light';
        });
    }

    /* ------------------- CSS de la caja de decisión ------------------------ */

    protected function decisionCssClass(): Attribute
    {
        return Attribute::get(function () {
            $map = [
                'preaprobada'   => 'is-preaprobada',
                'aprobada'      => 'is-aprobada',
                'rechazada'     => 'is-rechazada',
                'rechazada_sup' => 'is-rechazada',
                'pendiente'     => 'is-pendiente',
            ];
            $v = (string)($this->workflow_estado ?? 'pendiente');
            return $map[$v] ?? 'is-pendiente';
        });
    }
}