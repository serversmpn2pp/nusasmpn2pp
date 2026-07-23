<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendampingan_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('peringatan_dini_siswa_id')->nullable()->constrained('peringatan_dini_siswa')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('petugas_pegawai_id')->nullable()->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->string('jenis_tindakan', 50);
            $table->date('tanggal_tindak_lanjut');
            $table->text('catatan');
            $table->string('status', 20)->default('dalam_proses');
            $table->text('hasil')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->string('kunci_aktif', 100)->nullable()->unique();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('diperbarui_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['tahun_pelajaran_id', 'status', 'tanggal_tindak_lanjut'], 'pendampingan_tahun_status');
            $table->index(['siswa_id', 'tahun_pelajaran_id'], 'pendampingan_siswa_tahun');
            $table->index(['petugas_pegawai_id', 'status'], 'pendampingan_petugas_status');
        });

        DB::table('izin')->updateOrInsert(
            ['kode' => 'poin_siswa.pendampingan_kelola'],
            [
                'kelompok' => 'Pembinaan dan Poin',
                'nama' => 'Kelola tindak lanjut siswa',
                'deskripsi' => 'Membuat dan menyelesaikan tindak lanjut siswa sesuai cakupan tugas.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $izinId = DB::table('izin')->where('kode', 'poin_siswa.pendampingan_kelola')->value('id');
        $peranIds = DB::table('peran')
            ->whereIn('kode', ['administrator', 'wakil_pimpinan_kesiswaan', 'bk', 'wali_kelas', 'guru_wali'])
            ->pluck('id');

        foreach ($peranIds as $peranId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', 'poin_siswa.pendampingan_kelola')->value('id');
        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }

        Schema::dropIfExists('pendampingan_siswa');
    }
};
