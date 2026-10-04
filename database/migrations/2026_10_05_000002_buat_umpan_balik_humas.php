<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umpan_balik_humas', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete();
            $t->foreignId('agenda_humas_id')->nullable()->constrained('agenda_humas')->restrictOnDelete();
            $t->string('judul', 180);
            $t->text('pengantar');
            $t->string('penanggung_jawab', 180);
            $t->string('cakupan', 20);
            $t->unsignedSmallInteger('tingkat')->nullable();
            $t->json('kelas_ids')->nullable();
            $t->timestamp('mulai_pada');
            $t->timestamp('selesai_pada');
            $t->string('status', 20)->default('draf')->index();
            $t->timestamp('dibuka_pada')->nullable();
            $t->unsignedInteger('versi')->default(0);
            $t->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('pertanyaan_umpan_balik_humas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('umpan_balik_humas_id')->constrained('umpan_balik_humas')->restrictOnDelete();
            $t->unsignedSmallInteger('urutan');
            $t->string('jenis', 15);
            $t->text('teks');
            $t->boolean('wajib')->default(true);
            $t->timestamps();
            $t->index(['umpan_balik_humas_id', 'urutan'], 'pertanyaan_umpan_urutan');
        });
        Schema::create('sasaran_umpan_balik_humas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('umpan_balik_humas_id')->constrained('umpan_balik_humas')->restrictOnDelete();
            $t->foreignId('orang_tua_wali_id')->nullable()->constrained('orang_tua_wali')->nullOnDelete();
            $t->json('siswa_ids');
            $t->uuid('token_pengiriman')->nullable()->unique();
            $t->timestamp('dikirim_pada')->nullable();
            $t->timestamps();
            $t->unique(['umpan_balik_humas_id', 'orang_tua_wali_id'], 'sasaran_umpan_wali');
            $t->index(['orang_tua_wali_id', 'dikirim_pada'], 'sasaran_umpan_respons');
        });
        Schema::create('jawaban_umpan_balik_humas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sasaran_umpan_balik_humas_id')->constrained('sasaran_umpan_balik_humas', indexName: 'jawaban_umpan_sasaran_fk')->restrictOnDelete();
            $t->foreignId('pertanyaan_umpan_balik_humas_id')->constrained('pertanyaan_umpan_balik_humas', indexName: 'jawaban_umpan_pertanyaan_fk')->restrictOnDelete();
            $t->unsignedTinyInteger('nilai')->nullable();
            $t->text('teks')->nullable();
            $t->unique(['sasaran_umpan_balik_humas_id', 'pertanyaan_umpan_balik_humas_id'], 'jawaban_umpan_unik');
            $t->index(['pertanyaan_umpan_balik_humas_id', 'nilai'], 'jawaban_umpan_rekap');
        });
        Schema::create('tindak_lanjut_umpan_balik_humas', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->foreignId('umpan_balik_humas_id')->constrained('umpan_balik_humas')->restrictOnDelete();
            $t->foreignId('pertanyaan_umpan_balik_humas_id')->nullable()->constrained('pertanyaan_umpan_balik_humas', indexName: 'tindak_umpan_pertanyaan_fk')->restrictOnDelete();
            $t->text('uraian');
            $t->string('penanggung_jawab', 180);
            $t->date('batas_tanggal');
            $t->string('status', 20)->default('belum_mulai');
            $t->text('hasil')->nullable();
            $t->boolean('bagikan_ringkasan')->default(false);
            $t->text('ringkasan_publik')->nullable();
            $t->timestamp('selesai_pada')->nullable();
            $t->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna', indexName: 'tindak_umpan_pembuat_fk')->nullOnDelete();
            $t->timestamps();
            $t->index(['umpan_balik_humas_id', 'status'], 'tindak_umpan_status');
        });
        Schema::create('riwayat_umpan_balik_humas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('umpan_balik_humas_id')->constrained('umpan_balik_humas')->restrictOnDelete();
            $t->string('aksi', 180);
            $t->unsignedInteger('versi');
            $t->text('catatan')->nullable();
            $t->json('snapshot');
            $t->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamp('created_at');
            $t->index(['umpan_balik_humas_id', 'id'], 'riwayat_umpan_form');
        });
        foreach (['lihat' => 'Lihat rekap umpan balik orang tua', 'kelola' => 'Kelola formulir dan tindak lanjut umpan balik'] as $kode => $nama) {
            DB::table('izin')->updateOrInsert(['kode' => 'umpan_balik_humas.'.$kode], ['nama' => $nama, 'kelompok' => 'Humas', 'deskripsi' => 'Evaluasi orang tua, rekap tanpa nama akun, dan tindak lanjut.', 'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('izin')->where('kode', 'umpan_balik_humas.'.$kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', ['administrator', 'wakil_pimpinan_humas', ...($kode === 'lihat' ? ['pimpinan'] : [])])->pluck('id') as $role) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $role, 'izin_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (['riwayat_umpan_balik_humas', 'tindak_lanjut_umpan_balik_humas', 'jawaban_umpan_balik_humas', 'sasaran_umpan_balik_humas', 'pertanyaan_umpan_balik_humas', 'umpan_balik_humas'] as $table) {
            Schema::dropIfExists($table);
        }
        $ids = DB::table('izin')->whereIn('kode', ['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola'])->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $ids)->delete();
        DB::table('izin')->whereIn('id', $ids)->delete();
    }
};
