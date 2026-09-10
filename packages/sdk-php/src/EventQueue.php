<?php

declare(strict_types=1);

namespace LidoAlexion\Telemetry;

use RuntimeException;

/**
 * Durable bounded FIFO queue for telemetry payloads.
 *
 * Supports file-based persistence (default) or a custom PDO database table.
 */
class EventQueue
{
    public const DRIVER_FILE = 'file';

    public const DRIVER_DATABASE = 'database';

    private string $driver;

    private string $filePath;

    private ?\PDO $pdo;

    private string $table;

    private int $maxSize;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(array $options = [])
    {
        $this->driver = (string) ($options['driver'] ?? self::DRIVER_FILE);
        $this->filePath = (string) ($options['file_path'] ?? sys_get_temp_dir().'/lido_telemetry_queue.jsonl');
        $this->pdo = $options['pdo'] ?? null;
        $this->table = (string) ($options['table'] ?? 'telemetry_queue');
        $this->maxSize = max(1, (int) ($options['max_size'] ?? 5000));

        if ($this->driver === self::DRIVER_DATABASE && $this->pdo === null) {
            throw new RuntimeException('PDO connection is required for database queue driver.');
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function push(array $item): void
    {
        $encoded = json_encode($item, JSON_THROW_ON_ERROR);

        if ($this->driver === self::DRIVER_DATABASE) {
            $this->pushDatabase($encoded);

            return;
        }

        $this->pushFile($encoded);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pop(int $limit): array
    {
        $limit = max(1, $limit);

        if ($this->driver === self::DRIVER_DATABASE) {
            return $this->popDatabase($limit);
        }

        return $this->popFile($limit);
    }

    public function count(): int
    {
        if ($this->driver === self::DRIVER_DATABASE) {
            $statement = $this->pdo->query("SELECT COUNT(*) FROM {$this->table}");
            $count = $statement?->fetchColumn();

            return (int) ($count ?: 0);
        }

        if (! is_file($this->filePath)) {
            return 0;
        }

        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $lines === false ? 0 : count($lines);
    }

    /**
     * @param  array<int, string>  $ids
     */
    public function acknowledge(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        if ($this->driver === self::DRIVER_DATABASE) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $statement = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id IN ({$placeholders})");
            $statement->execute($ids);

            return;
        }

        $this->acknowledgeFile($ids);
    }

    private function pushFile(string $encoded): void
    {
        $directory = dirname($this->filePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create queue directory: {$directory}");
        }

        $handle = fopen($this->filePath, 'c+');

        if ($handle === false) {
            throw new RuntimeException("Unable to open queue file: {$this->filePath}");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock telemetry queue file.');
            }

            $lines = [];

            if (filesize($this->filePath) > 0) {
                rewind($handle);
                $content = stream_get_contents($handle) ?: '';
                $lines = array_values(array_filter(explode("\n", $content), static fn (string $line): bool => $line !== ''));
            }

            $lines[] = $encoded;

            if (count($lines) > $this->maxSize) {
                $lines = array_slice($lines, -$this->maxSize);
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, implode("\n", $lines)."\n");
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function popFile(int $limit): array
    {
        if (! is_file($this->filePath)) {
            return [];
        }

        $handle = fopen($this->filePath, 'c+');

        if ($handle === false) {
            throw new RuntimeException("Unable to open queue file: {$this->filePath}");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock telemetry queue file.');
            }

            $content = stream_get_contents($handle) ?: '';
            $lines = array_values(array_filter(explode("\n", $content), static fn (string $line): bool => $line !== ''));

            if ($lines === []) {
                return [];
            }

            $selected = array_slice($lines, 0, $limit);
            $items = [];

            foreach ($selected as $line) {
                $decoded = json_decode($line, true);

                if (is_array($decoded)) {
                    $items[] = $decoded;
                }
            }

            return $items;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function acknowledgeFile(array $ids): void
    {
        if (! is_file($this->filePath)) {
            return;
        }

        $handle = fopen($this->filePath, 'c+');

        if ($handle === false) {
            throw new RuntimeException("Unable to open queue file: {$this->filePath}");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock telemetry queue file.');
            }

            $content = stream_get_contents($handle) ?: '';
            $lines = array_values(array_filter(explode("\n", $content), static fn (string $line): bool => $line !== ''));
            $remaining = [];

            foreach ($lines as $line) {
                $decoded = json_decode($line, true);

                if (! is_array($decoded)) {
                    continue;
                }

                $id = (string) ($decoded['queue_id'] ?? '');

                if ($id !== '' && in_array($id, $ids, true)) {
                    continue;
                }

                $remaining[] = $line;
            }

            ftruncate($handle, 0);
            rewind($handle);

            if ($remaining !== []) {
                fwrite($handle, implode("\n", $remaining)."\n");
            }

            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function pushDatabase(string $encoded): void
    {
        $count = $this->count();

        if ($count >= $this->maxSize) {
            $overflow = $count - $this->maxSize + 1;
            $this->pdo->exec("DELETE FROM {$this->table} WHERE id IN (SELECT id FROM {$this->table} ORDER BY created_at ASC LIMIT {$overflow})");
        }

        $statement = $this->pdo->prepare("INSERT INTO {$this->table} (payload, created_at) VALUES (?, ?)");
        $statement->execute([$encoded, (new \DateTimeImmutable())->format(DATE_ATOM)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function popDatabase(int $limit): array
    {
        $statement = $this->pdo->prepare("SELECT id, payload FROM {$this->table} ORDER BY created_at ASC LIMIT ?");
        $statement->bindValue(1, $limit, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $items = [];

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['payload'], true);

            if (! is_array($decoded)) {
                continue;
            }

            $decoded['queue_id'] = (string) $row['id'];
            $items[] = $decoded;
        }

        return $items;
    }
}
