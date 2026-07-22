<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanksi_poin_siswa', function (Blueprint $table) {
            $table->timestamp('mulai_diproses_pada')->nullable()->after('terpicu_pada');
            $table->date('batas_pelaksanaan')->nullable()->after('mulai_diproses_pada');
            $table->text('hasil_pelaksanaan')->nullable()->after('catatan');
            $table->foreignId('diperbarui_oleh_pengguna_id')->nullable()->after('hasil_pelaksanaan')
                ->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->index(['status', 'batas_pelaksanaan'], 'sanksi_poin_status_batas');
        });

        Schema::create('riwayat_sanksi_poin_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sanksi_poin_siswa_id')->constrained('sanksi_poin_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('jenis_kegiatan', 50);
            $table->string('judul', 180);
            $table->string('status_sebelum', 30)->nullable();
            $table->string('status_sesudah', 30)->nullable();
            $table->text('catatan')->nullable();
            $table->json('data_tambahan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('terjadi_pada');
            $table->timestamps();

            $table->index(['sanksi_poin_siswa_id', 'terjadi_pada'], 'riwayat_sanksi_waktu');
        });

        Schema::create('bukti_pelaksanaan_sanksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sanksi_poin_siswa_id')->constrained('sanksi_poin_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('nama_file_asli');
            $table->string('lokasi_file');
            $table->string('tipe_file', 120)->nullable();
            $table->unsignedBigInteger('ukuran_file')->default(0);
            $table->string('keterangan', 500)->nullable();
            $table->foreignId('diunggah_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('diunggah_pada');
            $table->timestamps();

            $table->index(['sanksi_poin_siswa_id', 'diunggah_pada'], 'bukti_sanksi_waktu');
        });

        $izinId = DB::table('izin')->insertGetId([
            'kelompok' => 'Pembinaan dan Poin',
            'nama' => 'Kelola pelaksanaan sanksi',
            'kode' => 'poin_siswa.sanksi_kelola',
            'deskripsi' => 'Menugaskan petugas, memproses, dan menyelesaikan sanksi poin siswa.',
            'sistem' => true,
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $peranIds = DB::table('peran')
            ->whereIn('kode', ['administrator', 'wakil_pimpinan_kesiswaan', 'bk'])
            ->pluck('id');
        foreach ($peranIds as $peranId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('sanksi_poin_siswa')->orderBy('id')->chunkById(200, function ($daftarSanksi) {
            foreach ($daftarSanksi as $sanksi) {
                DB::table('riwayat_sanksi_poin_siswa')->insert([
                    'sanksi_poin_siswa_id' => $sanksi->id,
                    'jenis_kegiatan' => 'sanksi_terpicu',
                    'judul' => 'Sanksi terbentuk dari akumulasi poin',
                    'status_sebelum' => null,
                    'status_sesudah' => $sanksi->status,
                    'catatan' => 'Riwayat awal dibuat saat pemutakhiran sistem pelaksanaan sanksi.',
                    'data_tambahan' => json_encode(['poin_saat_terpicu' => $sanksi->poin_saat_terpicu]),
                    'dibuat_oleh_pengguna_id' => null,
                    'terjadi_pada' => $sanksi->terpicu_pada ?? $sanksi->created_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', 'poin_siswa.sanksi_kelola')->value('id');
        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }

        Schema::dropIfExists('bukti_pelaksanaan_sanksi');
        Schema::dropIfExists('riwayat_sanksi_poin_siswa');

        Schema::table('sanksi_poin_siswa', function (Blueprint $table) {
            $table->dropIndex('sanksi_poin_status_batas');
            $table->dropConstrainedForeignId('diperbarui_oleh_pengguna_id');
            $table->dropColumn(['mulai_diproses_pada', 'batas_pelaksanaan', 'hasil_pelaksanaan']);
        });
    }
};
