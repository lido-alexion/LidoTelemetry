<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'session_id',
    'product_id',
    'environment',
    'user_id',
    'anonymous_id',
    'started_at',
    'last_seen_at',
    'ended_at',
    'is_complete',
    'event_count',
    'metadata_snapshot',
])]
class TelemetrySession extends Model
{
    protected $table = 'telemetry_sessions';

    protected $primaryKey = 'session_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_complete' => 'boolean',
            'metadata_snapshot' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(TelemetryView::class, 'session_id', 'session_id');
    }
}
