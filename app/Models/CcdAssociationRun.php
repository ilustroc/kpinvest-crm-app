<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcdAssociationRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'execution_id',
        'mode',
        'status',
        'filters',
        'batch_size',
        'last_ccd_id',
        'processed',
        'eligible',
        'confirmed',
        'already_confirmed',
        'not_eligible',
        'reviews',
        'conflicts',
        'errors',
        'elapsed_seconds',
        'documents_per_second',
        'last_query_count',
        'peak_memory_bytes',
        'last_report_path',
        'stop_reason',
        'started_at',
        'last_heartbeat_at',
        'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'batch_size' => 'integer',
        'last_ccd_id' => 'integer',
        'processed' => 'integer',
        'eligible' => 'integer',
        'confirmed' => 'integer',
        'already_confirmed' => 'integer',
        'not_eligible' => 'integer',
        'reviews' => 'integer',
        'conflicts' => 'integer',
        'errors' => 'integer',
        'elapsed_seconds' => 'float',
        'documents_per_second' => 'float',
        'last_query_count' => 'integer',
        'peak_memory_bytes' => 'integer',
        'started_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
