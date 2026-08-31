<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penerimaan_barang', function (Blueprint $table) {
            $table->string('status', 20)->default('aktif')->after('cara_perolehan')->index();
            $table->text('alasan_pembatalan')->nullable()->after('catatan');
            $table->timestamp('dibatalkan_pada')->nullable()->after('alasan_pembatalan');
            $table->foreignId('dibatalkan_oleh_pengguna_id')
                ->nullable()
                ->after('dibatalkan_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('detail_penerimaan_barang', function (Blueprint $table) {
            $table->foreignId('mutasi_pembatalan_stok_barang_id')
                ->nullable()
                ->after('mutasi_stok_barang_id')
                ->constrained('mutasi_stok_barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('detail_penerimaan_barang', function (Blueprint $table) {
            $table->dropForeign(['mutasi_pembatalan_stok_barang_id']);
            $table->dropColumn('mutasi_pembatalan_stok_barang_id');
        });

        Schema::table('penerimaan_barang', function (Blueprint $table) {
            $table->dropForeign(['dibatalkan_oleh_pengguna_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'alasan_pembatalan',
                'dibatalkan_pada',
                'dibatalkan_oleh_pengguna_id',
            ]);
        });
    }
};
