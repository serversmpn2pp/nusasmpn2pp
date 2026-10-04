<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_bundel_pertemuan_humas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agenda_humas_id')->constrained('agenda_humas')->restrictOnDelete();
            $t->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->json('ringkasan');
            $t->char('sha256', 64);
            $t->unsignedBigInteger('ukuran_byte');
            $t->timestamp('created_at');
            $t->index(['agenda_humas_id', 'id'], 'bundel_pertemuan_riwayat');
        });
        DB::table('izin')->updateOrInsert(['kode' => 'agenda_humas.bundel'], [
            'nama' => 'Unduh bundel pertemuan Humas', 'kelompok' => 'Humas', 'deskripsi' => 'Paket notulen, kehadiran, lampiran, dan rekap sesuai izin sumber.',
            'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = DB::table('izin')->where('kode', 'agenda_humas.bundel')->value('id');
        foreach (DB::table('peran')->whereIn('kode', ['administrator', 'wakil_pimpinan_humas', 'pimpinan'])->pluck('id') as $role) {
            DB::table('peran_izin')->insertOrIgnore(['peran_id' => $role, 'izin_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_bundel_pertemuan_humas');
        $ids = DB::table('izin')->where('kode', 'agenda_humas.bundel')->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $ids)->delete();
        DB::table('izin')->whereIn('id', $ids)->delete();
    }
};
