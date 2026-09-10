<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'environment',
    'scope_type',
    'scope_value',
    'range_start',
    'range_end',
    'requested_by_user_id',
    'deleted_at',
    'meta',
])]
class TelemetryDeletionTombstone extends Model
{
    use HasUuids;

    protected $table = 'telemetry_deletion_tombstones';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'range_start' => 'datetime',
            'range_end' => 'datetime',
            'deleted_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TelemetryProduct::class, 'product_id');
    }
}
