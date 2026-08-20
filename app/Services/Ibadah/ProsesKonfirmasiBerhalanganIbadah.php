<?php

namespace App\Services\Ibadah;

use App\Models\KonfirmasiBerhalanganIbadah;
use App\Models\Pengguna;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProsesKonfirmasiBerhalanganIbadah
{
    public function __construct(private AksesBerhalanganIbadah $akses) {}

    public function proses(
        PeriodeBerhalanganIbadah $periode,
        Pengguna $petugas,
        string $hasil,
        ?int $jedaKonfirmasiHari = null,
        ?string $catatanPrivat = null,
        ?CarbonInterface $waktuKonfirmasi = null,
    ): KonfirmasiBerhalanganIbadah {
        $waktuKonfirmasi = $waktuKonfirmasi ? Carbon::instance($waktuKonfirmasi) : now();
        $tahunPelajaran = TahunPelajaran::query()->find($periode->tahun_pelajaran_id);

        if (! array_key_exists($hasil, KonfirmasiBerhalanganIbadah::DAFTAR_HASIL)) {
            throw ValidationException::withMessages(['hasil' => 'Hasil konfirmasi tidak valid.']);
        }

        if ($hasil === KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN
            && (! $jedaKonfirmasiHari || $jedaKonfirmasiHari < 1 || $jedaKonfirmasiHari > 14)) {
            throw ValidationException::withMessages(['jeda_konfirmasi_hari' => 'Jeda pengingat harus antara 1 sampai 14 hari.']);
        }

        abort_unless(
            $tahunPelajaran
                && $this->akses->dapatMengonfirmasi($petugas, $tahunPelajaran)
                && $periode->kelas_id
                && $this->akses->dapatMengonfirmasiKelas($petugas, $tahunPelajaran, $periode->kelas_id),
            403,
            'Anda tidak ditugaskan untuk mengonfirmasi siswi pada kelas ini.'
        );

        return DB::transaction(function () use ($periode, $petugas, $hasil, $jedaKonfirmasiHari, $catatanPrivat, $waktuKonfirmasi) {
            $periode = PeriodeBerhalanganIbadah::query()->lockForUpdate()->findOrFail($periode->id);

            if ($periode->status !== PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI) {
                throw ValidationException::withMessages([
                    'periode' => $periode->status === PeriodeBerhalanganIbadah::STATUS_SELESAI
                        ? 'Periode ini sudah selesai dan tidak dapat dikonfirmasi ulang.'
                        : 'Periode ini belum mencapai batas konfirmasi.',
                ]);
            }

            $masihBerhalangan = $hasil === KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN;
            $konfirmasiBerikutnya = $masihBerhalangan
                ? $waktuKonfirmasi->copy()->startOfDay()->addDays((int) $jedaKonfirmasiHari)
                : null;

            $konfirmasi = KonfirmasiBerhalanganIbadah::create([
                'periode_berhalangan_ibadah_id' => $periode->id,
                'dikonfirmasi_oleh_pengguna_id' => $petugas->id,
                'hasil' => $hasil,
                'dikonfirmasi_pada' => $waktuKonfirmasi,
                'konfirmasi_berikutnya_pada' => $konfirmasiBerikutnya?->toDateString(),
                'catatan_privat' => filled($catatanPrivat) ? trim($catatanPrivat) : null,
            ]);

            $perubahan = [
                'terakhir_dikonfirmasi_pada' => $waktuKonfirmasi,
                'terakhir_dikonfirmasi_oleh_pengguna_id' => $petugas->id,
                'konfirmasi_berikutnya_pada' => $konfirmasiBerikutnya?->toDateString(),
                'perlu_konfirmasi_sejak' => null,
            ];

            if ($masihBerhalangan) {
                $perubahan['status'] = PeriodeBerhalanganIbadah::STATUS_AKTIF;
            } else {
                $perubahan += [
                    'tanggal_selesai' => $waktuKonfirmasi->toDateString(),
                    'status' => PeriodeBerhalanganIbadah::STATUS_SELESAI,
                    'diselesaikan_oleh_pengguna_id' => $petugas->id,
                    'diselesaikan_pada' => $waktuKonfirmasi,
                    'cara_selesai' => 'konfirmasi_privat',
                ];
            }

            $periode->update($perubahan);

            return $konfirmasi->fresh(['dikonfirmasiOlehPengguna']);
        });
    }
}
