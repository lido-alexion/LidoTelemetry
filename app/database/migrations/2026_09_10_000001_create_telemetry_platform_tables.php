<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('viewer')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::create('telemetry_user_invites', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('role', 32)->default('analyst');
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['email', 'accepted_at']);
        });

        Schema::create('telemetry_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product_key', 64)->unique();
            $table->string('product_name');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('telemetry_product_environments', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id');
            $table->string('environment_key', 64);
            $table->string('display_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('raw_retention_days')->nullable();
            $table->unsignedInteger('aggregate_retention_days')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'environment_key']);
            $table->foreign('product_id')->references('id')->on('telemetry_products')->cascadeOnDelete();
        });

        Schema::create('telemetry_ingestion_credentials', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id');
            $table->foreignId('environment_id')->constrained('telemetry_product_environments')->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign('product_id')->references('id')->on('telemetry_products')->cascadeOnDelete();
        });

        Schema::create('telemetry_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 16);
            $table->string('role', 32);
            $table->json('scopes');
            $table->json('product_ids')->nullable();
            $table->json('environment_keys')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telemetry_remote_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id');
            $table->foreignId('environment_id')->constrained('telemetry_product_environments')->cascadeOnDelete();
            $table->json('config');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'environment_id']);
            $table->foreign('product_id')->references('id')->on('telemetry_products')->cascadeOnDelete();
        });

        Schema::create('telemetry_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->uuid('product_id');
            $table->string('environment', 64);
            $table->timestamp('occurred_at');
            $table->timestamp('received_at');
            $table->string('event_type');
            $table->string('category', 64)->nullable();
            $table->string('user_id')->nullable();
            $table->string('anonymous_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('view_instance_id')->nullable();
            $table->unsignedBigInteger('sequence_number')->nullable();
            $table->string('correlation_id')->nullable();
            $table->string('trace_id')->nullable();
            $table->string('span_id')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('clock_skew_flag')->default(false);
            $table->timestamps();
            $table->index(['product_id', 'environment', 'occurred_at']);
            $table->index(['product_id', 'environment', 'event_type', 'occurred_at']);
            $table->index(['session_id', 'sequence_number']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['correlation_id']);
            $table->foreign('product_id')->references('id')->on('telemetry_products');
        });

        Schema::create('telemetry_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('environment', 64);
            $table->timestamp('occurred_at');
            $table->timestamp('received_at');
            $table->string('name');
            $table->string('type', 32);
            $table->double('value');
            $table->json('dimensions')->nullable();
            $table->string('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('correlation_id')->nullable();
            $table->string('trace_id')->nullable();
            $table->string('span_id')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'environment', 'name', 'occurred_at']);
            $table->foreign('product_id')->references('id')->on('telemetry_products');
        });

        Schema::create('telemetry_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('environment', 64);
            $table->timestamp('occurred_at');
            $table->timestamp('received_at');
            $table->string('severity', 16);
            $table->string('service')->nullable();
            $table->string('message_code')->nullable();
            $table->text('message');
            $table->string('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('correlation_id')->nullable();
            $table->string('trace_id')->nullable();
            $table->string('span_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'environment', 'occurred_at']);
            $table->index(['product_id', 'environment', 'severity', 'occurred_at']);
            $table->foreign('product_id')->references('id')->on('telemetry_products');
        });

        Schema::create('telemetry_trace_spans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('environment', 64);
            $table->string('trace_id');
            $table->string('span_id');
            $table->string('parent_span_id')->nullable();
            $table->string('name');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 32)->nullable();
            $table->json('attributes')->nullable();
            $table->string('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['product_id', 'environment', 'trace_id', 'span_id']);
            $table->index(['product_id', 'environment', 'trace_id']);
            $table->foreign('product_id')->references('id')->on('telemetry_products');
        });

        Schema::create('telemetry_metadata_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id');
            $table->string('metadata_key');
            $table->string('observed_type', 32)->default('mixed');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedBigInteger('approx_cardinality')->default(0);
            $table->boolean('high_cardinality')->default(false);
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'metadata_key']);
            $table->foreign('product_id')->references('id')->on('telemetry_products')->cascadeOnDelete();
        });

        Schema::create('telemetry_sessions', function (Blueprint $table) {
            $table->string('session_id')->primary();
            $table->uuid('product_id');
            $table->string('environment', 64);
            $table->string('user_id')->nullable();
            $table->string('anonymous_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->unsignedInteger('event_count')->default(0);
            $table->json('metadata_snapshot')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'environment', 'started_at']);
            $table->foreign('product_id')->references('id')->on('telemetry_products');
        });

        Schema::create('telemetry_views', function (Blueprint $table) {
            $table->string('view_instance_id')->primary();
            $table->string('session_id');
            $table->uuid('product_id');
            $table->string('environment', 64);
            $table->string('view_name')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->unsignedInteger('active_duration_ms')->default(0);
            $table->unsignedInteger('wall_duration_ms')->nullable();
            $table->boolean('duration_estimated')->default(false);
            $table->json('metadata_snapshot')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'environment', 'started_at']);
            $table->index(['session_id']);
            $table->foreign('session_id')->references('session_id')->on('telemetry_sessions');
            $table->foreign('product_id')->references('id')->on('telemetry_products');
        });

        Schema::create('telemetry_view_duration_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('view_instance_id');
            $table->uuid('product_id');
            $table->string('environment', 64);
            $table->string('view_name')->nullable();
            $table->unsignedInteger('active_duration_ms');
            $table->unsignedInteger('wall_duration_ms')->nullable();
            $table->boolean('is_complete');
            $table->boolean('duration_estimated')->default(false);
            $table->date('summary_date');
            $table->timestamps();
            $table->unique(['view_instance_id']);
            $table->index(['product_id', 'environment', 'summary_date']);
            $table->foreign('view_instance_id')->references('view_instance_id')->on('telemetry_views')->cascadeOnDelete();
        });

        Schema::create('telemetry_hourly_aggregates', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id');
            $table->string('environment', 64);
            $table->string('signal_family', 16);
            $table->string('metric_key');
            $table->timestamp('bucket_start');
            $table->unsignedBigInteger('count')->default(0);
            $table->double('sum_value')->nullable();
            $table->json('dimensions')->nullable();
            $table->string('dimensions_hash', 64)->default('');
            $table->timestamps();
            $table->unique(['product_id', 'environment', 'signal_family', 'metric_key', 'bucket_start', 'dimensions_hash'], 'telemetry_hourly_agg_unique');
            $table->index(['product_id', 'environment', 'bucket_start']);
        });

        Schema::create('telemetry_daily_aggregates', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id');
            $table->string('environment', 64);
            $table->string('signal_family', 16);
            $table->string('metric_key');
            $table->date('bucket_date');
            $table->unsignedBigInteger('count')->default(0);
            $table->double('sum_value')->nullable();
            $table->json('dimensions')->nullable();
            $table->string('dimensions_hash', 64)->default('');
            $table->timestamps();
            $table->unique(['product_id', 'environment', 'signal_family', 'metric_key', 'bucket_date', 'dimensions_hash'], 'telemetry_daily_agg_unique');
            $table->index(['product_id', 'environment', 'bucket_date']);
        });

        Schema::create('telemetry_saved_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('analysis_type', 64);
            $table->json('definition');
            $table->json('product_ids');
            $table->json('environment_keys')->nullable();
            $table->timestamps();
        });

        Schema::create('telemetry_dashboards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('dashboard_type', 32)->default('custom');
            $table->json('product_ids')->nullable();
            $table->json('environment_keys')->nullable();
            $table->json('layout')->nullable();
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();
        });

        Schema::create('telemetry_dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('dashboard_id');
            $table->string('widget_type', 64);
            $table->string('title');
            $table->json('config');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedTinyInteger('width')->default(6);
            $table->unsignedTinyInteger('height')->default(2);
            $table->timestamps();
            $table->foreign('dashboard_id')->references('id')->on('telemetry_dashboards')->cascadeOnDelete();
        });

        Schema::create('telemetry_deletion_tombstones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->nullable();
            $table->string('environment', 64)->nullable();
            $table->string('scope_type', 32);
            $table->string('scope_value')->nullable();
            $table->timestamp('range_start')->nullable();
            $table->timestamp('range_end')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at');
            $table->json('meta')->nullable();
        });

        Schema::create('telemetry_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_audit_entries');
        Schema::dropIfExists('telemetry_deletion_tombstones');
        Schema::dropIfExists('telemetry_dashboard_widgets');
        Schema::dropIfExists('telemetry_dashboards');
        Schema::dropIfExists('telemetry_saved_analyses');
        Schema::dropIfExists('telemetry_daily_aggregates');
        Schema::dropIfExists('telemetry_hourly_aggregates');
        Schema::dropIfExists('telemetry_view_duration_summaries');
        Schema::dropIfExists('telemetry_views');
        Schema::dropIfExists('telemetry_sessions');
        Schema::dropIfExists('telemetry_metadata_keys');
        Schema::dropIfExists('telemetry_trace_spans');
        Schema::dropIfExists('telemetry_logs');
        Schema::dropIfExists('telemetry_metrics');
        Schema::dropIfExists('telemetry_events');
        Schema::dropIfExists('telemetry_remote_configs');
        Schema::dropIfExists('telemetry_api_tokens');
        Schema::dropIfExists('telemetry_ingestion_credentials');
        Schema::dropIfExists('telemetry_product_environments');
        Schema::dropIfExists('telemetry_products');
        Schema::dropIfExists('telemetry_user_invites');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
