<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_komite_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_komite_humas_id')->constrained('periode_komite_humas')->restrictOnDelete();
            $table->uuid('token_pembuatan')->unique();
            $table->string('nama', 180);
            $table->text('tujuan');
            $table->text('target_hasil');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->index();
            $table->foreignId('pengurus_komite_humas_id')->nullable()->constrained('pengurus_komite_humas')->restrictOnDelete();
            $table->string('status', 20)->default('rencana')->index();
            $table->text('capaian')->nullable();
            $table->text('catatan_evaluasi')->nullable();
            $table->timestamp('diselesaikan_pada')->nullable();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('program_komite_humas_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_komite_humas_id')->constrained('program_komite_humas')->restrictOnDelete();
            $table->foreignId('agenda_humas_id')->constrained('agenda_humas')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['program_komite_humas_id', 'agenda_humas_id'], 'program_komite_agenda_unik');
        });
        Schema::create('riwayat_program_komite_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_komite_humas_id')->constrained('program_komite_humas')->restrictOnDelete();
            $table->unsignedInteger('versi');
            $table->string('aksi', 80);
            $table->json('snapshot');
            $table->text('catatan_perubahan')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
        foreach (['komite_humas.lihat' => ['Lihat administrasi komite sekolah', 'Melihat kepengurusan, SK, program kerja, dan riwayat komite. Rincian rapat mengikuti izin agenda.'],
            'komite_humas.kelola' => ['Kelola administrasi komite sekolah', 'Mengelola kepengurusan, kontak privat, SK, dan program kerja. Pengaitan rapat juga memerlukan izin kelola agenda.']] as $kode => [$nama, $deskripsi]) {
            DB::table('izin')->where('kode', $kode)->update(['nama' => $nama, 'deskripsi' => $deskripsi, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_program_komite_humas');
        Schema::dropIfExists('program_komite_humas_agenda');
        Schema::dropIfExists('program_komite_humas');
        foreach (['komite_humas.lihat' => 'Lihat kepengurusan komite sekolah', 'komite_humas.kelola' => 'Kelola kepengurusan komite sekolah'] as $kode => $nama) {
            DB::table('izin')->where('kode', $kode)->update(['nama' => $nama, 'deskripsi' => 'Susunan pengurus, masa bakti, SK, dan riwayat kepengurusan komite sekolah.', 'updated_at' => now()]);
        }
    }
};
