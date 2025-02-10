<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGenderToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Menambahkan kolom enum 'gender' dengan nilai 'Laki - Laki' dan 'Perempuan'
            // Di sini kita juga menggunakan nullable() jika tidak semua user sudah memiliki nilai gender
            $table->enum('gender', ['Laki - Laki', 'Perempuan'])->nullable()->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom gender
            $table->dropColumn('gender');
        });
    }
}
