<?php

namespace App\Services\Cbt;

use App\Models\AktivitasKeamananUjianCbt;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KeamananUjianService
{
    private const JENDELA_POLA_KELUAR_SINGKAT_DETIK = 60;

    private const MINIMAL_POLA_KELUAR_SINGKAT = 3;

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
        $polaKeluarSingkat = null;

        DB::transaction(function () use (
            $peserta,
            $peristiwa,
            $perangkat,
            $ip,
            $metadata,
            &$dihitung,
            &$durasi,
            &$polaKeluarSingkat,
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
                $polaKeluarSingkat = $this->buatPolaKeluarSingkatJikaPerlu(
                    $terkunci,
                    $selesai,
                    $batasToleransi,
                    $perangkat,
                    $ip,
                );

                if ($polaKeluarSingkat) {
                    $dihitung = true;
                    $durasi = $polaKeluarSingkat['durasi_total_detik'];
                }
            }

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
            'pola_keluar_singkat' => $polaKeluarSingkat,
            'pesan' => $this->pesan($keamanan, $dihitung, $polaKeluarSingkat),
            'keamanan' => $keamanan,
        ];
    }

    public function bukaTahanan(Pengguna $pengguna, PesertaUjianCbt $peserta, ?string $alasan = null): array
    {
        $peserta->loadMissing(['ujianCbt', 'ruangUjianCbt']);
        abort_unless($this->dapatMembuka($pengguna, $peserta), 403);

        if ($peserta->status !== 'terblokir') {
            throw ValidationException::withMessages([
                'peserta' => 'Peserta tidak sedang ditahan oleh Mode Aman.',
            ]);
        }

        DB::transaction(function () use ($pengguna, $peserta, $alasan): void {
            $terkunci = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($peserta->id);
            if ($terkunci->status !== 'terblokir') {
                throw ValidationException::withMessages([
                    'peserta' => 'Peserta tidak sedang ditahan oleh Mode Aman.',
                ]);
            }

            $dibukaPada = now();
            $terkunci->forceFill([
                'status' => 'sedang_mengerjakan',
                'ditahan_mode_aman_pada' => null,
                'dibuka_mode_aman_pada' => $dibukaPada,
                'dibuka_mode_aman_oleh_pengguna_id' => $pengguna->id,
            ])->save();

            AktivitasKeamananUjianCbt::create([
                'peserta_ujian_cbt_id' => $terkunci->id,
                'jenis' => 'buka_mode_aman',
                'mulai_pada' => $dibukaPada,
                'selesai_pada' => $dibukaPada,
                'oleh_pengguna_id' => $pengguna->id,
                'catatan' => $alasan === null ? null : trim($alasan),
                'metadata' => ['nama_petugas' => $pengguna->nama],
            ]);
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
        abort_unless($pengguna->akunSiswa(), 403);
        $siswa = $pengguna->siswa()->firstOrFail();

        return PesertaUjianCbt::query()
            ->with('ujianCbt')
            ->whereKey($peserta->id)
            ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
            ->firstOrFail();
    }

    public function dapatMembuka(Pengguna $pengguna, PesertaUjianCbt $peserta): bool
    {
        $peserta->loadMissing(['ujianCbt', 'ruangUjianCbt']);

        if ($pengguna->memilikiIzin('cbt.kelola')) {
            return true;
        }

        if ($peserta->ujianCbt->asesmenKelas()) {
            return $peserta->ujianCbt->dapatDikelolaOleh($pengguna);
        }

        if (filled($pengguna->pegawai_id)
            && $peserta->ruang_ujian_cbt_id
            && $peserta->ruangUjianCbt()
                ->ditugaskanKepada((int) $pengguna->pegawai_id)
                ->exists()) {
            return true;
        }

        return filled($pengguna->pegawai_id)
            && $pengguna->memilikiIzin('cbt.panitia')
            && $peserta->ujianCbt->jadwalUjianCbt()
                ->whereHas('kegiatanUjianCbt.panitiaUjianCbt', fn ($query) => $query
                    ->where('pegawai_id', $pengguna->pegawai_id)
                    ->where('aktif', true))
                ->exists();
    }

    private function buatPolaKeluarSingkatJikaPerlu(
        PesertaUjianCbt $peserta,
        CarbonInterface $selesai,
        int $batasToleransi,
        string $perangkat,
        ?string $ip,
    ): ?array {
        $idBatasRangkaianTerakhir = (int) AktivitasKeamananUjianCbt::query()
            ->where('peserta_ujian_cbt_id', $peserta->id)
            ->where(function ($query) {
                $query->whereIn('jenis', ['pola_keluar_singkat', 'buka_mode_aman'])
                    ->orWhere(function ($query) {
                        $query->where('jenis', 'keluar_aplikasi')->where('dihitung', true);
                    });
            })
            ->max('id');
        $aktivitasSingkat = AktivitasKeamananUjianCbt::query()
            ->where('peserta_ujian_cbt_id', $peserta->id)
            ->where('jenis', 'keluar_aplikasi')
            ->where('id', '>', $idBatasRangkaianTerakhir)
            ->whereNotNull('selesai_pada')
            ->where('selesai_pada', '>=', $selesai->copy()->subSeconds(self::JENDELA_POLA_KELUAR_SINGKAT_DETIK))
            ->where('dihitung', false)
            ->where('durasi_detik', '<', $batasToleransi)
            ->orderBy('selesai_pada')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $jumlah = $aktivitasSingkat->count();
        $durasiTotal = (int) $aktivitasSingkat->sum('durasi_detik');

        if ($jumlah < self::MINIMAL_POLA_KELUAR_SINGKAT && $durasiTotal < $batasToleransi) {
            return null;
        }

        $pola = AktivitasKeamananUjianCbt::create([
            'peserta_ujian_cbt_id' => $peserta->id,
            'jenis' => 'pola_keluar_singkat',
            'mulai_pada' => $aktivitasSingkat->first()?->mulai_pada ?? $selesai,
            'selesai_pada' => $selesai,
            'durasi_detik' => $durasiTotal,
            'dihitung' => true,
            'perangkat' => $perangkat ?: null,
            'ip' => $ip,
            'metadata' => [
                'jumlah_aktivitas_singkat' => $jumlah,
                'durasi_total_detik' => $durasiTotal,
                'jendela_detik' => self::JENDELA_POLA_KELUAR_SINGKAT_DETIK,
                'ambang_jumlah' => self::MINIMAL_POLA_KELUAR_SINGKAT,
                'ambang_durasi_detik' => $batasToleransi,
                'sumber_aktivitas_ids' => $aktivitasSingkat->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ],
        ]);

        $aktivitasSingkat->each(function (AktivitasKeamananUjianCbt $aktivitas) use ($pola): void {
            $aktivitas->update([
                'metadata' => array_merge($aktivitas->metadata ?? [], [
                    'bagian_pola_keluar_singkat' => true,
                    'aktivitas_pola_id' => $pola->id,
                ]),
            ]);
        });

        return [
            'aktivitas_id' => (int) $pola->id,
            'jumlah_aktivitas' => $jumlah,
            'durasi_total_detik' => $durasiTotal,
            'jendela_detik' => self::JENDELA_POLA_KELUAR_SINGKAT_DETIK,
        ];
    }

    private function pesan(array $keamanan, bool $dihitung, ?array $polaKeluarSingkat = null): ?string
    {
        if ($keamanan['ditahan']) {
            return 'Ujian ditahan karena batas keluar aplikasi tercapai. Minta pengawas membuka ujian.';
        }

        if (! $dihitung) {
            return null;
        }

        if ($polaKeluarSingkat) {
            $jumlah = $polaKeluarSingkat['jumlah_aktivitas'];
            $durasi = $polaKeluarSingkat['durasi_total_detik'];

            return "Peringatan {$keamanan['jumlah_kejadian']} dari {$keamanan['batas_kejadian']}: pola keluar singkat berulang ({$jumlah} kali, total {$durasi} detik) dihitung sebagai 1 kejadian.";
        }

        if ($keamanan['tindakan'] === 'tahan') {
            return "Peringatan {$keamanan['jumlah_kejadian']} dari {$keamanan['batas_kejadian']}: Anda terdeteksi keluar dari NUSA.";
        }

        return 'Aktivitas keluar dari NUSA telah dicatat untuk pengawas.';
    }
}
