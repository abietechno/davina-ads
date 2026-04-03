<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['meta', 'google']);
            $table->string('account_name');
            $table->string('account_id');
            $table->text('access_token');
            $table->text('api_key_or_refresh_token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_ad_accounts');
    }
};
