<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_ad_accounts', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('api_key_or_refresh_token');
        });

        Schema::table('daily_ad_metrics', function (Blueprint $table) {
            $table->string('campaign_id')->nullable()->after('campaign_name');
        });
    }

    public function down(): void
    {
        Schema::table('client_ad_accounts', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });

        Schema::table('daily_ad_metrics', function (Blueprint $table) {
            $table->dropColumn('campaign_id');
        });
    }
};
