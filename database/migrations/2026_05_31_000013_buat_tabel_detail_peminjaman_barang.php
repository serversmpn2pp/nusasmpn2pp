<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_peminjaman_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peminjaman_barang_id')->constrained('peminjaman_barang')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('unit_barang_id')->nullable()->constrained('unit_barang')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('lokasi_barang_id')->nullable()->constrained('lokasi_barang')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('tipe_pengelolaan', 30)->index();
            $table->decimal('jumlah', 14, 2);
            $table->decimal('jumlah_dikembalikan', 14, 2)->default(0);
            $table->boolean('wajib_dikembalikan')->default(true)->index();
            $table->string('cara_input_barang', 20);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['peminjaman_barang_id', 'wajib_dikembalikan']);
            $table->index(['barang_id', 'lokasi_barang_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_peminjaman_barang');
    }
};
