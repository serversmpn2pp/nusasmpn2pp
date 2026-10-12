<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tindak_lanjut_surat_tu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_tu_id')->constrained('surat_tu')->restrictOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('nama_pegawai', 200);
            $table->string('jenis', 20);
            $table->text('instruksi');
            $table->date('batas_tanggal');
            $table->foreignId('pemeriksa_pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('dibuat_oleh_pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->string('status', 30)->default('belum_mulai')->index();
            $table->unsignedInteger('versi')->default(1);
            $table->timestamp('dibaca_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();
            $table->unique(['surat_tu_id', 'pegawai_id']);
            $table->index(['status', 'batas_tanggal']);
        });
        Schema::create('laporan_tindak_lanjut_surat_tu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tindak_lanjut_id')->constrained('tindak_lanjut_surat_tu')->restrictOnDelete();
            $table->unsignedInteger('nomor');
            $table->text('hasil');
            $table->timestamp('diajukan_pada')->nullable();
            $table->string('keputusan', 20)->nullable();
            $table->text('catatan_pemeriksa')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('diperiksa_oleh_pengguna_id')->nullable()->constrained('pengguna')->restrictOnDelete();
            $table->timestamp('diperiksa_pada')->nullable();
            $table->timestamps();
            $table->unique(['tindak_lanjut_id', 'nomor']);
        });
        Schema::create('bukti_tindak_lanjut_surat_tu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan_tindak_lanjut_surat_tu')->restrictOnDelete();
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 100);
            $table->unsignedBigInteger('ukuran_file');
            $table->char('sha256', 64);
            $table->timestamps();
        });
        Schema::create('riwayat_tindak_lanjut_surat_tu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tindak_lanjut_id')->constrained('tindak_lanjut_surat_tu')->restrictOnDelete();
            $table->foreignId('pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->string('aksi', 30);
            $table->unsignedInteger('versi');
            $table->text('catatan')->nullable();
            $table->json('data');
            $table->timestamps();
        });
        DB::table('izin')->updateOrInsert(['kode' => 'surat.disposisi'], [
            'nama' => 'Disposisi dan pemeriksaan surat masuk', 'kelompok' => 'Persuratan',
            'deskripsi' => 'Memberikan disposisi surat masuk dan memeriksa hasil tindak lanjutnya.',
            'sistem' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $izin = DB::table('izin')->where('kode', 'surat.disposisi')->value('id');
        foreach (DB::table('peran')->whereIn('kode', ['administrator', 'pimpinan'])->pluck('id') as $peran) {
            DB::table('peran_izin')->insertOrIgnore(['peran_id' => $peran, 'izin_id' => $izin, 'created_at' => now(), 'updated_at' => now()]);
        }

        // Surat tugas lama ikut tersedia tanpa mengirim ulang notifikasi pengesahan.
        DB::table('penugasan_surat_tu as p')->join('surat_tu as s', 's.id', '=', 'p.surat_tu_id')
            ->where('s.jenis', 'tugas')->whereIn('s.status', ['disahkan', 'dikirim'])->whereNotNull('s.disahkan_pada')
            ->select('p.*', 's.uraian_tugas', 's.selesai_tugas', 's.pengesah_pengguna_id', 's.disahkan_oleh_pengguna_id', 's.dibuat_oleh_pengguna_id')
            ->orderBy('p.id')->chunk(100, function ($penugasan) {
                foreach ($penugasan as $p) {
                    DB::table('tindak_lanjut_surat_tu')->insert([
                        'surat_tu_id' => $p->surat_tu_id, 'pegawai_id' => $p->pegawai_id, 'nama_pegawai' => $p->nama_pegawai,
                        'jenis' => 'tugas', 'instruksi' => $p->uraian_tugas ?? 'Laksanakan penugasan sesuai surat.',
                        'batas_tanggal' => $p->selesai_tugas ?? today()->toDateString(),
                        'pemeriksa_pengguna_id' => $p->disahkan_oleh_pengguna_id ?? $p->pengesah_pengguna_id,
                        'dibuat_oleh_pengguna_id' => $p->dibuat_oleh_pengguna_id, 'dibaca_pada' => $p->dibaca_pada,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_tindak_lanjut_surat_tu');
        Schema::dropIfExists('bukti_tindak_lanjut_surat_tu');
        Schema::dropIfExists('laporan_tindak_lanjut_surat_tu');
        Schema::dropIfExists('tindak_lanjut_surat_tu');
        $id = DB::table('izin')->where('kode', 'surat.disposisi')->value('id');
        DB::table('peran_izin')->where('izin_id', $id)->delete();
        DB::table('izin')->where('id', $id)->delete();
    }
};
