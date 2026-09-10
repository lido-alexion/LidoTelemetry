<?php

namespace App\Providers;

use App\Contracts\Telemetry\EventWriterInterface;
use App\Contracts\Telemetry\LogWriterInterface;
use App\Contracts\Telemetry\MetricWriterInterface;
use App\Contracts\Telemetry\TelemetryQueryServiceInterface;
use App\Contracts\Telemetry\TraceWriterInterface;
use App\Infrastructure\Telemetry\Relational\RelationalTelemetryQueryService;
use App\Services\Telemetry\AggregateMaterializationService;
use App\Services\Telemetry\AuditService;
use App\Services\Telemetry\CredentialService;
use App\Services\Telemetry\DeletionService;
use App\Services\Telemetry\ExportService;
use App\Services\Telemetry\IngestionService;
use App\Services\Telemetry\MetadataCatalogService;
use App\Services\Telemetry\PrivacyRedactionService;
use App\Services\Telemetry\ProductManagementService;
use App\Services\Telemetry\RemoteConfigService;
use App\Services\Telemetry\RetentionService;
use App\Services\Telemetry\SessionMaterializationService;
use App\Services\Telemetry\UserInviteService;
use App\Services\Telemetry\ViewMaterializationService;
use App\Storage\Telemetry\RelationalEventWriter;
use App\Storage\Telemetry\RelationalLogWriter;
use App\Storage\Telemetry\RelationalMetricWriter;
use App\Storage\Telemetry\RelationalTraceWriter;
use Illuminate\Support\ServiceProvider;

class TelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventWriterInterface::class, RelationalEventWriter::class);
        $this->app->singleton(MetricWriterInterface::class, RelationalMetricWriter::class);
        $this->app->singleton(LogWriterInterface::class, RelationalLogWriter::class);
        $this->app->singleton(TraceWriterInterface::class, RelationalTraceWriter::class);
        $this->app->singleton(TelemetryQueryServiceInterface::class, RelationalTelemetryQueryService::class);

        $this->app->singleton(PrivacyRedactionService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(MetadataCatalogService::class);
        $this->app->singleton(SessionMaterializationService::class);
        $this->app->singleton(ViewMaterializationService::class);
        $this->app->singleton(AggregateMaterializationService::class);
        $this->app->singleton(IngestionService::class);
        $this->app->singleton(ProductManagementService::class);
        $this->app->singleton(CredentialService::class);
        $this->app->singleton(UserInviteService::class);
        $this->app->singleton(DeletionService::class);
        $this->app->singleton(ExportService::class);
        $this->app->singleton(RemoteConfigService::class);
        $this->app->singleton(RetentionService::class);
    }

    public function boot(): void
    {
        //
    }
}
