<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_ad_accounts', function (Blueprint $table) {
            // Tokens now live in .env (Meta System User / Google refresh token), so per-account tokens are optional
            $table->text('access_token')->nullable()->change();

            $table->char('currency', 3)->nullable()->after('account_id');
            $table->string('timezone')->nullable()->after('currency');
            // Meta only: which actions[].action_type counts as a conversion
            $table->string('conversion_action_type')->nullable()->after('timezone');
            $table->boolean('is_active')->default(true)->after('conversion_action_type');
        });

        Schema::table('daily_ad_metrics', function (Blueprint $table) {
            $table->decimal('conversions', 18, 2)->default(0)->after('reach');
            $table->decimal('revenue', 18, 2)->default(0)->after('conversions');
        });

        Schema::create('ad_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_ad_account_id')
                  ->constrained('client_ad_accounts')
                  ->cascadeOnDelete();
            $table->enum('platform', ['meta', 'google']);
            $table->date('date_from');
            $table->date('date_to');
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->unsignedInteger('rows_upserted')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->index(['client_ad_account_id', 'status', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_sync_runs');

        Schema::table('daily_ad_metrics', function (Blueprint $table) {
            $table->dropColumn(['conversions', 'revenue']);
        });

        Schema::table('client_ad_accounts', function (Blueprint $table) {
            $table->dropColumn(['currency', 'timezone', 'conversion_action_type', 'is_active']);
            $table->text('access_token')->nullable(false)->change();
        });
    }
};
