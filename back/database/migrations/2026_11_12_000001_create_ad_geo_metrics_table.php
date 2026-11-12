<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily delivery per audience location (account level, not per campaign) for the dashboard map
        Schema::create('ad_geo_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_ad_account_id')
                  ->constrained('client_ad_accounts')
                  ->cascadeOnDelete();
            $table->date('date');
            $table->char('country_code', 2)->nullable(); // null = platform couldn't tell
            $table->string('region')->nullable();        // province/state as the platform names it (English)
            $table->string('city')->nullable();          // Google only; Meta reports no deeper than region
            $table->decimal('spend', 15, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('conversions', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['client_ad_account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_geo_metrics');
    }
};
