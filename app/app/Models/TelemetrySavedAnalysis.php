<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'analysis_type',
    'definition',
    'product_ids',
    'environment_keys',
])]
class TelemetrySavedAnalysis extends Model
{
    use HasUuids;

    protected $table = 'telemetry_saved_analyses';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'product_ids' => 'array',
            'environment_keys' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
