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
        Schema::table('questions', function (Blueprint $table) {
            // Hapus foreign key lama terlebih dahulu
            $table->dropForeign(['material_id']);
            // Ganti nama kolom material_id menjadi challenge_id
            $table->renameColumn('material_id', 'challenge_id');
            // Tambahkan kembali constraint foreign key ke tabel challenges
            $table->foreign('challenge_id')->references('id')->on('challenges')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['challenge_id']);
            $table->renameColumn('challenge_id', 'material_id');
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
        });
    }
};
