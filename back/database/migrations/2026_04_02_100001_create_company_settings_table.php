<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('Ads Analytics');
            $table->string('logo_path')->nullable();
            $table->string('login_banner_path')->nullable();
            $table->string('tagline')->nullable();
            $table->timestamps();
        });

        // Insert default row
        DB::table('company_settings')->insert([
            'company_name' => 'Ads Analytics',
            'tagline' => 'Multi-Platform Ads Dashboard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
