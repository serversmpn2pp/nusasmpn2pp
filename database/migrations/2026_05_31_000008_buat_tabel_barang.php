<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->foreignId('kategori_barang_id')
                ->constrained('kategori_barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('satuan_barang_id')
                ->constrained('satuan_barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('lokasi_penyimpanan_id')
                ->nullable()
                ->constrained('lokasi_barang')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('tipe_pengelolaan', 30)->index();
            $table->decimal('stok_minimum', 12, 2)->default(0);
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();

            $table->index(['kategori_barang_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
