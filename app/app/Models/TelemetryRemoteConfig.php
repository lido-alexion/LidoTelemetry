<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'environment_id',
    'config',
    'version',
    'is_active',
])]
class TelemetryRemoteConfig extends Model
{
    protected $table = 'telemetry_remote_configs';

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
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
