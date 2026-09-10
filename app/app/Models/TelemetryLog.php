<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'product_id',
    'environment',
    'occurred_at',
    'received_at',
    'severity',
    'service',
    'message_code',
    'message',
    'user_id',
    'session_id',
    'correlation_id',
    'trace_id',
    'span_id',
    'metadata',
])]
class TelemetryLog extends Model
{
    protected $table = 'telemetry_logs';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }
}
