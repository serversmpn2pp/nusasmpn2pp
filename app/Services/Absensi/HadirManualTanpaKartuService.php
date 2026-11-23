<?php

namespace App\Services\Absensi;

use App\Models\AbsensiSiswa;
use App\Models\PengaturanAbsensi;
use Illuminate\Validation\ValidationException;

class HadirManualTanpaKartuService
{
    public function dataMasuk(?string $status, ?string $alasan, ?string $jamLama, ?PengaturanAbsensi $jadwal): array
    {
        if ($status !== null && ! isset(AbsensiSiswa::ALASAN_HADIR_TANPA_KARTU[$alasan ?? ''])) {
            throw ValidationException::withMessages(['alasan_hadir_manual' => 'Pilih tidak membawa kartu atau kartu pelajar hilang.']);
        }

        // An older client can still supply an explicit time; never use the save time as arrival evidence.
        if ($status === null && $jamLama && $jadwal) {
            $status = app(HitungKeterlambatanSiswaService::class)->menit($jamLama, $jadwal->jam_masuk, true) > 0
                ? 'terlambat' : 'tepat_waktu';
        }
        if (! isset(AbsensiSiswa::STATUS_MASUK_MANUAL[$status ?? ''])) {
            throw ValidationException::withMessages(['status_masuk_manual' => 'Pilih Hadir tepat waktu atau Hadir terlambat melalui web atau aplikasi NUSA terbaru.']);
        }

        return [
            'jam_masuk' => null,
            'status_masuk' => $status,
            'menit_terlambat' => 0,
            'alasan_hadir_manual' => AbsensiSiswa::normalisasiAlasanHadirManual($alasan),
            'sanksi_manual_menit' => 0,
        ];
    }
}
