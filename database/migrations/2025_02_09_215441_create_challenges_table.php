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
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            // Misalnya, Challenge terkait dengan Material (bisa juga langsung ke Module, sesuai kebutuhan)
            $table->foreignId('material_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('content')->nullable();
            // Tidak ada kolom pdf_path karena Challenge tidak menyimpan PDF
            $table->text('logo_path')->nullable();
            $table->unsignedInteger('jumlah_pertanyaan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
