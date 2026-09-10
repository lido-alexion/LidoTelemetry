<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'action',
    'subject_type',
    'subject_id',
    'context',
    'ip_address',
    'occurred_at',
])]
class TelemetryAuditEntry extends Model
{
    protected $table = 'telemetry_audit_entries';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
