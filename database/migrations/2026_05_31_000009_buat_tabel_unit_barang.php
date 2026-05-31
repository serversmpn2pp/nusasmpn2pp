<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')
                ->constrained('barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedInteger('nomor_unit');
            $table->string('kode_inventaris', 80)->unique();
            $table->foreignId('lokasi_barang_id')
                ->nullable()
                ->constrained('lokasi_barang')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('nomor_seri', 120)->nullable()->index();
            $table->string('kondisi', 30)->index();
            $table->string('status_unit', 30)->index();
            $table->date('tanggal_perolehan')->nullable();
            $table->string('sumber_perolehan', 120)->nullable();
            $table->decimal('harga_perolehan', 15, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();

            $table->unique(['barang_id', 'nomor_unit']);
            $table->index(['barang_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_barang');
    }
};
