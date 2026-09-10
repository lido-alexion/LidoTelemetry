<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_id',
    'environment_key',
    'display_name',
    'is_active',
    'raw_retention_days',
    'aggregate_retention_days',
    'settings',
])]
class TelemetryProductEnvironment extends Model
{
    protected $table = 'telemetry_product_environments';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }

    public function ingestionCredentials(): HasMany
    {
        return $this->hasMany(TelemetryIngestionCredential::class, 'environment_id');
    }

    public function remoteConfig(): HasMany
    {
        return $this->hasMany(TelemetryRemoteConfig::class, 'environment_id');
    }
}
