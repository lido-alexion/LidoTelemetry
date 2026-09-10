<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryHourlyAggregate extends Model
{
    protected $table = 'telemetry_hourly_aggregates';

    protected $fillable = [
        'product_id',
        'environment',
        'signal_family',
        'metric_key',
        'bucket_start',
        'count',
        'sum_value',
        'dimensions',
        'dimensions_hash',
    ];

    protected function casts(): array
    {
        return [
            'bucket_start' => 'datetime',
            'dimensions' => 'array',
            'sum_value' => 'float',
        ];
    }
}
