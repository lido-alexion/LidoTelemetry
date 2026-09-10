<?php

namespace App\Services\Telemetry;

use App\Models\TelemetryView;
use Carbon\Carbon;

class ViewMaterializationService
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function materializeFromEvent(array $event): void
    {
        $viewId = $event['view_instance_id'] ?? null;

        if ($viewId === null || $viewId === '') {
            return;
        }

        $eventType = (string) ($event['event_type'] ?? '');

        match (true) {
            $eventType === 'navigation.view_started' => $this->handleViewStarted($event),
            $eventType === 'navigation.view_ended' => $this->handleViewEnded($event),
            str_starts_with($eventType, 'navigation.visibility_') => $this->handleVisibility($event),
            $eventType === 'navigation.heartbeat' => $this->handleHeartbeat($event),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function handleViewStarted(array $event): void
    {
        $viewId = $event['view_instance_id'];
        $occurredAt = $this->occurredAt($event);

        $existing = TelemetryView::query()->find($viewId);

        if ($existing !== null) {
            return;
        }

        $metadata = $event['metadata'] ?? [];
        $viewName = $metadata['view_name'] ?? $metadata['view'] ?? $metadata['route'] ?? null;

        TelemetryView::query()->create([
            'view_instance_id' => $viewId,
            'session_id' => $event['session_id'] ?? $viewId,
            'product_id' => $event['product_id'],
            'environment' => $event['environment'],
            'view_name' => is_string($viewName) ? $viewName : null,
            'started_at' => $occurredAt,
            'ended_at' => null,
            'is_complete' => false,
            'active_duration_ms' => 0,
            'wall_duration_ms' => null,
            'duration_estimated' => false,
            'metadata_snapshot' => array_merge($metadata, [
                '_visibility' => 'visible',
                '_last_visibility_at' => $occurredAt->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function handleViewEnded(array $event): void
    {
        $view = $this->findOrCreateView($event);

        if ($view->is_complete) {
            return;
        }

        $occurredAt = $this->occurredAt($event);
        $snapshot = $view->metadata_snapshot ?? [];

        if (($snapshot['_visibility'] ?? 'visible') === 'visible') {
            $lastAt = $this->parseSnapshotTime($snapshot['_last_visibility_at'] ?? null) ?? $view->started_at;
            $view->active_duration_ms += max(0, $lastAt->diffInMilliseconds($occurredAt));
        }

        $view->ended_at = $occurredAt;
        $view->wall_duration_ms = max(0, $view->started_at->diffInMilliseconds($occurredAt));
        $view->is_complete = true;
        $view->duration_estimated = false;
        $snapshot['_visibility'] = 'ended';
        $view->metadata_snapshot = $snapshot;
        $view->save();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function handleVisibility(array $event): void
    {
        $view = $this->findOrCreateView($event);
        $occurredAt = $this->occurredAt($event);
        $snapshot = $view->metadata_snapshot ?? [];
        $previousState = $snapshot['_visibility'] ?? 'visible';
        $eventType = (string) $event['event_type'];

        if ($eventType === 'navigation.visibility_hidden' && $previousState === 'visible') {
            $lastAt = $this->parseSnapshotTime($snapshot['_last_visibility_at'] ?? null) ?? $view->started_at;
            $view->active_duration_ms += max(0, $lastAt->diffInMilliseconds($occurredAt));
            $snapshot['_visibility'] = 'hidden';
        } elseif ($eventType === 'navigation.visibility_visible' && $previousState === 'hidden') {
            $snapshot['_visibility'] = 'visible';
        }

        $snapshot['_last_visibility_at'] = $occurredAt->toIso8601String();
        $view->metadata_snapshot = $snapshot;
        $view->save();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function handleHeartbeat(array $event): void
    {
        $view = $this->findOrCreateView($event);

        if ($view->is_complete) {
            return;
        }

        $occurredAt = $this->occurredAt($event);
        $snapshot = $view->metadata_snapshot ?? [];
        $intervalSeconds = (int) config('telemetry.sdk.default_heartbeat_seconds', 60);

        if (($snapshot['_visibility'] ?? 'visible') === 'visible') {
            $view->active_duration_ms += $intervalSeconds * 1000;
            $view->duration_estimated = true;
        }

        $snapshot['_last_heartbeat_at'] = $occurredAt->toIso8601String();
        $view->metadata_snapshot = $snapshot;
        $view->save();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function findOrCreateView(array $event): TelemetryView
    {
        $viewId = $event['view_instance_id'];
        $view = TelemetryView::query()->find($viewId);

        if ($view !== null) {
            return $view;
        }

        $this->handleViewStarted($event);

        return TelemetryView::query()->findOrFail($viewId);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function occurredAt(array $event): Carbon
    {
        return $event['occurred_at'] instanceof Carbon
            ? $event['occurred_at']
            : Carbon::parse($event['occurred_at']);
    }

    protected function parseSnapshotTime(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
