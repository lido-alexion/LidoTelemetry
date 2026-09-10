<?php

namespace Database\Seeders;

use App\Models\TelemetryDashboard;
use App\Models\TelemetryDashboardWidget;
use App\Models\TelemetryIngestionCredential;
use App\Models\TelemetryProduct;
use App\Models\TelemetryProductEnvironment;
use App\Models\TelemetryRemoteConfig;
use App\Models\User;
use Illuminate\Database\Seeder;

class TelemetrySeeder extends Seeder
{
    public const DEMO_INGESTION_TOKEN = 'lti_demo_stox_production_seed_token_change_me';

    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@lidotelemetry.local'],
            [
                'name' => 'Telemetry Admin',
                'password' => 'password123',
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        $product = TelemetryProduct::query()->updateOrCreate(
            ['product_key' => 'stox'],
            [
                'product_name' => 'StoX',
                'is_active' => true,
                'settings' => [
                    'brand' => 'StoX by Lido Alexion',
                ],
            ],
        );

        $environment = TelemetryProductEnvironment::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'environment_key' => 'production',
            ],
            [
                'display_name' => 'Production',
                'is_active' => true,
                'raw_retention_days' => config('telemetry.retention.raw_days'),
                'aggregate_retention_days' => config('telemetry.retention.aggregate_days'),
            ],
        );

        TelemetryRemoteConfig::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'environment_id' => $environment->id,
            ],
            [
                'config' => [
                    'heartbeat_seconds' => config('telemetry.sdk.default_heartbeat_seconds'),
                    'batch_size' => config('telemetry.sdk.default_batch_size'),
                    'automatic_navigation_tracking' => true,
                    'enabled_signal_families' => ['events', 'metrics', 'logs', 'traces'],
                ],
                'version' => 1,
                'is_active' => true,
            ],
        );

        $tokenPrefix = substr(self::DEMO_INGESTION_TOKEN, 0, 12);

        TelemetryIngestionCredential::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'environment_id' => $environment->id,
                'name' => 'StoX Production SDK',
            ],
            [
                'token_hash' => hash('sha256', self::DEMO_INGESTION_TOKEN),
                'token_prefix' => $tokenPrefix,
                'is_active' => true,
                'revoked_at' => null,
            ],
        );

        $this->seedBuiltinDashboards($product->id);

        $this->command?->info('Seeded admin user: admin@lidotelemetry.local / password123');
        $this->command?->info('Demo ingestion token: '.self::DEMO_INGESTION_TOKEN);
    }

    protected function seedBuiltinDashboards(string $productId): void
    {
        $definitions = [
            [
                'slug' => 'overview',
                'name' => 'Overview',
                'widgets' => [
                    [
                        'widget_type' => 'metric_card',
                        'title' => 'Total Events',
                        'config' => [
                            'analysis_type' => 'aggregate',
                            'query' => [
                                'signal_family' => 'events',
                                'aggregations' => [['function' => 'count']],
                            ],
                        ],
                    ],
                    [
                        'widget_type' => 'time_series',
                        'title' => 'Events Over Time',
                        'config' => [
                            'analysis_type' => 'time_series',
                            'query' => [
                                'signal_family' => 'events',
                                'time_bucket' => 'hour',
                                'aggregations' => [['function' => 'count']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'product-usage',
                'name' => 'Product Usage',
                'widgets' => [
                    [
                        'widget_type' => 'breakdown',
                        'title' => 'Top Event Types',
                        'config' => [
                            'analysis_type' => 'group_by',
                            'query' => [
                                'signal_family' => 'events',
                                'group_by' => ['event_type'],
                                'aggregations' => [['function' => 'count']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'navigation-views',
                'name' => 'Navigation / Views',
                'widgets' => [
                    [
                        'widget_type' => 'breakdown',
                        'title' => 'View Starts',
                        'config' => [
                            'analysis_type' => 'aggregate',
                            'query' => [
                                'signal_family' => 'events',
                                'filters' => [
                                    ['field' => 'event_type', 'operator' => 'eq', 'value' => 'navigation.view_started'],
                                ],
                                'aggregations' => [['function' => 'count']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'sessions',
                'name' => 'Sessions',
                'widgets' => [
                    [
                        'widget_type' => 'table',
                        'title' => 'Recent Sessions',
                        'config' => [
                            'analysis_type' => 'sessions',
                            'query' => [
                                'signal_family' => 'events',
                                'limit' => 50,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'funnels',
                'name' => 'Funnels',
                'widgets' => [
                    [
                        'widget_type' => 'funnel',
                        'title' => 'Sample Funnel',
                        'config' => [
                            'analysis_type' => 'funnels',
                            'steps' => [
                                ['event_type' => 'navigation.view_started'],
                                ['event_type' => 'interaction.button_clicked'],
                            ],
                            'query' => [
                                'signal_family' => 'events',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'errors-reliability',
                'name' => 'Errors / Reliability',
                'widgets' => [
                    [
                        'widget_type' => 'breakdown',
                        'title' => 'Operational Failures',
                        'config' => [
                            'analysis_type' => 'aggregate',
                            'query' => [
                                'signal_family' => 'events',
                                'filters' => [
                                    ['field' => 'category', 'operator' => 'eq', 'value' => 'operational'],
                                ],
                                'aggregations' => [['function' => 'count']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'latency-performance',
                'name' => 'Latency / Performance',
                'widgets' => [
                    [
                        'widget_type' => 'time_series',
                        'title' => 'Metric Timers',
                        'config' => [
                            'analysis_type' => 'time_series',
                            'query' => [
                                'signal_family' => 'metrics',
                                'time_bucket' => 'hour',
                                'aggregations' => [['function' => 'avg', 'field' => 'value']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'operational-health',
                'name' => 'Operational Health',
                'widgets' => [
                    [
                        'widget_type' => 'breakdown',
                        'title' => 'Log Severities',
                        'config' => [
                            'analysis_type' => 'group_by',
                            'query' => [
                                'signal_family' => 'logs',
                                'group_by' => ['severity'],
                                'aggregations' => [['function' => 'count']],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $dashboard = TelemetryDashboard::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'dashboard_type' => 'builtin',
                    'product_ids' => [$productId],
                    'environment_keys' => ['production'],
                    'layout' => null,
                    'is_builtin' => true,
                    'user_id' => null,
                ],
            );

            $dashboard->widgets()->delete();

            foreach ($definition['widgets'] as $index => $widget) {
                TelemetryDashboardWidget::query()->create([
                    'dashboard_id' => $dashboard->id,
                    'widget_type' => $widget['widget_type'],
                    'title' => $widget['title'],
                    'config' => $widget['config'],
                    'position' => $index,
                    'width' => 6,
                    'height' => 2,
                ]);
            }
        }
    }
}
