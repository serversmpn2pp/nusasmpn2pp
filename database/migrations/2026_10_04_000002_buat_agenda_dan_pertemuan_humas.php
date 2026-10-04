<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'agenda_humas.lihat' => ['Lihat agenda Humas', 'Melihat agenda, kehadiran, notulen, dan tindak lanjut pertemuan Humas.'],
            'agenda_humas.kelola' => ['Kelola agenda Humas', 'Mengelola jadwal, peserta, kehadiran, notulen, dan tindak lanjut pertemuan Humas.'],
        ] as $kode => [$nama, $deskripsi]) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], [
                'nama' => $nama, 'deskripsi' => $deskripsi, 'kelompok' => 'Humas',
                'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $izinId = DB::table('izin')->where('kode', $kode)->value('id');
            $peranIds = DB::table('peran')->whereIn('kode', $kode === 'agenda_humas.lihat'
                ? ['wakil_pimpinan_humas', 'pimpinan'] : ['wakil_pimpinan_humas'])->pluck('id');
            foreach ($peranIds as $peranId) {
                DB::table('peran_izin')->insertOrIgnore([
                    'peran_id' => $peranId, 'izin_id' => $izinId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::create('agenda_humas', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 180);
            $table->string('jenis', 40);
            $table->dateTime('waktu_mulai')->index();
            $table->dateTime('waktu_selesai');
            $table->string('tempat', 180);
            $table->string('tautan_pertemuan', 1000)->nullable();
            $table->string('sasaran', 250)->nullable();
            $table->string('pemimpin', 180)->nullable();
            $table->string('notulis', 180)->nullable();
            $table->text('topik');
            $table->text('pembahasan')->nullable();
            $table->text('keputusan')->nullable();
            $table->string('status', 20)->default('terjadwal')->index();
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamp('diselesaikan_pada')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'waktu_mulai']);
        });

        Schema::create('peserta_pertemuan_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_humas_id')->constrained('agenda_humas')->cascadeOnDelete();
            $table->string('nama', 180);
            $table->string('instansi', 180)->nullable();
            $table->string('peran', 120)->nullable();
            $table->string('status_kehadiran', 20)->default('belum_dicatat');
            $table->string('catatan', 500)->nullable();
            $table->timestamp('hadir_pada')->nullable();
            $table->foreignId('dicatat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->index(['agenda_humas_id', 'status_kehadiran']);
        });

        Schema::create('tindak_lanjut_agenda_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_humas_id')->constrained('agenda_humas')->cascadeOnDelete();
            $table->text('uraian');
            $table->string('penanggung_jawab', 180);
            $table->date('batas_tanggal')->nullable()->index();
            $table->string('status', 20)->default('belum_mulai');
            $table->text('catatan')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('agenda_humas_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_humas_id')->constrained('agenda_humas')->cascadeOnDelete();
            $table->foreignId('dokumen_humas_id')->constrained('dokumen_humas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['agenda_humas_id', 'dokumen_humas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_humas_dokumen');
        Schema::dropIfExists('tindak_lanjut_agenda_humas');
        Schema::dropIfExists('peserta_pertemuan_humas');
        Schema::dropIfExists('agenda_humas');
        $ids = DB::table('izin')->whereIn('kode', ['agenda_humas.lihat', 'agenda_humas.kelola'])->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $ids)->delete();
        DB::table('izin')->whereIn('id', $ids)->delete();
    }
};
