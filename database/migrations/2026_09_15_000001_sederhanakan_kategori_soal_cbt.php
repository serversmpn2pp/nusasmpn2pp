<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('soal_cbt')
            ->whereNotIn('kategori', ['lots', 'mots', 'hots'])
            ->update(['kategori' => 'mots']);

        $this->ubahNilaiBawaan('mots');
    }

    public function down(): void
    {
        DB::table('soal_cbt')
            ->whereIn('kategori', ['lots', 'mots'])
            ->update(['kategori' => 'umum']);

        $this->ubahNilaiBawaan('umum');
    }

    private function ubahNilaiBawaan(string $nilai): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE soal_cbt ALTER COLUMN kategori SET DEFAULT '{$nilai}'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE soal_cbt MODIFY kategori VARCHAR(40) NOT NULL DEFAULT '{$nilai}'");
        }
    }
};
