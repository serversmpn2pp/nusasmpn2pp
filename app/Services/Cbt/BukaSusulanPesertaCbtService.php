<?php

namespace App\Services\Cbt;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\NilaiSiswa;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\UjianCbt;
use App\Services\Nilai\PublikasiNilaiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BukaSusulanPesertaCbtService
{
    public function __construct(private PublikasiNilaiService $publikasiNilai) {}

    public function buka(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
        PesertaUjianCbt $peserta,
        string $alasan,
    ): array {
        abort_unless($kegiatan->dapatDiaksesOleh($pengguna)
            && $pengguna->memilikiIzin(['cbt.panitia', 'cbt.kelola']), 403);
        abort_unless((int) $jadwal->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        abort_unless($jadwal->ujian_cbt_id && (int) $peserta->ujian_cbt_id === (int) $jadwal->ujian_cbt_id, 404);
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 10 || mb_strlen($alasan) > 1000) {
            throw ValidationException::withMessages(['alasan' => 'Tuliskan alasan pembukaan susulan yang jelas (10–1000 karakter).']);
        }

        return DB::transaction(function () use ($pengguna, $jadwal, $peserta, $alasan): array {
            // Urutan kunci juga dipakai penerapan nilai agar nilai lama tidak terpasang kembali.
            $ujian = UjianCbt::query()->lockForUpdate()->findOrFail($jadwal->ujian_cbt_id);
            abort_unless($ujian->ujianTerpusat() && in_array($ujian->status, ['terjadwal', 'berlangsung', 'selesai'], true), 422);
            $peserta = PesertaUjianCbt::query()->with(['anggotaKelas.siswa', 'kelasUjianCbt.komponenNilai'])
                ->lockForUpdate()->findOrFail($peserta->id);
            if (! $peserta->dapatDibukaUntukSusulan()) {
                throw ValidationException::withMessages(['susulan' => 'Pembukaan hanya tersedia untuk peserta selesai yang nilainya sudah diterapkan dan tidak memiliki jadwal susulan aktif.']);
            }

            $nilai = NilaiSiswa::query()->lockForUpdate()->find($peserta->nilai_siswa_id);
            $komponen = $peserta->kelasUjianCbt?->komponenNilai;
            if (! $nilai || ! $komponen
                || (int) $nilai->komponen_nilai_id !== (int) $komponen->id
                || (int) $nilai->siswa_id !== (int) $peserta->anggotaKelas?->siswa_id) {
                throw ValidationException::withMessages(['susulan' => 'Tujuan nilai siswa tidak cocok dengan peserta ujian. Hubungi pengelola nilai.']);
            }

            $soal = $ujian->soalUjianCbt()->get()->sortBy(fn ($item) => sprintf('%05d|%08d', $item->nomor_urut ?? 9999, $item->id))
                ->values()->take($ujian->jumlah_soal);
            $bobot = round((float) $soal->sum('bobot'), 2);
            $skor = (float) $peserta->jawabanPesertaUjianCbt()->whereIn('soal_ujian_cbt_id', $soal->pluck('id'))->sum('skor');
            $nilaiCbt = $bobot > 0 ? max(0, min(100, round($skor / $bobot * 100, 2))) : null;
            if (! $peserta->nilai_diterapkan_pada || is_null($nilai->nilai) || is_null($nilaiCbt)
                || abs((float) $nilai->nilai - $nilaiCbt) > 0.001
                || $nilai->updated_at?->gt($peserta->nilai_diterapkan_pada)
                || PesertaUjianCbt::query()->where('nilai_siswa_id', $nilai->id)->whereKeyNot($peserta->id)->exists()) {
                throw ValidationException::withMessages(['susulan' => 'Nilai sudah berubah setelah penerapan atau dipakai hasil lain. Pembukaan dibatalkan agar nilai akademik tidak tertimpa. Hubungi pengelola nilai.']);
            }

            $riwayat = $peserta->riwayatPembukaanSusulanCbt()->create([
                'nilai_siswa_id' => $nilai->id,
                'nilai_sebelumnya' => [
                    'nilai' => $nilai->nilai, 'predikat' => $nilai->predikat, 'catatan' => $nilai->catatan,
                    'komponen_nilai_id' => $nilai->komponen_nilai_id, 'siswa_id' => $nilai->siswa_id,
                    'diterapkan_pada' => $peserta->nilai_diterapkan_pada->toISOString(),
                    'diterapkan_oleh_pengguna_id' => $peserta->nilai_diterapkan_oleh_pengguna_id,
                ],
                'keadaan_peserta_sebelumnya' => $peserta->only([
                    'status', 'status_susulan', 'cara_selesai', 'waktu_mulai', 'waktu_selesai',
                    'selesai_otomatis_pada', 'susulan_mulai', 'susulan_selesai',
                    'perangkat_terakhir', 'user_agent_terakhir',
                ]),
                'alasan' => $alasan,
                'dibuka_oleh_pengguna_id' => $pengguna->id,
            ]);
            // Kosongkan hanya nilai target, bukan baris/komponen atau jawaban peserta.
            $nilai->update(['nilai' => null, 'predikat' => null]);
            $peserta->update([
                'nilai_siswa_id' => null,
                'nilai_diterapkan_pada' => null,
                'nilai_diterapkan_oleh_pengguna_id' => null,
                'status_susulan' => 'menunggu_jadwal',
                'token_susulan' => null,
                'perangkat_terakhir' => null,
                'user_agent_terakhir' => null,
                'heartbeat_terakhir_pada' => null,
                'catatan_kehadiran_ujian' => trim(($peserta->catatan_kehadiran_ujian ?? '')."\nDibuka untuk susulan oleh {$pengguna->nama} pada ".now()->format('d-m-Y H:i').": {$alasan}"),
            ]);
            $this->publikasiNilai->tandaiDraf($komponen->guru_mata_pelajaran_id, $komponen->semester);

            return [
                'pesan' => 'Penerapan nilai siswa dibatalkan dan riwayat nilai lama disimpan. Siswa masuk ke daftar penjadwalan susulan; jawaban tersimpan tetap dipertahankan.',
                'data' => ['peserta_id' => $peserta->id, 'status_susulan' => $peserta->status_susulan, 'riwayat_id' => $riwayat->id],
            ];
        });
    }
}
