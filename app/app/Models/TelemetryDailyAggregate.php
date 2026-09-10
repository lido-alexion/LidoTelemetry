<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryDailyAggregate extends Model
{
    protected $table = 'telemetry_daily_aggregates';

    protected $fillable = [
        'product_id',
        'environment',
        'signal_family',
        'metric_key',
        'bucket_date',
        'count',
        'sum_value',
        'dimensions',
        'dimensions_hash',
    ];

    protected function casts(): array
    {
        return [
            'bucket_date' => 'date',
            'dimensions' => 'array',
            'sum_value' => 'float',
        ];
    }
}
