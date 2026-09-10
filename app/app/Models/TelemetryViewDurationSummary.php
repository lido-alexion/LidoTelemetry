<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'view_instance_id',
    'product_id',
    'environment',
    'view_name',
    'active_duration_ms',
    'wall_duration_ms',
    'is_complete',
    'duration_estimated',
    'summary_date',
])]
class TelemetryViewDurationSummary extends Model
{
    protected $table = 'telemetry_view_duration_summaries';

    protected function casts(): array
    {
        return [
            'is_complete' => 'boolean',
            'duration_estimated' => 'boolean',
            'summary_date' => 'date',
        ];
    }

    public function view(): BelongsTo
    {
        return $this->belongsTo(TelemetryView::class, 'view_instance_id', 'view_instance_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }
}
