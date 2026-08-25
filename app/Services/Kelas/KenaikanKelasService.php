<?php

namespace App\Services\Kelas;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\TahunPelajaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KenaikanKelasService
{
    public function proses(
        TahunPelajaran $tahunAsal,
        TahunPelajaran $tahunTujuan,
        Kelas $kelasAsal,
        array $penempatan,
    ): array {
        $this->pastikanKonteks($tahunAsal, $tahunTujuan, $kelasAsal);

        $kelasTujuan = Kelas::query()
            ->where('tahun_pelajaran_id', $tahunTujuan->id)
            ->where('aktif', true)
            ->get()
            ->keyBy('id');
        $anggotaAsal = $kelasAsal->anggotaKelas()
            ->with('siswa')
            ->get()
            ->keyBy('id');
        $ringkasan = [
            'diproses' => 0,
            'ditempatkan' => 0,
            'dilewati' => 0,
            'catatan' => [],
        ];

        DB::transaction(function () use (
            $penempatan,
            $kelasTujuan,
            $anggotaAsal,
            $tahunTujuan,
            &$ringkasan,
        ) {
            foreach ($penempatan as $item) {
                $anggotaKelasId = (int) ($item['anggota_kelas_id'] ?? 0);
                $kelasTujuanId = isset($item['kelas_tujuan_id'])
                    ? (int) $item['kelas_tujuan_id']
                    : null;
                $anggotaLama = $anggotaAsal->get($anggotaKelasId);

                if (! $anggotaLama) {
                    continue;
                }

                $ringkasan['diproses']++;
                $namaSiswa = $anggotaLama->siswa?->nama_lengkap ?: 'Siswa #'.$anggotaLama->siswa_id;

                if (! $kelasTujuanId) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = $namaSiswa.': belum ditempatkan.';

                    continue;
                }

                $kelasBaru = $kelasTujuan->get($kelasTujuanId);
                if (! $kelasBaru) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = $namaSiswa.': kelas tujuan tidak valid.';

                    continue;
                }

                $anggotaTujuan = AnggotaKelas::query()
                    ->where('tahun_pelajaran_id', $tahunTujuan->id)
                    ->where('siswa_id', $anggotaLama->siswa_id)
                    ->first();

                if ($this->kelasTujuanPenuh($kelasBaru, $anggotaTujuan)) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = $namaSiswa.': kelas '.$kelasBaru->nama.' sudah penuh.';

                    continue;
                }

                $payload = [
                    'tahun_pelajaran_id' => (int) $tahunTujuan->id,
                    'kelas_id' => (int) $kelasBaru->id,
                    'siswa_id' => (int) $anggotaLama->siswa_id,
                    'nomor_absen' => null,
                    'status_keanggotaan' => 'aktif',
                    'tanggal_masuk' => $tahunTujuan->tanggal_mulai,
                    'tanggal_keluar' => null,
                    'keterangan' => filled($item['keterangan'] ?? null)
                        ? trim((string) $item['keterangan'])
                        : 'Penempatan massal',
                ];

                if ($anggotaTujuan) {
                    $anggotaTujuan->update($payload);
                } else {
                    AnggotaKelas::create($payload);
                }

                $ringkasan['ditempatkan']++;
            }
        });

        return $ringkasan;
    }

    private function pastikanKonteks(
        TahunPelajaran $tahunAsal,
        TahunPelajaran $tahunTujuan,
        Kelas $kelasAsal,
    ): void {
        if ($tahunAsal->is($tahunTujuan)) {
            throw ValidationException::withMessages([
                'tahun_tujuan_id' => 'Tahun pelajaran tujuan harus berbeda dari tahun asal.',
            ]);
        }

        if ((int) $kelasAsal->tahun_pelajaran_id !== (int) $tahunAsal->id) {
            throw ValidationException::withMessages([
                'kelas_asal_id' => 'Kelas asal tidak berada pada tahun pelajaran yang dipilih.',
            ]);
        }
    }

    private function kelasTujuanPenuh(Kelas $kelas, ?AnggotaKelas $anggotaTujuan): bool
    {
        if (! $kelas->kapasitas || ($anggotaTujuan && (int) $anggotaTujuan->kelas_id === (int) $kelas->id)) {
            return false;
        }

        return $kelas->anggotaKelas()->count() >= $kelas->kapasitas;
    }
}
