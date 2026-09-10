<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'slug',
    'name',
    'dashboard_type',
    'product_ids',
    'environment_keys',
    'layout',
    'is_builtin',
])]
class TelemetryDashboard extends Model
{
    use HasUuids;

    protected $table = 'telemetry_dashboards';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
            'environment_keys' => 'array',
            'layout' => 'array',
            'is_builtin' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(TelemetryDashboardWidget::class, 'dashboard_id');
    }
}
