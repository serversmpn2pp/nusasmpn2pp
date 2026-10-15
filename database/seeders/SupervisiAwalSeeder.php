<?php

namespace Database\Seeders;

use App\Models\InstrumenSupervisi;
use App\Services\Supervisi\InstrumenSupervisiAwal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupervisiAwalSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('instrumen_supervisi')) {
            return;
        }
        foreach (['supervisi.lihat' => 'Monitoring supervisi akademik', 'supervisi.kelola' => 'Kelola program dan jadwal supervisi', 'supervisi.instrumen_kelola' => 'Kelola instrumen supervisi'] as $kode => $nama) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'kelompok' => 'Supervisi Akademik', 'deskripsi' => $nama, 'sistem' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izin = DB::table('izin')->where('kode', $kode)->value('id');
            $roles = $kode === 'supervisi.lihat' ? ['administrator', 'pimpinan', 'wakil_pimpinan_kurikulum'] : ['administrator', 'wakil_pimpinan_kurikulum'];
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $peran) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $peran, 'izin_id' => $izin, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $i = InstrumenSupervisi::firstOrCreate(['token_pembuatan' => '00000000-0000-4000-8000-000000000079'], ['nama' => 'Supervisi Akademik SMP - Kurikulum Merdeka', 'aktif' => true]);
        $i->versiInstrumen()->firstOrCreate(['nomor' => 1], ['nama' => $i->nama, 'versi' => 1, 'status' => 'terbit', 'catatan' => 'Instrumen awal dari formulir supervisi sekolah.', 'skala' => InstrumenSupervisiAwal::skala(), 'kelompok' => InstrumenSupervisiAwal::kelompok(), 'diterbitkan_pada' => now()]);
    }
}
