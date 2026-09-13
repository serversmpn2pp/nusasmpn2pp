<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perangkat_notifikasi_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')
                ->constrained('pengguna')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->text('token')->unique();
            $table->string('platform', 20);
            $table->string('nama_perangkat', 120)->nullable();
            $table->string('versi_aplikasi', 40)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamp('terakhir_terlihat_pada');
            $table->timestamps();

            $table->index(
                ['pengguna_id', 'aktif', 'terakhir_terlihat_pada'],
                'perangkat_push_pengguna_aktif',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perangkat_notifikasi_push');
    }
};
