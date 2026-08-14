<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerimaan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_penerimaan', 50)->unique();
            $table->date('tanggal_penerimaan')->index();
            $table->foreignId('sumber_perolehan_barang_id')
                ->constrained('sumber_perolehan_barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('cara_perolehan', 30)->index();
            $table->string('nomor_dokumen', 120)->nullable()->index();
            $table->string('asal_barang', 160)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('detail_penerimaan_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_barang_id')
                ->constrained('penerimaan_barang')
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
            $table->decimal('jumlah', 14, 2);
            $table->decimal('harga_satuan', 15, 2)->nullable();
            $table->string('merek', 120)->nullable();
            $table->string('tipe', 120)->nullable();
            $table->string('kondisi', 30)->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('mutasi_stok_barang_id')
                ->nullable()
                ->constrained('mutasi_stok_barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['barang_id', 'lokasi_barang_id']);
        });

        Schema::table('unit_barang', function (Blueprint $table) {
            $table->foreignId('detail_penerimaan_barang_id')
                ->nullable()
                ->after('barang_id')
                ->constrained('detail_penerimaan_barang')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('unit_barang', function (Blueprint $table) {
            $table->dropForeign(['detail_penerimaan_barang_id']);
            $table->dropColumn('detail_penerimaan_barang_id');
        });

        Schema::dropIfExists('detail_penerimaan_barang');
        Schema::dropIfExists('penerimaan_barang');
    }
};
