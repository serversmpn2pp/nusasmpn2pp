<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migration.
     */
    public function up(): void
    {
        Schema::create('kategori_pembinaan_siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 120)->unique();
            $table->string('kode', 40)->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['aktif', 'nama']);
        });

        DB::table('kategori_pembinaan_siswa')->insert([
            [
                'nama' => 'Kedisiplinan',
                'kode' => 'KEDISIPLINAN',
                'deskripsi' => 'Catatan terkait kepatuhan siswa terhadap aturan sekolah dan tata tertib harian.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Kehadiran',
                'kode' => 'KEHADIRAN',
                'deskripsi' => 'Catatan terkait alfa, terlambat, sering izin, atau pola kehadiran yang perlu pembinaan.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Seragam dan Atribut',
                'kode' => 'SERAGAM_ATRIBUT',
                'deskripsi' => 'Catatan terkait kelengkapan seragam, atribut, dan kerapian siswa.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Sikap dan Perilaku',
                'kode' => 'SIKAP_PERILAKU',
                'deskripsi' => 'Catatan terkait sikap, adab, tanggung jawab, dan perilaku siswa di lingkungan sekolah.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Akademik',
                'kode' => 'AKADEMIK',
                'deskripsi' => 'Catatan terkait kebiasaan belajar, tugas, dan perkembangan akademik yang memerlukan perhatian.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Konflik Antar Siswa',
                'kode' => 'KONFLIK_SISWA',
                'deskripsi' => 'Catatan terkait perselisihan, mediasi, atau konflik antar siswa.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Perundungan',
                'kode' => 'PERUNDUNGAN',
                'deskripsi' => 'Catatan terkait dugaan atau kejadian perundungan yang perlu penanganan terstruktur.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Pelanggaran Tata Tertib',
                'kode' => 'PELANGGARAN_TATA_TERTIB',
                'deskripsi' => 'Catatan terkait pelanggaran tata tertib yang perlu ditindaklanjuti.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Lainnya',
                'kode' => 'LAINNYA',
                'deskripsi' => 'Kategori umum untuk catatan pembinaan yang belum masuk kategori lain.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori_pembinaan_siswa');
    }
};
