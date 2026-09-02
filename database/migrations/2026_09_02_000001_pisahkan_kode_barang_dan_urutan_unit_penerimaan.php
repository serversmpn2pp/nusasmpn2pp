<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_barang', function (Blueprint $table) {
            $table->unsignedInteger('urutan_dalam_penerimaan')
                ->nullable()
                ->after('nomor_unit');
        });

        $detailAktif = null;
        $urutan = 0;

        DB::table('unit_barang')
            ->whereNotNull('detail_penerimaan_barang_id')
            ->orderBy('detail_penerimaan_barang_id')
            ->orderBy('id')
            ->get(['id', 'detail_penerimaan_barang_id'])
            ->each(function ($unit) use (&$detailAktif, &$urutan) {
                if ((int) $detailAktif !== (int) $unit->detail_penerimaan_barang_id) {
                    $detailAktif = (int) $unit->detail_penerimaan_barang_id;
                    $urutan = 0;
                }

                $urutan++;
                DB::table('unit_barang')->where('id', $unit->id)->update([
                    'urutan_dalam_penerimaan' => $urutan,
                ]);
            });

        DB::table('unit_barang')
            ->whereNull('detail_penerimaan_barang_id')
            ->whereNull('urutan_dalam_penerimaan')
            ->update(['urutan_dalam_penerimaan' => DB::raw('nomor_unit')]);

        Schema::table('unit_barang', function (Blueprint $table) {
            $table->unique(
                ['detail_penerimaan_barang_id', 'urutan_dalam_penerimaan'],
                'unit_barang_urutan_penerimaan_unik',
            );
        });

        $barang = DB::table('barang')
            ->where('jenis_barang', 'tidak_habis_pakai')
            ->orderBy('id')
            ->get(['id', 'kode']);

        foreach ($barang as $item) {
            $kode = trim((string) $item->kode);

            if (! preg_match('/^(\d{2}(?:\.\d{2}){4})\.\d{2}$/', $kode, $bagian)) {
                continue;
            }

            $kodeDasar = $bagian[1];
            $sudahDipakai = DB::table('barang')
                ->where('id', '!=', $item->id)
                ->where('kode', $kodeDasar)
                ->exists();

            if (! $sudahDipakai) {
                DB::table('barang')->where('id', $item->id)->update(['kode' => $kodeDasar]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('unit_barang', function (Blueprint $table) {
            $table->dropUnique('unit_barang_urutan_penerimaan_unik');
            $table->dropColumn('urutan_dalam_penerimaan');
        });
    }
};
