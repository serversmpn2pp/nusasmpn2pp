<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['token_akses']);
        });

        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropConstrainedForeignId('akun_peserta_cbt_id');
            $table->dropColumn(['username', 'kata_sandi', 'token_akses']);
        });

        Schema::dropIfExists('akun_peserta_cbt');

        Schema::table('jenis_ujian_cbt', function (Blueprint $table) {
            $table->dropColumn('tampil_di_kartu_peserta');
        });

        DB::table('jenis_ujian_cbt')->updateOrInsert(
            ['kode' => 'ASESMEN_KELAS'],
            [
                'nama' => 'Asesmen Kelas',
                'deskripsi' => 'Asesmen yang dilaksanakan guru pada jam mengajarnya sendiri tanpa pengaturan panitia dan ruang ujian.',
                'memerlukan_token' => false,
                'dapat_diterapkan_ke_nilai' => true,
                'urutan' => 0,
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('jenis_ujian_cbt')->where('kode', 'ASESMEN_KELAS')->delete();

        Schema::table('jenis_ujian_cbt', function (Blueprint $table) {
            $table->boolean('tampil_di_kartu_peserta')->default(false);
        });

        Schema::create('akun_peserta_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_ujian_cbt_id')->constrained('jenis_ujian_cbt')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('semester', 20);
            $table->foreignId('anggota_kelas_id')->constrained('anggota_kelas')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('nomor_peserta', 80)->unique();
            $table->string('username', 80);
            $table->string('kata_sandi', 40);
            $table->string('kode_qr', 80)->unique();
            $table->string('status', 30)->default('aktif')->index();
            $table->timestamps();

            $table->unique(['jenis_ujian_cbt_id', 'tahun_pelajaran_id', 'semester', 'anggota_kelas_id'], 'akun_peserta_cbt_rangkaian_anggota_unik');
            $table->unique(['jenis_ujian_cbt_id', 'tahun_pelajaran_id', 'semester', 'username'], 'akun_peserta_cbt_rangkaian_username_unik');
            $table->index(['jenis_ujian_cbt_id', 'tahun_pelajaran_id', 'semester'], 'akun_peserta_cbt_rangkaian_index');
        });

        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('akun_peserta_cbt_id')->nullable()->constrained('akun_peserta_cbt')->nullOnDelete()->cascadeOnUpdate();
            $table->string('username', 80)->nullable()->unique();
            $table->string('kata_sandi', 40)->nullable();
            $table->string('token_akses', 40)->nullable()->unique();
        });
    }
};
