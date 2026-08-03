<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE laporan_pembinaan_siswa ALTER COLUMN kategori_pembinaan_siswa_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // Laporan kejadian memang boleh belum memiliki kategori sebelum diputuskan BK.
    }
};
