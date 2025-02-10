<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('name'); // Add nickname field
            $table->timestamp('nickname_updated_at')->nullable()->after('nickname'); // Track last nickname update
            $table->string('avatar')->nullable(); // Store avatar URL
            $table->string('logo_path')->nullable(); // Path to the custom logo image
            $table->string('provider')->nullable()->unique(); // Google, Facebook, etc.
            $table->string('provider_id')->nullable()->unique(); // User's ID from provider
            $table->string('provider_token')->nullable(); // OAuth token from provider
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nickname',
                'nickname_updated_at',
                'avatar', 'logo_path',
                'provider', 'provider_id', 'provider_token']);
        });
    }
};
