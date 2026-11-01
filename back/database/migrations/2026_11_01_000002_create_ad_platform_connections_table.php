<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One OAuth connection per platform (e.g. the Google login that owns the MCC), shared by all its ad accounts
        Schema::create('ad_platform_connections', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['meta', 'google'])->unique();
            $table->text('refresh_token'); // encrypted at rest via the model cast
            $table->string('email')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_platform_connections');
    }
};
