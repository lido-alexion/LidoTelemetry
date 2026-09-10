<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'product_id',
    'environment',
    'trace_id',
    'span_id',
    'parent_span_id',
    'name',
    'started_at',
    'ended_at',
    'duration_ms',
    'status',
    'attributes',
    'user_id',
    'session_id',
    'correlation_id',
    'received_at',
])]
class TelemetryTraceSpan extends Model
{
    protected $table = 'telemetry_trace_spans';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'attributes' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }
}
