<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'environment_id',
    'name',
    'token_hash',
    'token_prefix',
    'is_active',
    'last_used_at',
    'revoked_at',
])]
class TelemetryIngestionCredential extends Model
{
    protected $table = 'telemetry_ingestion_credentials';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(TelemetryProductEnvironment::class, 'environment_id');
    }
}
