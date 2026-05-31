<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_pengembalian_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengembalian_barang_id')->constrained('pengembalian_barang')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('detail_peminjaman_barang_id')->constrained('detail_peminjaman_barang')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('jumlah', 14, 2);
            $table->string('kondisi_pengembalian', 30)->nullable();
            $table->string('cara_input_barang', 20);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_pengembalian_barang');
    }
};
