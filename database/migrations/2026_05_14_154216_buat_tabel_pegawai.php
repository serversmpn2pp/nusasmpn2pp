<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migration.
     */
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();

            // Identitas utama
            $table->string('nama_lengkap');
            $table->string('nip')->nullable()->unique();
            $table->string('nuptk')->nullable()->unique();
            $table->string('nik')->nullable()->unique();

            // Data pribadi
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->text('alamat')->nullable();

            // Kontak
            $table->string('email')->nullable()->unique();
            $table->string('no_hp')->nullable();

            // Data kepegawaian
            $table->string('status_kepegawaian')->nullable();
            $table->string('golongan')->nullable();
            $table->date('tanggal_mulai_kerja')->nullable();
            $table->date('tanggal_mulai_bertugas')->nullable();
            $table->string('jenis_pegawai')->nullable();
            $table->string('jabatan_utama')->nullable();
            $table->string('sumber_gaji')->nullable();

            // Pendidikan terakhir
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('jurusan_pendidikan')->nullable();
            $table->integer('tahun_lulus')->nullable();

            // Tambahan
            $table->text('keterangan')->nullable();
            $table->boolean('aktif')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};
