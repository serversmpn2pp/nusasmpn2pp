<?php

namespace App\Console\Commands;

use App\Models\AcaraBukuTamu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiapkanFestivalLiterasi2026 extends Command
{
    protected $signature = 'humas:siapkan-festival-literasi-2026
        {--perbarui-foto : Terapkan foto bawaan sekolah yang baru hanya pada festival ini; berkas lama tetap disimpan}';

    protected $description = 'Menyiapkan buku tamu Festival Literasi IV, 29-31 Oktober 2026, tanpa menimpa acara yang sudah ada.';

    public const KUNCI = 'bc7c72f0-3e3e-4e9c-932d-471b40fc2386';

    public const KODE = 'fl4padangpanjang2026nusa';

    public function handle(): int
    {
        if (! Schema::hasTable('acara_buku_tamu') || ! Schema::hasColumns('acara_buku_tamu', ['banner_tampilan', 'banner_fokus_desktop', 'banner_fokus_mobile'])) {
            $this->error('Jalankan php artisan migrate --force terlebih dahulu.');

            return self::FAILURE;
        }
        if ($this->option('perbarui-foto') && ! is_file(public_path(AcaraBukuTamu::BANNER_DEFAULT))) {
            $this->error('Foto bawaan sekolah belum tersedia. Pastikan berkas gambar sudah ikut diterapkan.');

            return self::FAILURE;
        }
        $acara = DB::transaction(function () {
            $a = AcaraBukuTamu::where('kunci_pembuatan', self::KUNCI)->lockForUpdate()->first();
            if ($a) {
                if ($this->option('perbarui-foto')) {
                    $this->perbaruiFoto($a);
                }

                return $a;
            }
            if (AcaraBukuTamu::where('kode_publik', self::KODE)->exists()) {
                throw new \RuntimeException('Kode festival sudah digunakan acara lain. Data tidak diubah.');
            }
            $a = AcaraBukuTamu::create(['nama' => 'Festival Literasi IV Kota Padang Panjang 2026', 'lokasi' => 'Lapangan Banca Laweh',
                'pengantar' => 'Selamat datang di stand SMP Negeri 2 Padang Panjang. Mari berbagi cerita melalui karya siswa, literasi, numerasi, dan inovasi digital NUSA. Kehadiran dan kesan Anda sangat berarti bagi kami.',
                'mulai_pada' => '2026-10-29 00:00:00', 'selesai_pada' => '2026-10-31 23:59:59', 'status' => 'dibuka',
                'kode_publik' => self::KODE, 'kunci_pembuatan' => self::KUNCI]);
            DB::table('riwayat_acara_buku_tamu')->insert(['acara_buku_tamu_id' => $a->id, 'aksi' => 'disiapkan_perintah',
                'perubahan' => json_encode(['nama', 'lokasi', 'mulai_pada', 'selesai_pada', 'status']),
                'alasan' => 'Penyiapan Festival Literasi IV sesuai jadwal sekolah.', 'created_at' => now()]);

            return $a;
        });
        $this->info($acara->nama);
        $this->line('Periode: '.$acara->mulai_pada->format('d/m/Y H:i:s').' - '.$acara->selesai_pada->format('d/m/Y H:i:s').' WIB');
        $this->line('Buku tamu: '.$acara->urlPublik());
        $this->line('Kelola: '.route('acara-buku-tamu.show', $acara));
        $this->comment('Alamat QR mengikuti APP_URL server. Jadwal, status tutup, dan data acara yang sudah ada tetap dipertahankan.');
        if ($this->option('perbarui-foto')) {
            $this->comment('Foto festival memakai gambar bawaan sekolah. Berkas unggahan sebelumnya tidak dihapus; status dan jadwal tetap.');
        }

        return self::SUCCESS;
    }

    private function perbaruiFoto(AcaraBukuTamu $a): void
    {
        $sebelum = $a->only(['banner', 'banner_tampilan', 'banner_fokus_desktop', 'banner_fokus_mobile']);
        $a->fill(['banner' => null, 'banner_tampilan' => 'potong', 'banner_fokus_desktop' => 66, 'banner_fokus_mobile' => 74]);
        if (! $a->isDirty()) {
            return;
        }
        $a->versi++;
        $a->diubah_oleh = null;
        $a->save();
        DB::table('riwayat_acara_buku_tamu')->insert(['acara_buku_tamu_id' => $a->id, 'aksi' => 'foto_festival_diperbarui',
            'perubahan' => json_encode(['sebelum' => $sebelum, 'sesudah' => $a->only(array_keys($sebelum))]),
            'alasan' => 'Penerapan foto sekolah baru melalui opsi --perbarui-foto; berkas lama tetap disimpan.', 'created_at' => now()]);
    }
}
