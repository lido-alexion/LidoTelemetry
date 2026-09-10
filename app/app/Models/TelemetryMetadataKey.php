<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'metadata_key',
    'observed_type',
    'first_seen_at',
    'last_seen_at',
    'approx_cardinality',
    'high_cardinality',
    'label',
    'description',
])]
class TelemetryMetadataKey extends Model
{
    protected $table = 'telemetry_metadata_keys';

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'high_cardinality' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }
}
