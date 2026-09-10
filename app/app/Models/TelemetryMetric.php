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
    'name',
    'type',
    'value',
    'dimensions',
    'user_id',
    'session_id',
    'correlation_id',
    'trace_id',
    'span_id',
])]
class TelemetryMetric extends Model
{
    protected $table = 'telemetry_metrics';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'value' => 'float',
            'dimensions' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }
}
