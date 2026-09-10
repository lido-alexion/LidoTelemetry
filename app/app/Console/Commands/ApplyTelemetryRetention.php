<?php

namespace App\Console\Commands;

use App\Services\Telemetry\RetentionService;
use Illuminate\Console\Command;

class ApplyTelemetryRetention extends Command
{
    protected $signature = 'telemetry:apply-retention';

    protected $description = 'Apply telemetry raw and aggregate retention policies';

    public function handle(RetentionService $retention): int
    {
        $result = $retention->apply();

        $this->info('Raw records deleted: '.$result['raw_deleted']);
        $this->info('Aggregate records deleted: '.$result['aggregate_deleted']);

        return self::SUCCESS;
    }
}
