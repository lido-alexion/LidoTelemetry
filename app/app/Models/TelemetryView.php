<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'view_instance_id',
    'session_id',
    'product_id',
    'environment',
    'view_name',
    'started_at',
    'ended_at',
    'is_complete',
    'active_duration_ms',
    'wall_duration_ms',
    'duration_estimated',
    'metadata_snapshot',
])]
class TelemetryView extends Model
{
    protected $table = 'telemetry_views';

    protected $primaryKey = 'view_instance_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_complete' => 'boolean',
            'duration_estimated' => 'boolean',
            'metadata_snapshot' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TelemetrySession::class, 'session_id', 'session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }
}
