<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryAuditEntry;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function log(
        string $action,
        ?int $userId = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $context = null,
        ?string $ipAddress = null,
    ): TelemetryAuditEntry {
        return TelemetryAuditEntry::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'context' => $context,
            'ip_address' => $ipAddress,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function logFromRequest(
        Request $request,
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $context = null,
    ): TelemetryAuditEntry {
        return $this->log(
            action: $action,
            userId: $request->user()?->id,
            subjectType: $subjectType,
            subjectId: $subjectId,
            context: $context,
            ipAddress: $request->ip(),
        );
    }
}
