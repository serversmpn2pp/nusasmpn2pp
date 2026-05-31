<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengembalian_barang', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pengembalian', 80)->unique();
            $table->foreignId('peminjaman_barang_id')->constrained('peminjaman_barang')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('tanggal_pengembalian')->index();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengembalian_barang');
    }
};
