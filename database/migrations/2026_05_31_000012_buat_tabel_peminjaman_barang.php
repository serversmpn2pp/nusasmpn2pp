<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peminjaman_barang', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_peminjaman', 80)->unique();
            $table->string('jenis_peminjam', 20)->index();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete()->cascadeOnUpdate();
            $table->string('cara_input_peminjam', 20);
            $table->date('tanggal_peminjaman')->index();
            $table->date('rencana_kembali')->nullable()->index();
            $table->string('status', 30)->index();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman_barang');
    }
};
