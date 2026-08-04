<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_pelanggaran_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_pembinaan_siswa_id')->nullable()->constrained('kategori_pembinaan_siswa')->cascadeOnUpdate()->nullOnDelete();
            $table->string('kode', 20)->unique();
            $table->text('nama');
            $table->string('tingkat', 20);
            $table->unsignedSmallInteger('poin');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['tingkat', 'aktif']);
        });

        Schema::create('aturan_sanksi_poin', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('batas_poin')->unique();
            $table->string('nama', 120);
            $table->text('deskripsi');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('penugasan_guru_wali_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('guru_wali_pegawai_id')->constrained('pegawai')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->string('nomor_sk', 100)->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['guru_wali_pegawai_id', 'aktif']);
            $table->index(['siswa_id', 'tanggal_mulai']);
        });

        DB::statement('CREATE UNIQUE INDEX satu_guru_wali_aktif_per_siswa ON penugasan_guru_wali_siswa (siswa_id) WHERE aktif = true');

        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->string('jenis_laporan', 30)->default('pembinaan')->after('nomor_laporan');
            $table->string('status_verifikasi', 40)->default('tidak_perlu')->after('status');
            $table->unsignedSmallInteger('total_poin')->default(0)->after('status_verifikasi');
            $table->foreignId('wali_kelas_pegawai_id')->nullable()->after('pelapor_pegawai_id')->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('guru_wali_pegawai_id')->nullable()->after('wali_kelas_pegawai_id')->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('poin_ditetapkan_pada')->nullable()->after('total_poin');

            $table->index(['jenis_laporan', 'status_verifikasi']);
            $table->index(['guru_wali_pegawai_id', 'status_verifikasi']);
        });

        Schema::create('butir_pelanggaran_laporan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_pembinaan_siswa_id')->constrained('laporan_pembinaan_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('jenis_pelanggaran_siswa_id')->constrained('jenis_pelanggaran_siswa')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('kode_pelanggaran', 20);
            $table->text('nama_pelanggaran');
            $table->string('tingkat', 20);
            $table->unsignedSmallInteger('poin');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['laporan_pembinaan_siswa_id', 'jenis_pelanggaran_siswa_id'], 'butir_pelanggaran_unik');
        });

        Schema::create('verifikasi_bk_pelanggaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_pembinaan_siswa_id')->constrained('laporan_pembinaan_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('bk_pegawai_id')->nullable()->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->string('hasil', 30);
            $table->text('catatan')->nullable();
            $table->timestamp('diverifikasi_pada');
            $table->timestamps();

            $table->index(['laporan_pembinaan_siswa_id', 'diverifikasi_pada'], 'verifikasi_bk_laporan_tanggal');
        });

        Schema::create('persetujuan_pelanggaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_pembinaan_siswa_id')->constrained('laporan_pembinaan_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('jenis_persetujuan', 40);
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->string('keputusan', 30);
            $table->text('catatan')->nullable();
            $table->timestamp('diputuskan_pada');
            $table->timestamps();

            $table->unique(['laporan_pembinaan_siswa_id', 'jenis_persetujuan'], 'persetujuan_jenis_unik');
            $table->index(['pegawai_id', 'keputusan']);
        });

        Schema::create('pengurangan_poin_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('tanggal_kegiatan');
            $table->string('jenis_kegiatan', 160);
            $table->text('deskripsi')->nullable();
            $table->unsignedSmallInteger('poin_pengurangan');
            $table->string('bukti', 255)->nullable();
            $table->string('status', 30)->default('diajukan');
            $table->foreignId('diajukan_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('disetujui_oleh_pegawai_id')->nullable()->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->text('catatan_keputusan')->nullable();
            $table->timestamps();

            $table->index(['siswa_id', 'tahun_pelajaran_id']);
            $table->index(['status', 'tanggal_kegiatan']);
        });

        Schema::create('transaksi_poin_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('laporan_pembinaan_siswa_id')->nullable()->constrained('laporan_pembinaan_siswa')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('pengurangan_poin_siswa_id')->nullable()->constrained('pengurangan_poin_siswa')->cascadeOnUpdate()->nullOnDelete();
            $table->string('kunci_sumber', 100)->unique();
            $table->string('jenis', 30);
            $table->integer('poin');
            $table->text('keterangan');
            $table->timestamp('tercatat_pada');
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['siswa_id', 'tahun_pelajaran_id', 'tercatat_pada'], 'transaksi_poin_siswa_tahun');
        });

        Schema::create('sanksi_poin_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('aturan_sanksi_poin_id')->constrained('aturan_sanksi_poin')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('poin_saat_terpicu');
            $table->string('status', 30)->default('menunggu');
            $table->timestamp('terpicu_pada');
            $table->timestamp('dilaksanakan_pada')->nullable();
            $table->foreignId('petugas_pegawai_id')->nullable()->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'tahun_pelajaran_id', 'aturan_sanksi_poin_id'], 'sanksi_poin_siswa_unik');
            $table->index(['status', 'terpicu_pada']);
        });

        $this->isiJenisPelanggaran();
        $this->isiAturanSanksi();
        $this->isiPeranDanIzin();
    }

    public function down(): void
    {
        $kodeIzin = array_column($this->daftarIzin(), 'kode');
        $izinIds = DB::table('izin')->whereIn('kode', $kodeIzin)->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $izinIds)->delete();
        DB::table('izin')->whereIn('id', $izinIds)->delete();

        $peranGuruWaliId = DB::table('peran')->where('kode', 'guru_wali')->value('id');
        if ($peranGuruWaliId) {
            DB::table('pengguna_peran')->where('peran_id', $peranGuruWaliId)->delete();
            DB::table('peran')->where('id', $peranGuruWaliId)->delete();
        }

        Schema::dropIfExists('sanksi_poin_siswa');
        Schema::dropIfExists('transaksi_poin_siswa');
        Schema::dropIfExists('pengurangan_poin_siswa');
        Schema::dropIfExists('persetujuan_pelanggaran');
        Schema::dropIfExists('verifikasi_bk_pelanggaran');
        Schema::dropIfExists('butir_pelanggaran_laporan');

        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->dropIndex(['jenis_laporan', 'status_verifikasi']);
            $table->dropIndex(['guru_wali_pegawai_id', 'status_verifikasi']);
            $table->dropConstrainedForeignId('guru_wali_pegawai_id');
            $table->dropConstrainedForeignId('wali_kelas_pegawai_id');
            $table->dropColumn(['jenis_laporan', 'status_verifikasi', 'total_poin', 'poin_ditetapkan_pada']);
        });

        DB::statement('DROP INDEX IF EXISTS satu_guru_wali_aktif_per_siswa');
        Schema::dropIfExists('penugasan_guru_wali_siswa');
        Schema::dropIfExists('aturan_sanksi_poin');
        Schema::dropIfExists('jenis_pelanggaran_siswa');
    }

    private function isiJenisPelanggaran(): void
    {
        $kategoriIds = DB::table('kategori_pembinaan_siswa')->pluck('id', 'kode');
        $waktu = now();

        $baris = [];
        foreach ($this->daftarPelanggaran() as $urutan => [$kode, $nama, $tingkat, $poin, $kategori]) {
            $baris[] = [
                'kategori_pembinaan_siswa_id' => $kategoriIds[$kategori] ?? $kategoriIds['PELANGGARAN_TATA_TERTIB'] ?? null,
                'kode' => $kode,
                'nama' => $nama,
                'tingkat' => $tingkat,
                'poin' => $poin,
                'urutan' => $urutan + 1,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ];
        }

        DB::table('jenis_pelanggaran_siswa')->insert($baris);
    }

    private function isiAturanSanksi(): void
    {
        $waktu = now();
        $aturan = [
            [25, 'Teguran Lisan', 'Teguran lisan dan pencatatan pembinaan.'],
            [50, 'Teguran Tertulis', 'Teguran tertulis kepada peserta didik.'],
            [75, 'Pemanggilan Orang Tua', 'Pemanggilan orang tua atau wali peserta didik.'],
            [150, 'Surat Peringatan 1', 'Belajar di rumah selama 3 hari, mendampingi pekerjaan orang tua, dan menyusun laporan kegiatan.'],
            [200, 'Surat Peringatan 2', 'Belajar di rumah selama 1 minggu, mendampingi pekerjaan orang tua, dan menyusun laporan kegiatan.'],
            [250, 'Surat Peringatan 3', 'Belajar di rumah selama 2 minggu, mendampingi pekerjaan orang tua, dan menyusun laporan kegiatan.'],
            [300, 'Keputusan Dewan Guru', 'Tidak naik kelas atau tidak dinyatakan lulus sesuai hasil rapat dewan guru.'],
        ];

        foreach ($aturan as $urutan => [$batas, $nama, $deskripsi]) {
            DB::table('aturan_sanksi_poin')->insert([
                'batas_poin' => $batas,
                'nama' => $nama,
                'deskripsi' => $deskripsi,
                'urutan' => $urutan + 1,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }
    }

    private function isiPeranDanIzin(): void
    {
        $waktu = now();

        DB::table('peran')->updateOrInsert(
            ['kode' => 'guru_wali'],
            [
                'nama' => 'Guru Wali',
                'deskripsi' => 'Mendampingi siswa lintas kelas selama bersekolah dan ikut menyetujui penetapan pelanggaran.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        foreach ($this->daftarIzin() as $izin) {
            DB::table('izin')->updateOrInsert(
                ['kode' => $izin['kode']],
                array_merge($izin, [
                    'sistem' => true,
                    'aktif' => true,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]),
            );
        }

        $peta = [
            'administrator' => array_column($this->daftarIzin(), 'kode'),
            'pegawai' => ['poin_siswa.lapor'],
            'pimpinan' => ['poin_siswa.lihat'],
            'wakil_pimpinan_kesiswaan' => array_column($this->daftarIzin(), 'kode'),
            'guru_mapel' => ['poin_siswa.lapor'],
            'wali_kelas' => ['poin_siswa.lapor', 'poin_siswa.lihat', 'poin_siswa.menyetujui'],
            'guru_wali' => ['poin_siswa.lapor', 'poin_siswa.lihat', 'poin_siswa.menyetujui', 'guru_wali.lihat'],
            'bk' => ['poin_siswa.lapor', 'poin_siswa.lihat', 'poin_siswa.verifikasi_bk', 'poin_siswa.reward_kelola'],
        ];

        $peranIds = DB::table('peran')->pluck('id', 'kode');
        $izinIds = DB::table('izin')->pluck('id', 'kode');

        foreach ($peta as $kodePeran => $kodeIzin) {
            $peranId = $peranIds[$kodePeran] ?? null;
            if (! $peranId) {
                continue;
            }

            foreach ($kodeIzin as $kode) {
                if (! isset($izinIds[$kode])) {
                    continue;
                }

                DB::table('peran_izin')->insertOrIgnore([
                    'peran_id' => $peranId,
                    'izin_id' => $izinIds[$kode],
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
            }
        }
    }

    private function daftarIzin(): array
    {
        return [
            ['kelompok' => 'Pembinaan dan Poin', 'nama' => 'Laporkan kejadian siswa', 'kode' => 'poin_siswa.lapor', 'deskripsi' => 'Membuat laporan kejadian siswa untuk diperiksa BK.'],
            ['kelompok' => 'Pembinaan dan Poin', 'nama' => 'Lihat poin siswa', 'kode' => 'poin_siswa.lihat', 'deskripsi' => 'Melihat laporan dan rekap poin sesuai cakupan tugas.'],
            ['kelompok' => 'Pembinaan dan Poin', 'nama' => 'Verifikasi fakta oleh BK', 'kode' => 'poin_siswa.verifikasi_bk', 'deskripsi' => 'Memeriksa fakta laporan pelanggaran sebagai BK.'],
            ['kelompok' => 'Pembinaan dan Poin', 'nama' => 'Setujui pelanggaran', 'kode' => 'poin_siswa.menyetujui', 'deskripsi' => 'Memberi persetujuan sebagai wali kelas atau guru wali.'],
            ['kelompok' => 'Pembinaan dan Poin', 'nama' => 'Kelola reward poin', 'kode' => 'poin_siswa.reward_kelola', 'deskripsi' => 'Mengusulkan dan mengelola pengurangan poin siswa.'],
            ['kelompok' => 'Pembinaan dan Poin', 'nama' => 'Putuskan musyawarah', 'kode' => 'poin_siswa.putus_konflik', 'deskripsi' => 'Memberi keputusan pengganti atau musyawarah sebagai Wakil Kesiswaan.'],
            ['kelompok' => 'Pembinaan dan Poin', 'nama' => 'Kelola aturan poin', 'kode' => 'poin_siswa.pengaturan', 'deskripsi' => 'Mengelola jenis pelanggaran dan aturan sanksi.'],
            ['kelompok' => 'Guru Wali', 'nama' => 'Lihat siswa wali', 'kode' => 'guru_wali.lihat', 'deskripsi' => 'Melihat siswa yang menjadi tanggung jawab guru wali.'],
            ['kelompok' => 'Guru Wali', 'nama' => 'Kelola penugasan guru wali', 'kode' => 'guru_wali.kelola', 'deskripsi' => 'Menentukan guru wali untuk setiap siswa.'],
        ];
    }

    private function daftarPelanggaran(): array
    {
        return [
            ['R001', 'Terlambat datang ke sekolah (lewat pukul 07.10 WIB) dan dijemput orang tua', 'ringan', 15, 'KEHADIRAN'],
            ['R002', 'Tidak memakai atribut sekolah lengkap', 'ringan', 5, 'SERAGAM_ATRIBUT'],
            ['R003', 'Tidak melaksanakan piket kelas sesuai jadwal', 'ringan', 5, 'KEDISIPLINAN'],
            ['R004', 'Rambut tidak rapi atau tidak sesuai ketentuan sekolah', 'ringan', 5, 'SERAGAM_ATRIBUT'],
            ['R005', 'Tidak membawa perlengkapan belajar', 'ringan', 10, 'AKADEMIK'],
            ['R006', 'Membuang sampah sembarangan', 'ringan', 10, 'KEDISIPLINAN'],
            ['R007', 'Tidak mengikuti kegiatan keagamaan tanpa alasan yang sah', 'ringan', 10, 'KEDISIPLINAN'],
            ['R008', 'Berkata tidak sopan atau bicara kasar ringan', 'ringan', 15, 'SIKAP_PERILAKU'],
            ['R009', 'Tidak memakai seragam sesuai jadwal yang ditentukan', 'ringan', 10, 'SERAGAM_ATRIBUT'],
            ['R010', 'Tidak memakai sepatu atau kaus kaki sesuai ketentuan', 'ringan', 5, 'SERAGAM_ATRIBUT'],
            ['R011', 'Tidak membawa buku penghubung atau buku agenda jika diwajibkan', 'ringan', 5, 'KEDISIPLINAN'],
            ['R012', 'Keluar kelas tanpa izin guru', 'ringan', 10, 'KEDISIPLINAN'],
            ['R013', 'Bermain atau membuat keributan saat pembelajaran berlangsung', 'ringan', 10, 'SIKAP_PERILAKU'],
            ['R014', 'Tidur di kelas tanpa alasan yang dapat diterima', 'ringan', 10, 'SIKAP_PERILAKU'],
            ['R015', 'Makan atau minum di dalam kelas saat pembelajaran tanpa izin guru', 'ringan', 5, 'SIKAP_PERILAKU'],
            ['R016', 'Mencoret meja, kursi, dinding, atau fasilitas sekolah', 'ringan', 10, 'KEDISIPLINAN'],
            ['R017', 'Tidak mengikuti upacara bendera atau apel tanpa alasan yang sah', 'ringan', 15, 'KEHADIRAN'],
            ['R018', 'Tidak menjaga kebersihan dan kerapian kelas', 'ringan', 5, 'KEDISIPLINAN'],
            ['R019', 'Membawa mainan atau benda yang mengganggu pembelajaran', 'ringan', 10, 'KEDISIPLINAN'],
            ['R020', 'Tidak mengumpulkan tugas tepat waktu tanpa alasan yang sah', 'ringan', 5, 'AKADEMIK'],
            ['R021', 'Berada di kantin atau tempat lain saat jam pelajaran tanpa izin', 'ringan', 15, 'KEHADIRAN'],
            ['R022', 'Bersenda gurau berlebihan hingga mengganggu ketertiban', 'ringan', 10, 'SIKAP_PERILAKU'],
            ['R023', 'Tidak memberi salam atau tidak menunjukkan sikap hormat kepada guru dan tenaga kependidikan', 'ringan', 5, 'SIKAP_PERILAKU'],
            ['R024', 'Memalsukan tanda tangan orang tua pada surat atau tugas ringan', 'ringan', 15, 'SIKAP_PERILAKU'],
            ['S001', 'Bolos pelajaran', 'sedang', 20, 'KEHADIRAN'],
            ['S002', 'Keluar lingkungan sekolah tanpa izin pada jam pelajaran', 'sedang', 25, 'KEHADIRAN'],
            ['S003', 'Tidak mengikuti upacara bendera atau apel tanpa alasan yang sah (pelanggaran sedang)', 'sedang', 20, 'KEHADIRAN'],
            ['S004', 'Membawa HP ke sekolah tanpa izin', 'sedang', 25, 'KEDISIPLINAN'],
            ['S005', 'Menyontek atau membantu teman menyontek saat ujian', 'sedang', 30, 'AKADEMIK'],
            ['S006', 'Merusak fasilitas sekolah ringan', 'sedang', 30, 'KEDISIPLINAN'],
            ['S007', 'Tidak mengikuti kegiatan wajib sekolah tanpa alasan yang sah', 'sedang', 25, 'KEDISIPLINAN'],
            ['S008', 'Berbohong kepada guru atau tenaga kependidikan', 'sedang', 30, 'SIKAP_PERILAKU'],
            ['S009', 'Menggunakan HP saat pembelajaran tanpa izin', 'sedang', 50, 'KEDISIPLINAN'],
            ['S010', 'Berperilaku tidak sopan berat kepada guru, tenaga kependidikan, atau tamu sekolah', 'sedang', 50, 'SIKAP_PERILAKU'],
            ['S011', 'Membawa atau menyebarkan materi yang tidak pantas di lingkungan sekolah', 'sedang', 40, 'SIKAP_PERILAKU'],
            ['S012', 'Mengganggu proses belajar mengajar secara berulang', 'sedang', 25, 'SIKAP_PERILAKU'],
            ['S013', 'Memprovokasi atau menghasut teman hingga menimbulkan keributan', 'sedang', 40, 'KONFLIK_SISWA'],
            ['S014', 'Membuat kegaduhan yang menyebabkan pembelajaran terganggu', 'sedang', 20, 'SIKAP_PERILAKU'],
            ['S015', 'Memalsukan surat izin, tanda tangan orang tua, atau dokumen sekolah', 'sedang', 50, 'SIKAP_PERILAKU'],
            ['S016', 'Menggunakan akun atau media sosial untuk mencemarkan nama baik warga sekolah', 'sedang', 50, 'SIKAP_PERILAKU'],
            ['S017', 'Membawa kartu remi, alat perjudian, atau permainan yang dilarang', 'sedang', 30, 'KEDISIPLINAN'],
            ['S018', 'Mengambil atau menggunakan barang milik orang lain tanpa izin, bukan pencurian', 'sedang', 40, 'SIKAP_PERILAKU'],
            ['S019', 'Tidak mengembalikan barang pinjaman sekolah atau milik teman dengan sengaja', 'sedang', 25, 'SIKAP_PERILAKU'],
            ['S020', 'Terlibat perselisihan atau perkelahian ringan', 'sedang', 50, 'KONFLIK_SISWA'],
            ['S021', 'Membawa kendaraan ke sekolah tanpa memenuhi ketentuan', 'sedang', 30, 'KEDISIPLINAN'],
            ['S022', 'Mengabaikan atau menolak sanksi pembinaan sekolah', 'sedang', 40, 'SIKAP_PERILAKU'],
            ['S023', 'Mengulangi pelanggaran ringan hingga tiga kali atau lebih', 'sedang', 30, 'KEDISIPLINAN'],
            ['S024', 'Membawa benda yang berpotensi membahayakan tetapi tidak digunakan untuk melukai', 'sedang', 50, 'KEDISIPLINAN'],
            ['S025', 'Melakukan tindakan yang mencemarkan nama baik sekolah', 'sedang', 50, 'SIKAP_PERILAKU'],
            ['B001', 'Berkelahi di lingkungan sekolah atau kegiatan resmi sekolah', 'berat', 75, 'KONFLIK_SISWA'],
            ['B002', 'Melakukan perundungan berat secara fisik, verbal, sosial, atau siber', 'berat', 75, 'PERUNDUNGAN'],
            ['B003', 'Mengancam, mengintimidasi, atau memeras warga sekolah', 'berat', 100, 'PERUNDUNGAN'],
            ['B004', 'Merokok, menggunakan vape, atau produk tembakau di lingkungan dan sekitar sekolah', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B005', 'Melakukan pemalakan atau pemerasan terhadap warga sekolah', 'berat', 100, 'PERUNDUNGAN'],
            ['B006', 'Membawa barang berbahaya tanpa izin', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B007', 'Melakukan vandalisme berat terhadap fasilitas sekolah', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B008', 'Membawa, menggunakan, mengedarkan, atau terlibat penyalahgunaan NAPZA', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B009', 'Melakukan pelecehan fisik, verbal, maupun melalui media digital', 'berat', 100, 'PERUNDUNGAN'],
            ['B010', 'Melawan, mengancam, atau bertindak sangat tidak hormat kepada guru atau tenaga kependidikan', 'berat', 100, 'SIKAP_PERILAKU'],
            ['B011', 'Mencuri atau mengambil barang milik orang lain dengan sengaja', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B012', 'Memalsukan dokumen resmi sekolah yang mengakibatkan kerugian pihak lain', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B013', 'Merusak fasilitas sekolah secara sengaja hingga menimbulkan kerugian besar', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B014', 'Membawa, menyimpan, atau mengonsumsi minuman beralkohol di lingkungan sekolah', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B015', 'Terlibat perjudian dalam bentuk apa pun di lingkungan sekolah', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B016', 'Menyebarkan konten yang melanggar norma atau mencemarkan nama baik warga sekolah', 'berat', 100, 'SIKAP_PERILAKU'],
            ['B017', 'Mengakses, menyimpan, atau menyebarkan konten pornografi di lingkungan sekolah', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
            ['B018', 'Menghasut atau mengajak siswa lain melakukan pelanggaran berat', 'berat', 100, 'KONFLIK_SISWA'],
            ['B019', 'Mengulangi pelanggaran sedang hingga mencapai batas pembinaan', 'berat', 100, 'KEDISIPLINAN'],
            ['B020', 'Melakukan tindakan lain yang membahayakan keselamatan atau mencemarkan nama baik sekolah secara serius', 'berat', 100, 'PELANGGARAN_TATA_TERTIB'],
        ];
    }
};
