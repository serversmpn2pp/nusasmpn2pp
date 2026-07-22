<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi_pengguna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')
                ->constrained('pengguna')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('jenis', 40)->default('informasi')->index();
            $table->string('judul', 160);
            $table->text('pesan');
            $table->string('tautan', 500)->nullable();
            $table->string('kunci_unik', 190)->nullable();
            $table->json('data_tambahan')->nullable();
            $table->timestamp('dibaca_pada')->nullable()->index();
            $table->timestamps();

            $table->unique(['pengguna_id', 'kunci_unik'], 'notifikasi_pengguna_kunci_unik');
            $table->index(['pengguna_id', 'created_at'], 'notifikasi_pengguna_terbaru');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_pengguna');
    }
};
