<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'product_id',
    'environment',
    'occurred_at',
    'received_at',
    'event_type',
    'category',
    'user_id',
    'anonymous_id',
    'session_id',
    'view_instance_id',
    'sequence_number',
    'correlation_id',
    'trace_id',
    'span_id',
    'metadata',
    'clock_skew_flag',
])]
class TelemetryEvent extends Model
{
    protected $table = 'telemetry_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'metadata' => 'array',
            'clock_skew_flag' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }
}
