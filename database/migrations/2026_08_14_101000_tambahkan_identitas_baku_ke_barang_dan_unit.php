<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->string('jenis_barang', 30)
                ->default('tidak_habis_pakai')
                ->after('tipe_pengelolaan')
                ->index();
        });

        DB::table('barang')
            ->where('tipe_pengelolaan', 'habis_pakai')
            ->update(['jenis_barang' => 'habis_pakai']);

        DB::table('barang')
            ->where('tipe_pengelolaan', '!=', 'habis_pakai')
            ->update(['jenis_barang' => 'tidak_habis_pakai']);

        Schema::table('unit_barang', function (Blueprint $table) {
            $table->string('nomor_aset_resmi', 100)->nullable()->after('kode_inventaris')->index();
            $table->unsignedSmallInteger('tahun_perolehan')->nullable()->after('tanggal_perolehan')->index();
            $table->foreignId('sumber_perolehan_barang_id')
                ->nullable()
                ->after('tahun_perolehan')
                ->constrained('sumber_perolehan_barang')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('merek', 120)->nullable()->after('nomor_seri');
            $table->string('tipe', 120)->nullable()->after('merek');
        });

        DB::table('unit_barang')
            ->whereNotNull('tanggal_perolehan')
            ->orderBy('id')
            ->eachById(function ($unit) {
                $tahun = (int) substr((string) $unit->tanggal_perolehan, 0, 4);

                DB::table('unit_barang')
                    ->where('id', $unit->id)
                    ->update([
                        'tahun_perolehan' => $tahun,
                        'nomor_aset_resmi' => '12.03.15.08.10.'.$tahun.'.08',
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('unit_barang', function (Blueprint $table) {
            $table->dropForeign(['sumber_perolehan_barang_id']);
            $table->dropColumn([
                'nomor_aset_resmi',
                'tahun_perolehan',
                'sumber_perolehan_barang_id',
                'merek',
                'tipe',
            ]);
        });

        Schema::table('barang', function (Blueprint $table) {
            $table->dropColumn('jenis_barang');
        });
    }
};
