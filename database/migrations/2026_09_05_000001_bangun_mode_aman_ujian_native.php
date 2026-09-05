<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_cbt', function (Blueprint $table) {
            $table->boolean('blokir_tangkapan_layar')->default(true)->after('wajib_fullscreen');
            $table->unsignedSmallInteger('toleransi_pindah_aplikasi_detik')->default(3)->after('blokir_tangkapan_layar');
            $table->unsignedSmallInteger('batas_pindah_aplikasi')->default(3)->after('toleransi_pindah_aplikasi_detik');
            $table->string('tindakan_pindah_aplikasi', 20)->default('catat')->after('batas_pindah_aplikasi');
        });

        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah_pindah_aplikasi')->default(0)->after('menit_tersisa');
            $table->unsignedInteger('durasi_di_luar_aplikasi_detik')->default(0)->after('jumlah_pindah_aplikasi');
            $table->timestamp('heartbeat_terakhir_pada')->nullable()->after('durasi_di_luar_aplikasi_detik');
            $table->timestamp('ditahan_mode_aman_pada')->nullable()->after('heartbeat_terakhir_pada');
            $table->timestamp('dibuka_mode_aman_pada')->nullable()->after('ditahan_mode_aman_pada');
            $table->foreignId('dibuka_mode_aman_oleh_pengguna_id')
                ->nullable()
                ->after('dibuka_mode_aman_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('aktivitas_keamanan_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_ujian_cbt_id')
                ->constrained('peserta_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('jenis', 40);
            $table->timestamp('mulai_pada');
            $table->timestamp('selesai_pada')->nullable();
            $table->unsignedInteger('durasi_detik')->default(0);
            $table->boolean('dihitung')->default(false);
            $table->string('perangkat', 120)->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['peserta_ujian_cbt_id', 'jenis', 'selesai_pada'],
                'aktivitas_keamanan_peserta_jenis_selesai_index'
            );
        });

        // Ujian terpusat memakai kebijakan ketat secara baku. Asesmen kelas tetap
        // dapat memilih hanya mencatat atau menahan peserta dari form pengaturan.
        DB::table('ujian_cbt')->where('alur', 'terpusat')->update([
            'batasi_satu_perangkat' => true,
            'deteksi_pindah_tab' => true,
            'wajib_fullscreen' => true,
            'blokir_tangkapan_layar' => true,
            'toleransi_pindah_aplikasi_detik' => 3,
            'batas_pindah_aplikasi' => 3,
            'tindakan_pindah_aplikasi' => 'tahan',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas_keamanan_ujian_cbt');

        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropForeign(['dibuka_mode_aman_oleh_pengguna_id']);
            $table->dropColumn([
                'jumlah_pindah_aplikasi',
                'durasi_di_luar_aplikasi_detik',
                'heartbeat_terakhir_pada',
                'ditahan_mode_aman_pada',
                'dibuka_mode_aman_pada',
                'dibuka_mode_aman_oleh_pengguna_id',
            ]);
        });

        Schema::table('ujian_cbt', function (Blueprint $table) {
            $table->dropColumn([
                'blokir_tangkapan_layar',
                'toleransi_pindah_aplikasi_detik',
                'batas_pindah_aplikasi',
                'tindakan_pindah_aplikasi',
            ]);
        });
    }
};
