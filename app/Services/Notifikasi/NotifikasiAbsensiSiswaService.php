<?php

namespace App\Services\Notifikasi;

use App\Models\AbsensiSiswa;
use App\Models\LogScanAbsensi;
use App\Models\NotifikasiAbsensiSiswa;
use App\Models\Pengguna;

class NotifikasiAbsensiSiswaService
{
    public function __construct(private readonly NotifikasiPenggunaService $notifikasi) {}

    public function jadwalkanScanMasuk(AbsensiSiswa $absensi, LogScanAbsensi $logScan): ?NotifikasiAbsensiSiswa
    {
        $absensi->loadMissing(['siswa', 'kelas']);
        $siswa = $absensi->siswa;
        if (! $siswa) {
            return null;
        }

        $jam = substr((string) $absensi->jam_masuk, 0, 5);
        $menit = (int) ($absensi->menit_terlambat ?? 0);
        $status = $menit > 0 ? "terlambat {$menit} menit" : 'tepat waktu';
        $tanggal = $absensi->tanggal->locale('id')->translatedFormat('d F Y');
        $detail = "{$siswa->nama_lengkap} (".($absensi->kelas?->nama ?: '-').") tercatat hadir {$status} pada {$tanggal} pukul ".str_replace(':', '.', $jam).' WIB.';
        $parameter = [
            'siswa_id' => $siswa->id,
            'bulan' => $absensi->tanggal->format('Y-m'),
        ];
        $data = $parameter + [
            'absensi_siswa_id' => $absensi->id,
            'jam_masuk' => $jam,
            'menit_terlambat' => $menit,
        ];
        $ids = [];

        // Relasi identitas menentukan penerima; role dan nomor WhatsApp bukan bukti.
        foreach ([
            'siswa' => $this->notifikasi->penggunaUntukSiswa($siswa->id)
                ->filter(fn (Pengguna $akun) => $akun->akunSiswa()),
            'orang-tua' => $this->notifikasi->penggunaOrangTuaUntukSiswa($siswa->id)
                ->filter(fn (Pengguna $akun) => $akun->akunOrangTua()),
        ] as $penerima => $akunPenerima) {
            $orangTua = $penerima === 'orang-tua';
            foreach ($akunPenerima as $akun) {
                $hasil = $this->notifikasi->kirim(
                    $akun,
                    $menit > 0 ? 'peringatan' : 'berhasil',
                    $orangTua ? 'Kehadiran anak Anda tercatat' : 'Kehadiran Anda tercatat',
                    $orangTua ? 'Anak Anda, '.$detail : $detail,
                    route($orangTua ? 'presensi-anak.index' : 'notifikasi.index', $parameter),
                    "presensi-masuk-{$penerima}:{$absensi->id}",
                    $data,
                );
                if ($hasil) {
                    $ids[] = $hasil->id;
                }
            }
        }

        // Tetap gunakan catatan kanal agar kontrak scanner dan riwayat tidak berubah.
        // Tersimpan tidak berarti FCM sudah diterima perangkat.
        return NotifikasiAbsensiSiswa::firstOrCreate([
            'absensi_siswa_id' => $absensi->id,
            'jenis_absensi' => 'masuk',
            'kanal' => 'nusa',
        ], [
            'log_scan_absensi_id' => $logScan->id,
            'siswa_id' => $siswa->id,
            'tanggal' => $absensi->tanggal->toDateString(),
            'jenis_pesan' => $menit > 0 ? 'masuk_terlambat' : 'masuk_tepat_waktu',
            'mode_pengiriman' => 'aplikasi',
            'status' => $ids ? NotifikasiAbsensiSiswa::STATUS_TERSIMPAN : NotifikasiAbsensiSiswa::STATUS_DILEWATI,
            'pesan' => $detail,
            'payload' => $data + ['notifikasi_pengguna_ids' => $ids],
            'pesan_error' => $ids ? null : 'Belum ada akun siswa atau orang tua aktif yang terhubung.',
            'dijadwalkan_pada' => now(),
        ]);
    }
}
