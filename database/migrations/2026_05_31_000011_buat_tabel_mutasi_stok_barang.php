<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi_stok_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_stok_barang_id')
                ->constrained('saldo_stok_barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('barang_id')
                ->constrained('barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('lokasi_barang_id')
                ->constrained('lokasi_barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('jenis_mutasi', 30)->index();
            $table->string('kategori_mutasi', 50)->index();
            $table->date('tanggal_mutasi')->index();
            $table->decimal('jumlah_perubahan', 14, 2);
            $table->decimal('saldo_sebelum', 14, 2);
            $table->decimal('saldo_sesudah', 14, 2);
            $table->string('referensi', 120)->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['barang_id', 'tanggal_mutasi']);
            $table->index(['lokasi_barang_id', 'tanggal_mutasi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_stok_barang');
    }
};
