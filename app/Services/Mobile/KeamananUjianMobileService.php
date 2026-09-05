<?php

namespace App\Services\Mobile;

use App\Models\AktivitasKeamananUjianCbt;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KeamananUjianMobileService
{
    public function catat(
        Pengguna $pengguna,
        PesertaUjianCbt $peserta,
        string $peristiwa,
        string $perangkat,
        ?string $ip,
        array $metadata = [],
    ): array {
        $peserta = $this->pesertaMilikSiswa($pengguna, $peserta);
        $perangkat = trim($perangkat);
        $dihitung = false;
        $durasi = 0;

        DB::transaction(function () use (
            $peserta,
            $peristiwa,
            $perangkat,
            $ip,
            $metadata,
            &$dihitung,
            &$durasi,
        ): void {
            $terkunci = PesertaUjianCbt::query()
                ->with('ujianCbt')
                ->lockForUpdate()
                ->findOrFail($peserta->id);

            if ($peristiwa === 'heartbeat') {
                $terkunci->forceFill(['heartbeat_terakhir_pada' => now()])->save();

                return;
            }

            if (! $terkunci->ujianCbt->deteksi_pindah_tab
                || ! in_array($terkunci->status, ['sedang_mengerjakan', 'terblokir'], true)) {
                return;
            }

            if ($peristiwa === 'keluar') {
                if ($terkunci->status !== 'sedang_mengerjakan') {
                    return;
                }

                $sudahTerbuka = AktivitasKeamananUjianCbt::query()
                    ->where('peserta_ujian_cbt_id', $terkunci->id)
                    ->where('jenis', 'keluar_aplikasi')
                    ->whereNull('selesai_pada')
                    ->exists();

                if (! $sudahTerbuka) {
                    AktivitasKeamananUjianCbt::create([
                        'peserta_ujian_cbt_id' => $terkunci->id,
                        'jenis' => 'keluar_aplikasi',
                        'mulai_pada' => now(),
                        'perangkat' => $perangkat,
                        'ip' => $ip,
                        'metadata' => $metadata ?: null,
                    ]);
                }

                return;
            }

            $aktivitas = AktivitasKeamananUjianCbt::query()
                ->where('peserta_ujian_cbt_id', $terkunci->id)
                ->where('jenis', 'keluar_aplikasi')
                ->whereNull('selesai_pada')
                ->latest('mulai_pada')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $aktivitas) {
                return;
            }

            $selesai = now();
            $durasi = max(0, (int) floor($aktivitas->mulai_pada->diffInSeconds($selesai)));
            $batasToleransi = max(1, (int) $terkunci->ujianCbt->toleransi_pindah_aplikasi_detik);
            $dihitung = $durasi >= $batasToleransi;
            $aktivitas->update([
                'selesai_pada' => $selesai,
                'durasi_detik' => $durasi,
                'dihitung' => $dihitung,
                'metadata' => array_merge($aktivitas->metadata ?? [], $metadata),
            ]);

            if (! $dihitung) {
                return;
            }

            $jumlah = (int) $terkunci->jumlah_pindah_aplikasi + 1;
            $perubahan = [
                'jumlah_pindah_aplikasi' => $jumlah,
                'durasi_di_luar_aplikasi_detik' => (int) $terkunci->durasi_di_luar_aplikasi_detik + $durasi,
            ];

            $batas = max(1, (int) $terkunci->ujianCbt->batas_pindah_aplikasi);
            if ($terkunci->status === 'sedang_mengerjakan'
                && $terkunci->ujianCbt->tindakan_pindah_aplikasi === 'tahan'
                && $jumlah >= $batas) {
                $perubahan['status'] = 'terblokir';
                $perubahan['ditahan_mode_aman_pada'] = $selesai;
            }

            $terkunci->forceFill($perubahan)->save();
        });

        $peserta = $peserta->fresh(['ujianCbt']);
        $keamanan = $this->ringkas($peserta);

        return [
            'mode' => $peserta->status === 'terblokir' ? 'ditahan' : 'pengerjaan',
            'waktu_server' => now()->toISOString(),
            'kejadian_dihitung' => $dihitung,
            'durasi_kejadian_detik' => $durasi,
            'pesan' => $this->pesan($keamanan, $dihitung),
            'keamanan' => $keamanan,
        ];
    }

    public function bukaTahanan(Pengguna $pengguna, PesertaUjianCbt $peserta): array
    {
        $peserta->loadMissing(['ujianCbt', 'ruangUjianCbt']);
        abort_unless($this->bolehMembuka($pengguna, $peserta), 403);

        if ($peserta->status !== 'terblokir') {
            throw ValidationException::withMessages([
                'peserta' => 'Peserta tidak sedang ditahan oleh Mode Aman.',
            ]);
        }

        DB::transaction(function () use ($pengguna, $peserta): void {
            $terkunci = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($peserta->id);
            if ($terkunci->status !== 'terblokir') {
                return;
            }

            $terkunci->forceFill([
                'status' => 'sedang_mengerjakan',
                'ditahan_mode_aman_pada' => null,
                'dibuka_mode_aman_pada' => now(),
                'dibuka_mode_aman_oleh_pengguna_id' => $pengguna->id,
            ])->save();
        });

        $peserta = $peserta->fresh(['ujianCbt']);

        return [
            'peserta_id' => (int) $peserta->id,
            'status' => $peserta->status,
            'label_status' => $peserta->labelStatus(),
            'keamanan' => $this->ringkas($peserta),
        ];
    }

    public function ringkas(PesertaUjianCbt $peserta): array
    {
        $peserta->loadMissing('ujianCbt');
        $ujian = $peserta->ujianCbt;
        $batas = max(1, (int) $ujian->batas_pindah_aplikasi);

        return [
            'aktif' => (bool) ($ujian->deteksi_pindah_tab || $ujian->wajib_fullscreen || $ujian->blokir_tangkapan_layar),
            'catat_pindah_aplikasi' => (bool) $ujian->deteksi_pindah_tab,
            'layar_aman' => (bool) $ujian->blokir_tangkapan_layar,
            'wajib_fullscreen' => (bool) $ujian->wajib_fullscreen,
            'toleransi_detik' => max(1, (int) $ujian->toleransi_pindah_aplikasi_detik),
            'batas_kejadian' => $batas,
            'tindakan' => $ujian->tindakan_pindah_aplikasi ?: 'catat',
            'jumlah_kejadian' => (int) $peserta->jumlah_pindah_aplikasi,
            'sisa_kejadian' => max(0, $batas - (int) $peserta->jumlah_pindah_aplikasi),
            'durasi_total_detik' => (int) $peserta->durasi_di_luar_aplikasi_detik,
            'ditahan' => $peserta->status === 'terblokir',
            'ditahan_pada' => $peserta->ditahan_mode_aman_pada?->toISOString(),
            'heartbeat_terakhir_pada' => $peserta->heartbeat_terakhir_pada?->toISOString(),
        ];
    }

    private function pesertaMilikSiswa(Pengguna $pengguna, PesertaUjianCbt $peserta): PesertaUjianCbt
    {
        abort_unless($pengguna->akunSiswa() || $pengguna->memilikiPeran('siswa'), 403);
        $siswa = $pengguna->siswa()->firstOrFail();

        return PesertaUjianCbt::query()
            ->with('ujianCbt')
            ->whereKey($peserta->id)
            ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
            ->firstOrFail();
    }

    private function bolehMembuka(Pengguna $pengguna, PesertaUjianCbt $peserta): bool
    {
        if ($peserta->ujianCbt->dapatDiaksesOperasionalOleh($pengguna)) {
            return true;
        }

        return filled($pengguna->pegawai_id)
            && $peserta->ruang_ujian_cbt_id
            && $peserta->ruangUjianCbt()
                ->ditugaskanKepada((int) $pengguna->pegawai_id)
                ->exists();
    }

    private function pesan(array $keamanan, bool $dihitung): ?string
    {
        if ($keamanan['ditahan']) {
            return 'Ujian ditahan karena batas keluar aplikasi tercapai. Minta pengawas membuka ujian.';
        }

        if (! $dihitung) {
            return null;
        }

        if ($keamanan['tindakan'] === 'tahan') {
            return "Peringatan {$keamanan['jumlah_kejadian']} dari {$keamanan['batas_kejadian']}: Anda terdeteksi keluar dari NUSA.";
        }

        return 'Aktivitas keluar dari NUSA telah dicatat untuk pengawas.';
    }
}
