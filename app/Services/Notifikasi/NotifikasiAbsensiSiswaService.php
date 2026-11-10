<?php

namespace App\Services\Notifikasi;

use App\Models\AbsensiSiswa;
use App\Models\LogScanAbsensi;
use App\Models\NotifikasiAbsensiSiswa;
use App\Models\Pengguna;

class NotifikasiAbsensiSiswaService
{
    public function __construct(private readonly NotifikasiPenggunaService $notifikasi) {}

    public function jadwalkanHadirManual(AbsensiSiswa $absensi): void
    {
        if (! $absensi->memilikiSanksiManual()) {
            return;
        }
        $absensi->loadMissing(['siswa', 'kelas']);
        if (! $absensi->siswa) {
            return;
        }
        $detail = $absensi->siswa->nama_lengkap.' ('.($absensi->kelas?->nama ?: '-').') tercatat hadir melalui input manual pada '
            .$absensi->tanggal->format('d/m/Y').' pukul '.substr((string) $absensi->jam_masuk, 0, 5).' WIB. '
            .$absensi->keteranganSanksiManual();
        $parameter = ['siswa_id' => $absensi->siswa_id, 'bulan' => $absensi->tanggal->format('Y-m')];
        $versi = substr(hash('sha256', $detail), 0, 20);
        foreach ([
            'siswa' => $this->notifikasi->penggunaUntukSiswa($absensi->siswa_id)->filter(fn (Pengguna $akun) => $akun->akunSiswa()),
            'orang-tua' => $this->notifikasi->penggunaOrangTuaUntukSiswa($absensi->siswa_id)->filter(fn (Pengguna $akun) => $akun->akunOrangTua()),
        ] as $jenis => $penerima) {
            foreach ($penerima as $akun) {
                $orangTua = $jenis === 'orang-tua';
                $this->notifikasi->kirim($akun, 'peringatan',
                    $orangTua ? 'Kehadiran anak dicatat manual' : 'Kehadiran dicatat manual',
                    ($orangTua ? 'Anak Anda, ' : '').$detail,
                    route($orangTua ? 'presensi-anak.index' : 'notifikasi.index', $parameter),
                    "presensi-manual-{$jenis}:{$absensi->id}:{$versi}",
                    $parameter + ['absensi_siswa_id' => $absensi->id, 'alasan_hadir_manual' => $absensi->alasan_hadir_manual,
                        'pelanggaran_hadir_manual' => $absensi->aturan_poin_hadir_manual['nama'] ?? null,
                        'menit_terlambat' => $absensi->menit_terlambat, 'poin_keterlambatan' => $absensi->poin_keterlambatan_terhitung]);
            }
        }
    }

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
        if ($absensi->penugasan_presensi_id) {
            $detail = $siswa->nama_lengkap.' melakukan scan pada '.$tanggal.' pukul '.$jam.' WIB. '.$absensi->keteranganPenugasan();
        }
        $parameter = [
            'siswa_id' => $siswa->id,
            'bulan' => $absensi->tanggal->format('Y-m'),
        ];
        $data = $parameter + [
            'absensi_siswa_id' => $absensi->id,
            'jam_masuk' => $jam,
            'menit_terlambat' => $menit,
        ];
        if ($absensi->penugasan_presensi_id) {
            $data['penugasan_sekolah'] = true;
        }
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
