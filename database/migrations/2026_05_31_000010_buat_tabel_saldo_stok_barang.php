<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldo_stok_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')
                ->constrained('barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('lokasi_barang_id')
                ->constrained('lokasi_barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->decimal('jumlah', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['barang_id', 'lokasi_barang_id']);
            $table->index(['lokasi_barang_id', 'jumlah']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_stok_barang');
    }
};
