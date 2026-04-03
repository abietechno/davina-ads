<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_ad_metrics', function (Blueprint $table) {
            $table->enum('level', ['campaign', 'adset', 'ad'])->default('campaign')->after('client_ad_account_id');
            $table->string('adset_id')->nullable()->after('campaign_id');
            $table->string('adset_name')->nullable()->after('adset_id');
            $table->string('ad_id')->nullable()->after('adset_name');
            $table->string('ad_name')->nullable()->after('ad_id');
            $table->unsignedInteger('reach')->default(0)->after('clicks');

            // Update index for better queries
            $table->index(['client_ad_account_id', 'level', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_ad_metrics', function (Blueprint $table) {
            $table->dropIndex(['client_ad_account_id', 'level', 'date']);
            $table->dropColumn(['level', 'adset_id', 'adset_name', 'ad_id', 'ad_name', 'reach']);
        });
    }
};
