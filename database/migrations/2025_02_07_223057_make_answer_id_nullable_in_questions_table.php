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
            // Hapus constraint foreign key terlebih dahulu
            $table->dropForeign(['answer_id']);
        });

        Schema::table('questions', function (Blueprint $table) {
            // Ubah kolom answer_id agar menjadi nullable
            $table->unsignedBigInteger('answer_id')->nullable()->change();
            // Tambahkan kembali constraint foreign key dengan opsi onDelete('cascade')
            $table->foreign('answer_id')->references('id')->on('answers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Hapus constraint foreign key terlebih dahulu
            $table->dropForeign(['answer_id']);
        });

        Schema::table('questions', function (Blueprint $table) {
            // Kembalikan kolom answer_id agar tidak nullable (asumsi semula)
            $table->unsignedBigInteger('answer_id')->nullable(false)->change();
            // Tambahkan kembali constraint foreign key
            $table->foreign('answer_id')->references('id')->on('answers')->onDelete('cascade');
        });
    }
};
