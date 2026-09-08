<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akses_cepat_pengguna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')
                ->constrained('pengguna')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('kode_menu', 100);
            $table->unsignedTinyInteger('urutan');
            $table->timestamps();

            $table->unique(['pengguna_id', 'kode_menu']);
            $table->unique(['pengguna_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akses_cepat_pengguna');
    }
};
