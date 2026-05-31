<?php

namespace App\Services\Notifikasi;

use App\Jobs\KirimNotifikasiAbsensiSiswa;
use App\Models\AbsensiSiswa;
use App\Models\LogScanAbsensi;
use App\Models\NotifikasiAbsensiSiswa;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

class NotifikasiAbsensiSiswaService
{
    public function jadwalkanScanMasuk(AbsensiSiswa $absensi, LogScanAbsensi $logScan): ?NotifikasiAbsensiSiswa
    {
        if (! (bool) config('services.whatsapp.kirim_otomatis_absensi_siswa', true)) {
            return null;
        }

        $absensi->loadMissing(['siswa', 'kelas']);
        $siswa = $absensi->siswa;

        if (! $siswa) {
            return null;
        }

        $kontak = $this->pilihKontak($siswa);
        $menitTerlambat = (int) ($absensi->menit_terlambat ?? 0);
        $jenisPesan = $menitTerlambat > 0 ? 'masuk_terlambat' : 'masuk_tepat_waktu';
        $statusKehadiran = $menitTerlambat > 0
            ? 'Terlambat ' . $menitTerlambat . ' menit'
            : 'Hadir tepat waktu';

        $pesan = $this->susunPesanMasuk(
            siswa: $siswa,
            kelas: $absensi->kelas?->nama,
            tanggal: $absensi->tanggal?->locale('id')->translatedFormat('d F Y') ?? now()->locale('id')->translatedFormat('d F Y'),
            jamMasuk: $this->formatJam($absensi->jam_masuk),
            statusKehadiran: $statusKehadiran,
        );
        $modePengiriman = (string) config('services.whatsapp.mode', 'simulasi');
        $statusAwal = match (true) {
            blank($kontak['nomor']) => NotifikasiAbsensiSiswa::STATUS_DILEWATI,
            $modePengiriman === 'simulasi' => NotifikasiAbsensiSiswa::STATUS_SIMULASI,
            default => NotifikasiAbsensiSiswa::STATUS_MENUNGGU,
        };

        $notifikasi = NotifikasiAbsensiSiswa::updateOrCreate(
            [
                'absensi_siswa_id' => $absensi->id,
                'jenis_absensi' => 'masuk',
                'kanal' => 'whatsapp',
            ],
            [
                'log_scan_absensi_id' => $logScan->id,
                'siswa_id' => $siswa->id,
                'tanggal' => $absensi->tanggal?->toDateString() ?? now()->toDateString(),
                'jenis_pesan' => $jenisPesan,
                'mode_pengiriman' => $modePengiriman,
                'nomor_tujuan' => $kontak['nomor'],
                'nama_penerima' => $kontak['nama'],
                'status' => $statusAwal,
                'pesan' => $pesan,
                'payload' => [
                    'nama_siswa' => $siswa->nama_lengkap,
                    'nisn' => $siswa->nisn,
                    'kelas' => $absensi->kelas?->nama,
                    'tanggal' => $absensi->tanggal?->toDateString(),
                    'jam_masuk' => $this->formatJam($absensi->jam_masuk),
                    'status_kehadiran' => $statusKehadiran,
                    'menit_terlambat' => $menitTerlambat,
                ],
                'pesan_error' => $kontak['nomor'] ? null : 'Nomor WhatsApp orang tua/wali belum diisi.',
                'dijadwalkan_pada' => now(),
                'dikirim_pada' => $statusAwal === NotifikasiAbsensiSiswa::STATUS_SIMULASI ? now() : null,
                'gagal_pada' => $kontak['nomor'] ? null : now(),
                'respons' => $statusAwal === NotifikasiAbsensiSiswa::STATUS_SIMULASI ? ['mode' => 'simulasi'] : null,
            ],
        );

        if ($kontak['nomor'] && $modePengiriman !== 'simulasi') {
            DB::afterCommit(fn () => KirimNotifikasiAbsensiSiswa::dispatch($notifikasi->id));
        }

        return $notifikasi;
    }

    private function pilihKontak(Siswa $siswa): array
    {
        $daftarKontak = [
            'ayah' => [
                'nama' => filled($siswa->nama_ayah) ? 'Bapak ' . $siswa->nama_ayah : 'Ayah/Wali',
                'nomor' => $this->normalisasiNomorWhatsapp($siswa->nomor_wa_ayah),
            ],
            'ibu' => [
                'nama' => filled($siswa->nama_ibu) ? 'Ibu ' . $siswa->nama_ibu : 'Ibu/Wali',
                'nomor' => $this->normalisasiNomorWhatsapp($siswa->nomor_wa_ibu),
            ],
            'wali' => [
                'nama' => filled($siswa->nama_wali) ? $siswa->nama_wali : 'Orang Tua/Wali',
                'nomor' => $this->normalisasiNomorWhatsapp($siswa->nomor_wa_wali),
            ],
        ];

        $urutan = collect([$siswa->kontak_absensi_utama, 'ayah', 'ibu', 'wali'])
            ->filter()
            ->unique()
            ->values();

        foreach ($urutan as $kodeKontak) {
            $kontak = $daftarKontak[$kodeKontak] ?? null;

            if ($kontak && filled($kontak['nomor'])) {
                return $kontak;
            }
        }

        return [
            'nama' => 'Orang Tua/Wali',
            'nomor' => null,
        ];
    }

    private function susunPesanMasuk(
        Siswa $siswa,
        ?string $kelas,
        string $tanggal,
        ?string $jamMasuk,
        string $statusKehadiran,
    ): string {
        $kelas = $kelas ?: '-';
        $jamMasuk = $jamMasuk ?: '-';

        return implode("\n\n", [
            "Assalamu'alaikum Bapak/Ibu.",
            "Ananda {$siswa->nama_lengkap} ({$kelas}) tercatat hadir di SMP Negeri 2 Padang Panjang pada {$tanggal} pukul {$jamMasuk}.",
            "Status: {$statusKehadiran}.",
            "Terima kasih.\nNUSA - SMP Negeri 2 Padang Panjang",
        ]);
    }

    private function normalisasiNomorWhatsapp(?string $nomor): ?string
    {
        $nomor = preg_replace('/\D+/', '', (string) $nomor);

        if ($nomor === '') {
            return null;
        }

        if (str_starts_with($nomor, '0')) {
            return '62' . substr($nomor, 1);
        }

        if (str_starts_with($nomor, '620')) {
            return '62' . substr($nomor, 3);
        }

        return $nomor;
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }
}
