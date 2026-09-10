<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'dashboard_id',
    'widget_type',
    'title',
    'config',
    'position',
    'width',
    'height',
])]
class TelemetryDashboardWidget extends Model
{
    protected $table = 'telemetry_dashboard_widgets';

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(TelemetryDashboard::class, 'dashboard_id');
    }
}
