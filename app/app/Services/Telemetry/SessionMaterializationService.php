<?php

namespace App\Services\Telemetry;

use App\Models\TelemetrySession;
use Carbon\Carbon;

class SessionMaterializationService
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function materializeFromEvent(array $event): void
    {
        $sessionId = $event['session_id'] ?? null;

        if ($sessionId === null || $sessionId === '') {
            return;
        }

        $occurredAt = $event['occurred_at'] instanceof Carbon
            ? $event['occurred_at']
            : Carbon::parse($event['occurred_at']);

        $session = TelemetrySession::query()->find($sessionId);

        if ($session === null) {
            TelemetrySession::query()->create([
                'session_id' => $sessionId,
                'product_id' => $event['product_id'],
                'environment' => $event['environment'],
                'user_id' => $event['user_id'] ?? null,
                'anonymous_id' => $event['anonymous_id'] ?? null,
                'started_at' => $occurredAt,
                'last_seen_at' => $occurredAt,
                'ended_at' => null,
                'is_complete' => false,
                'event_count' => 1,
                'metadata_snapshot' => $event['metadata'] ?? null,
            ]);

            return;
        }

        $session->last_seen_at = $occurredAt->greaterThan($session->last_seen_at) ? $occurredAt : $session->last_seen_at;
        $session->event_count = ($session->event_count ?? 0) + 1;

        if ($event['user_id'] ?? null) {
            $session->user_id = $event['user_id'];
        }

        if ($event['anonymous_id'] ?? null) {
            $session->anonymous_id = $event['anonymous_id'];
        }

        if (($event['event_type'] ?? '') === 'navigation.session_ended') {
            $session->ended_at = $occurredAt;
            $session->is_complete = true;
        }

        $session->save();
    }
}
