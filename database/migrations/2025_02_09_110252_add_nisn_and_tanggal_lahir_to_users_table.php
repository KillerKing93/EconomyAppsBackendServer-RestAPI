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
            // Menambahkan field 'nisn' setelah kolom 'email'
            $table->string('nisn')->nullable()->after('email');
            // Menambahkan field 'tanggal_lahir' setelah kolom 'nisn'
            $table->date('tanggal_lahir')->nullable()->after('nisn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nisn', 'tanggal_lahir']);
        });
    }
};
