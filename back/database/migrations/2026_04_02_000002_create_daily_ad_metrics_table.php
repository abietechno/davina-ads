<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_ad_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_ad_account_id')
                  ->constrained('client_ad_accounts')
                  ->cascadeOnDelete();
            $table->date('date');
            $table->string('campaign_name');
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['client_ad_account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ad_metrics');
    }
};
