<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_key', 'product_name', 'is_active', 'settings'])]
class TelemetryProduct extends Model
{
    use HasUuids;

    protected $table = 'telemetry_products';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function environments(): HasMany
    {
        return $this->hasMany(TelemetryProductEnvironment::class, 'product_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TelemetryEvent::class, 'product_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(TelemetryMetric::class, 'product_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TelemetryLog::class, 'product_id');
    }

    public function traceSpans(): HasMany
    {
        return $this->hasMany(TelemetryTraceSpan::class, 'product_id');
    }

    public function metadataKeys(): HasMany
    {
        return $this->hasMany(TelemetryMetadataKey::class, 'product_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TelemetrySession::class, 'product_id');
    }

    public function ingestionCredentials(): HasMany
    {
        return $this->hasMany(TelemetryIngestionCredential::class, 'product_id');
    }

    public function remoteConfigs(): HasMany
    {
        return $this->hasMany(TelemetryRemoteConfig::class, 'product_id');
    }
}
