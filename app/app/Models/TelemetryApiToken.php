<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'token_hash',
    'token_prefix',
    'role',
    'scopes',
    'product_ids',
    'environment_keys',
    'is_active',
    'last_used_at',
    'expires_at',
    'revoked_at',
])]
class TelemetryApiToken extends Model
{
    protected $table = 'telemetry_api_tokens';

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'product_ids' => 'array',
            'environment_keys' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
