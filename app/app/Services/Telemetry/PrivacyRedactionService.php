<?php

namespace App\Services\Telemetry;

use Illuminate\Validation\ValidationException;

class PrivacyRedactionService
{
    /**
     * @return array{metadata: array<string, mixed>, redacted_keys: array<int, string>}
     */
    public function process(?array $metadata): array
    {
        if ($metadata === null || $metadata === []) {
            return ['metadata' => [], 'redacted_keys' => []];
        }

        $this->validateStructure($metadata);

        $redactedKeys = [];
        $processed = $this->walk($metadata, 0, $redactedKeys);

        return [
            'metadata' => $processed,
            'redacted_keys' => $redactedKeys,
        ];
    }

    public function redact(?array $metadata): array
    {
        return $this->process($metadata)['metadata'];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function validateStructure(array $metadata): void
    {
        $maxDepth = (int) config('telemetry.ingestion.max_metadata_depth', 8);
        $maxKeys = (int) config('telemetry.ingestion.max_metadata_keys', 100);

        $keyCount = $this->countKeys($metadata);

        if ($keyCount > $maxKeys) {
            throw ValidationException::withMessages([
                'metadata' => ["Metadata exceeds maximum of {$maxKeys} keys (found {$keyCount})."],
            ]);
        }

        if ($this->depth($metadata) > $maxDepth) {
            throw ValidationException::withMessages([
                'metadata' => ["Metadata exceeds maximum depth of {$maxDepth}."],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $redactedKeys
     * @return array<string, mixed>
     */
    protected function walk(array $data, int $depth, array &$redactedKeys, string $prefix = ''): array
    {
        $maxDepth = (int) config('telemetry.ingestion.max_metadata_depth', 8);
        $maxString = (int) config('telemetry.ingestion.max_string_length', 1024);
        $result = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if ($this->isForbiddenKey((string) $key)) {
                $redactedKeys[] = $fullKey;

                continue;
            }

            if (is_array($value)) {
                if ($depth + 1 > $maxDepth) {
                    throw ValidationException::withMessages([
                        'metadata' => ["Metadata exceeds maximum depth of {$maxDepth} at {$fullKey}."],
                    ]);
                }

                $result[$key] = $this->walk($value, $depth + 1, $redactedKeys, $fullKey);

                continue;
            }

            if (is_string($value) && mb_strlen($value) > $maxString) {
                $result[$key] = mb_substr($value, 0, $maxString);
            } elseif (is_float($value) || is_int($value) || is_bool($value) || $value === null) {
                $result[$key] = $value;
            } elseif (is_string($value)) {
                $result[$key] = $value;
            } else {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }

    protected function isForbiddenKey(string $key): bool
    {
        $normalized = strtolower($key);

        $forbidden = config('telemetry.ingestion.forbidden_metadata_keys', []);

        if (in_array($normalized, $forbidden, true)) {
            return true;
        }

        foreach (config('telemetry.ingestion.forbidden_metadata_patterns', []) as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function countKeys(array $data): int
    {
        $count = 0;

        foreach ($data as $value) {
            $count++;

            if (is_array($value)) {
                $count += $this->countKeys($value);
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function depth(array $data): int
    {
        $max = 1;

        foreach ($data as $value) {
            if (is_array($value)) {
                $max = max($max, 1 + $this->depth($value));
            }
        }

        return $max;
    }
}
