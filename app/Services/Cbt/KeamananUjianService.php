<?php

namespace App\Services\Cbt;

use App\Models\AktivitasKeamananUjianCbt;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KeamananUjianService
{
    private const BATAS_JEDA_HEARTBEAT_DETIK = 120;

    private const JENDELA_POLA_KELUAR_SINGKAT_DETIK = 60;

    private const MINIMAL_POLA_KELUAR_SINGKAT = 3;

    public function catat(
        Pengguna $pengguna,
        PesertaUjianCbt $peserta,
        string $peristiwa,
        string $perangkat,
        ?string $ip,
        array $metadata = [],
        bool $nolToleransiMobile = false,
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
            $nolToleransiMobile,
            &$dihitung,
            &$durasi,
            &$polaKeluarSingkat,
        ): void {
            $terkunci = PesertaUjianCbt::query()
                ->with('ujianCbt')
                ->lockForUpdate()
                ->findOrFail($peserta->id);

            if (filled($metadata['kejadian_id'] ?? null)) {
                $diterima = DB::table('penerimaan_keamanan_ujian_cbt')->insertOrIgnore([
                    'peserta_ujian_cbt_id' => $terkunci->id,
                    'kejadian_id' => $metadata['kejadian_id'],
                    'diterima_pada' => now(),
                ]);
                if (! $diterima) {
                    return; // Retry after a lost response must never count a second incident.
                }
            }

            if (! in_array($terkunci->status, ['sedang_mengerjakan', 'terblokir'], true)) {
                if ($terkunci->status === 'selesai' && $terkunci->ujianCbt->deteksi_pindah_tab
                    && $peristiwa !== 'heartbeat' && filled($metadata['kejadian_id'] ?? null)) {
                    $this->catatPeninjauan($terkunci, $perangkat, $ip, array_merge($metadata, [
                        'peristiwa' => $peristiwa, 'pemicu' => 'sinkronisasi-setelah-selesai',
                    ]));
                }

                return; // No heartbeat/status/penalty changes once the exam is closed.
            }

            $heartbeatSebelumnya = $terkunci->heartbeat_terakhir_pada ?? $terkunci->sesi_ujian_mulai_pada ?? $terkunci->waktu_mulai;
            $this->periksaIzinBerkas($terkunci, $perangkat, $ip);
            $adaJeda = $this->adaJeda($terkunci);

            if ($peristiwa === 'heartbeat') {
                if ($adaJeda && $terkunci->ujianCbt->deteksi_pindah_tab) {
                    $this->tinjauKesinambungan($terkunci, $perangkat, $ip, [
                        'pemicu' => 'jeda-heartbeat',
                        'heartbeat_sebelumnya' => $heartbeatSebelumnya->toISOString(),
                    ]);
                }
                $terkunci->forceFill(['heartbeat_terakhir_pada' => now()])->save();

                return;
            }

            if (! $terkunci->ujianCbt->deteksi_pindah_tab
                || ! in_array($terkunci->status, ['sedang_mengerjakan', 'terblokir'], true)) {
                return;
            }

            // The API, never client metadata, selects zero tolerance. One open
            // interval covers inactive/hidden/paused and all UUID retries.
            if ($nolToleransiMobile && in_array($peristiwa, ['keluar', 'kembali'], true)) {
                $aktivitas = $terkunci->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')
                    ->whereNull('selesai_pada')->latest('id')->first();
                if (app(BatasWaktuPesertaUjianCbtService::class)->sisaDetik($terkunci) <= 0) {
                    $this->catatPeninjauan($terkunci, $perangkat, $ip, array_merge($metadata, ['pemicu' => 'kejadian-setelah-deadline']));

                    return;
                }
                if ($peristiwa === 'keluar') {
                    if (! $aktivitas) {
                        AktivitasKeamananUjianCbt::create([
                            'peserta_ujian_cbt_id' => $terkunci->id, 'jenis' => 'keluar_aplikasi',
                            'mulai_pada' => now(), 'dihitung' => true, 'perangkat' => $perangkat, 'ip' => $ip,
                            'metadata' => array_merge($metadata, ['nol_toleransi_mobile' => true,
                                'menahan_sesi' => $terkunci->status === 'sedang_mengerjakan' && $this->modeKetat($terkunci)
                                    && (int) $terkunci->jumlah_pindah_aplikasi + 1 >= max(1, (int) $terkunci->ujianCbt->batas_pindah_aplikasi),
                                'alasan_tahanan' => 'batas_kejadian']),
                        ]);
                        $dihitung = true;
                        $this->tambahKejadian($terkunci);
                    }
                    if (($metadata['dikirim_ulang'] ?? false) || ($metadata['tertunda'] ?? false) || $adaJeda) {
                        $this->tinjauKesinambungan($terkunci, $perangkat, $ip, array_merge($metadata, ['pemicu' => 'keluar-mobile-tertunda']));
                    }

                    return;
                }
                if ($aktivitas && ($aktivitas->metadata['nol_toleransi_mobile'] ?? false)) {
                    $durasi = max(0, (int) floor($aktivitas->mulai_pada->diffInSeconds(now())));
                    $aktivitas->update(['selesai_pada' => now(), 'durasi_detik' => $durasi,
                        'metadata' => array_merge($aktivitas->metadata ?? [], $metadata)]);
                    $terkunci->forceFill(['durasi_di_luar_aplikasi_detik' => (int) $terkunci->durasi_di_luar_aplikasi_detik + $durasi,
                        'heartbeat_terakhir_pada' => now()])->save();
                    if (($metadata['dikirim_ulang'] ?? false) || ($metadata['tertunda'] ?? false)) {
                        $this->tinjauKesinambungan($terkunci, $perangkat, $ip, array_merge($metadata, ['pemicu' => 'kembali-mobile-tertunda']));
                    }

                    return; // Already counted on departure, including while held.
                }
            }

            if ($peristiwa === 'keluar_pengerjaan') {
                $menahan = $this->modeKetat($terkunci) && $terkunci->status === 'sedang_mengerjakan';
                AktivitasKeamananUjianCbt::create([
                    'peserta_ujian_cbt_id' => $terkunci->id,
                    'jenis' => 'keluar_pengerjaan',
                    'mulai_pada' => now(), 'selesai_pada' => now(),
                    'perangkat' => $perangkat, 'ip' => $ip, 'dihitung' => false,
                    'catatan' => 'Siswa menyimpan dan meninggalkan halaman pengerjaan. Bukan penyelesaian ujian.',
                    'metadata' => array_merge($metadata, ['menahan_sesi' => $menahan, 'alasan_tahanan' => 'keluar_pengerjaan']),
                ]);
                if ($menahan) {
                    $this->tahanUntukPeninjauan($terkunci);
                }
                if ($terkunci->status === 'terblokir') {
                    return;
                }
                // Mode catat tetap menyimpan interval keluar sampai siswa kembali.
                $peristiwa = 'keluar';
            }

            // UUID penerimaan, bukan flag client, menentukan apakah ini duplikat.
            // Klaim retry/tertunda hanya dapat memperketat akses, tidak membuka pengecualian.
            if ($terkunci->status === 'terblokir') {
                $this->catatPeninjauan($terkunci, $perangkat, $ip, array_merge($metadata, [
                    'peristiwa' => $peristiwa, 'pemicu' => 'sinkronisasi-saat-ditahan',
                ]));

                return;
            }
            $jedaTidakTerjelaskan = $adaJeda && ($peristiwa !== 'kembali'
                || ! $terkunci->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')->whereNull('selesai_pada')->exists());
            $adaKeluarTerbuka = $terkunci->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')->whereNull('selesai_pada')->exists();
            if ($peristiwa === 'pemulihan' || ($metadata['dikirim_ulang'] ?? false)
                || ($metadata['tertunda'] ?? false) || $jedaTidakTerjelaskan) {
                $perluRekonsiliasi = $peristiwa !== 'pemulihan' || $adaKeluarTerbuka || $adaJeda
                    || ($metadata['dikirim_ulang'] ?? false) || ($metadata['tertunda'] ?? false)
                    || in_array($metadata['pemicu'] ?? null, ['buka-ulang-sesi', 'halaman-ditutup', 'proteksi-layar-gagal', 'pemilih-berkas'], true);
                $this->tinjauKesinambungan($terkunci, $perangkat, $ip, array_merge($metadata, [
                    'peristiwa' => $peristiwa,
                    'pemicu' => $metadata['pemicu'] ?? ($adaJeda ? 'jeda-heartbeat' : 'sinkronisasi-ulang'),
                ]), $perluRekonsiliasi);
                // Bukti keluar tidak ditutup/dihapus oleh klaim pemulihan dari siswa.
                $terkunci->forceFill(['heartbeat_terakhir_pada' => now()])->save();

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
            $dihitung = $nolToleransiMobile || $durasi >= $batasToleransi;
            $aktivitas->update([
                'selesai_pada' => $selesai,
                'durasi_detik' => $durasi,
                'dihitung' => $dihitung,
                'metadata' => array_merge($aktivitas->metadata ?? [], $metadata),
            ]);
            $terkunci->forceFill(['heartbeat_terakhir_pada' => $selesai])->save();

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
            'mode' => $peserta->status === 'selesai' ? 'selesai' : ($peserta->status === 'terblokir' ? 'ditahan' : 'pengerjaan'),
            'waktu_server' => now()->toISOString(),
            'kejadian_dihitung' => $dihitung,
            'durasi_kejadian_detik' => $durasi,
            'pola_keluar_singkat' => $polaKeluarSingkat,
            'pesan' => $this->pesan($keamanan, $dihitung, $polaKeluarSingkat),
            'keamanan' => $keamanan,
        ];
    }

    public function resetPerangkat(Pengguna $pengguna, PesertaUjianCbt $peserta, string $alasan): array
    {
        abort_unless($this->dapatMembuka($pengguna, $peserta), 403);
        DB::transaction(function () use ($pengguna, $peserta, $alasan): void {
            $terkunci = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($peserta->id);
            abort_if($terkunci->ruangUjianCbt?->status === 'selesai', 422, 'Ruang ujian sudah selesai.');
            abort_unless(in_array($terkunci->status, ['aktif', 'sedang_mengerjakan', 'terblokir'], true), 422, 'Sesi peserta yang sudah selesai tidak dapat direset.');
            $terkunci->forceFill([
                'perangkat_terakhir' => null,
                'sesi_ujian_hash' => null,
                'sesi_ujian_saluran' => null,
                'sesi_ujian_mulai_pada' => null,
                'heartbeat_terakhir_pada' => null,
                'ip_terakhir' => null,
                'user_agent_terakhir' => null,
            ])->save();
            $terkunci->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')
                ->whereNull('selesai_pada')->get()->each(fn ($aktivitas) => $aktivitas->update([
                    'selesai_pada' => now(),
                    'metadata' => array_merge($aktivitas->metadata ?? [], ['perlu_ditinjau' => true]),
                ]));
            $this->tutupIzinBerkasPetugas($terkunci, $pengguna);
            AktivitasKeamananUjianCbt::create([
                'peserta_ujian_cbt_id' => $terkunci->id, 'jenis' => 'reset_perangkat',
                'mulai_pada' => now(), 'selesai_pada' => now(),
                'oleh_pengguna_id' => $pengguna->id, 'catatan' => trim($alasan),
            ]);
        });

        return ['peserta_id' => (int) $peserta->id, 'pesan' => 'Ikatan sesi telah direset. Siswa dapat membuka ujian pada perangkat yang disetujui.'];
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
                'heartbeat_terakhir_pada' => $dibukaPada,
            ])->save();

            // Hanya keputusan petugas yang menyelesaikan interval ambigu tanpa sanksi.
            $this->tutupIzinBerkasPetugas($terkunci, $pengguna);
            $terkunci->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')
                ->whereNull('selesai_pada')->get()->each(fn ($aktivitas) => $aktivitas->update([
                    'selesai_pada' => $dibukaPada,
                    'durasi_detik' => max(0, (int) floor($aktivitas->mulai_pada->diffInSeconds($dibukaPada))),
                    'metadata' => array_merge($aktivitas->metadata ?? [], [
                        'perlu_ditinjau' => true, 'diselesaikan_oleh_pengguna_id' => $pengguna->id,
                        'diselesaikan_pada' => $dibukaPada->toISOString(),
                    ]),
                ]));

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
        $alasanTahanan = null;
        if ($peserta->status === 'terblokir') {
            $terakhir = $peserta->aktivitasKeamananUjianCbt()->where(function ($query) {
                $query->where(fn ($query) => $query->where('dihitung', true)
                    ->whereNull('metadata->nol_toleransi_mobile'))->orWhere('metadata->menahan_sesi', true)
                    ->orWhere('jenis', 'buka_mode_aman');
            })->latest('id')->first();
            $alasanTahanan = data_get($terakhir?->metadata, 'menahan_sesi') === true
                ? data_get($terakhir->metadata, 'alasan_tahanan', 'peninjauan_sesi') : 'batas_kejadian';
        }

        return [
            'aktif' => (bool) ($ujian->deteksi_pindah_tab || $ujian->wajib_fullscreen || $ujian->blokir_tangkapan_layar),
            'catat_pindah_aplikasi' => (bool) $ujian->deteksi_pindah_tab,
            'layar_aman' => (bool) $ujian->blokir_tangkapan_layar,
            'wajib_fullscreen' => (bool) $ujian->wajib_fullscreen,
            'toleransi_detik' => max(1, (int) $ujian->toleransi_pindah_aplikasi_detik),
            'nol_toleransi_mobile' => (bool) $ujian->deteksi_pindah_tab,
            'toleransi_mobile_detik' => 0,
            'batas_kejadian' => $batas,
            'tindakan' => $ujian->tindakan_pindah_aplikasi ?: 'catat',
            'jumlah_kejadian' => (int) $peserta->jumlah_pindah_aplikasi,
            'sisa_kejadian' => max(0, $batas - (int) $peserta->jumlah_pindah_aplikasi),
            'durasi_total_detik' => (int) $peserta->durasi_di_luar_aplikasi_detik,
            'ditahan' => $peserta->status === 'terblokir',
            'alasan_tahanan' => $alasanTahanan,
            'pesan_tahanan' => match ($alasanTahanan) {
                'peninjauan_sesi' => 'Kesinambungan sesi perlu diperiksa pengawas. Ini bukan penetapan kecurangan. Jawaban tersimpan tetap ada dan waktu ujian tetap berjalan.',
                'keluar_pengerjaan' => 'Halaman pengerjaan telah ditinggalkan. Hubungi pengawas untuk melanjutkan; jawaban tersimpan tetap ada dan waktu ujian tetap berjalan.',
                'batas_kejadian' => 'Batas keluar aplikasi tercapai. Hubungi pengawas untuk melanjutkan. Waktu ujian tetap berjalan.',
                default => null,
            },
            'ditahan_pada' => $peserta->ditahan_mode_aman_pada?->toISOString(),
            'heartbeat_terakhir_pada' => $peserta->heartbeat_terakhir_pada?->toISOString(),
            'jumlah_perlu_ditinjau' => $peserta->aktivitasKeamananUjianCbt()->where('jenis', 'pemulihan_koneksi')->count(),
        ];
    }

    private function catatPeninjauan(PesertaUjianCbt $peserta, string $perangkat, ?string $ip, array $metadata): void
    {
        AktivitasKeamananUjianCbt::create([
            'peserta_ujian_cbt_id' => $peserta->id,
            'jenis' => 'pemulihan_koneksi',
            'mulai_pada' => now(),
            'selesai_pada' => now(),
            'dihitung' => false,
            'perangkat' => $perangkat,
            'ip' => $ip,
            'catatan' => 'Koneksi atau sesi ujian terputus/dipulihkan. Perlu ditinjau pengawas, bukan bukti otomatis kecurangan.',
            'metadata' => $metadata,
        ]);
    }

    private function modeKetat(PesertaUjianCbt $peserta): bool
    {
        return $peserta->ujianCbt->deteksi_pindah_tab && $peserta->ujianCbt->tindakan_pindah_aplikasi === 'tahan';
    }

    private function tambahKejadian(PesertaUjianCbt $peserta): void
    {
        $jumlah = (int) $peserta->jumlah_pindah_aplikasi + 1;
        $perubahan = ['jumlah_pindah_aplikasi' => $jumlah];
        if ($peserta->status === 'sedang_mengerjakan' && $peserta->ujianCbt->tindakan_pindah_aplikasi === 'tahan'
            && $jumlah >= max(1, (int) $peserta->ujianCbt->batas_pindah_aplikasi)) {
            $perubahan['status'] = 'terblokir';
            $perubahan['ditahan_mode_aman_pada'] = now();
        }
        $peserta->forceFill($perubahan)->save();
    }

    private function adaJeda(PesertaUjianCbt $peserta): bool
    {
        $terakhir = $peserta->heartbeat_terakhir_pada ?? $peserta->sesi_ujian_mulai_pada ?? $peserta->waktu_mulai;

        return $terakhir && $terakhir->lt(now()->subSeconds(self::BATAS_JEDA_HEARTBEAT_DETIK));
    }

    private function tahanUntukPeninjauan(PesertaUjianCbt $peserta): void
    {
        $peserta->forceFill(['status' => 'terblokir', 'ditahan_mode_aman_pada' => now()])->save();
    }

    private function tinjauKesinambungan(PesertaUjianCbt $peserta, string $perangkat, ?string $ip, array $metadata, bool $rekonsiliasi = true): void
    {
        $menahan = $rekonsiliasi && $this->modeKetat($peserta) && $peserta->status === 'sedang_mengerjakan';
        $this->catatPeninjauan($peserta, $perangkat, $ip, array_merge($metadata, [
            'menahan_sesi' => $menahan, 'alasan_tahanan' => 'peninjauan_sesi',
        ]));
        if ($menahan) {
            $this->tahanUntukPeninjauan($peserta);
        }
    }

    // Dipanggil dalam transaksi/lock peserta yang juga melindungi penulisan jawaban.
    public function periksaKesinambungan(PesertaUjianCbt $peserta, string $perangkat, ?string $ip, bool $membukaKertas = false): void
    {
        $this->periksaIzinBerkas($peserta, $perangkat, $ip, $membukaKertas);
        if ($peserta->status !== 'sedang_mengerjakan' || ! $peserta->ujianCbt->deteksi_pindah_tab
            || app(BatasWaktuPesertaUjianCbtService::class)->sisaDetik($peserta) <= 0) {
            return;
        }
        $adaKeluar = $peserta->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')->whereNull('selesai_pada')->exists();
        if ((! $adaKeluar && $this->adaJeda($peserta)) || ($membukaKertas && $adaKeluar)) {
            $this->tinjauKesinambungan($peserta, $perangkat, $ip, [
                'pemicu' => $adaKeluar ? 'buka-kertas-sebelum-rekonsiliasi' : 'jeda-heartbeat-pada-akses',
            ]);
            if ($peserta->status !== 'terblokir') {
                $peserta->forceFill(['heartbeat_terakhir_pada' => now()])->save();
            }
        }
    }

    public function adaKeluarBelumSelesai(PesertaUjianCbt $peserta): bool
    {
        return $this->modeKetat($peserta) && $peserta->status === 'sedang_mengerjakan'
            && $peserta->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')->whereNull('selesai_pada')->exists();
    }

    public function izinBerkasAktif(PesertaUjianCbt $peserta): ?AktivitasKeamananUjianCbt
    {
        return $peserta->aktivitasKeamananUjianCbt()->where('jenis', 'pemilih_berkas')
            ->whereNull('selesai_pada')->latest('id')->first();
    }

    // Pemanggil API wajib menahan lock peserta melalui SesiPengerjaanUjian.
    public function mulaiIzinBerkas(Pengguna $pengguna, PesertaUjianCbt $peserta, int $soalId, string $identitas, string $permintaanId, string $perangkat, ?string $ip): array
    {
        $peserta = $this->pesertaMilikSiswa($pengguna, $peserta);
        $lama = $peserta->aktivitasKeamananUjianCbt()->where('jenis', 'pemilih_berkas')
            ->where('metadata->permintaan_id', $permintaanId)->first();
        if ($lama) {
            abort_unless(hash_equals($lama->metadata['sesi_hash'], $identitas) && (int) $lama->metadata['soal_id'] === $soalId, 403);

            return $this->responsIzinBerkas($peserta, $lama, $lama->selesai_pada === null);
        }
        abort_unless($peserta->status === 'sedang_mengerjakan' && app(BatasWaktuPesertaUjianCbtService::class)->sisaDetik($peserta) > 0, 422, 'Ujian tidak sedang dapat dikerjakan.');
        abort_if($peserta->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')->whereNull('selesai_pada')->exists(), 422, 'Kembali ke ujian dan selesaikan rekonsiliasi sesi terlebih dahulu.');
        $soal = app(PengacakPenyajianCbt::class)->urutkanSoal($peserta->ujianCbt, $peserta,
            $peserta->ujianCbt->soalUjianCbt()->with('soalCbt')->get())
            ->take($peserta->ujianCbt->jumlah_soal)->firstWhere('id', $soalId);
        abort_unless($soal && $soal->soalCbt?->jenis_soal === 'upload_file', 422, 'Izin berkas hanya tersedia untuk soal unggah berkas dalam paket peserta.');
        abort_if($this->izinBerkasAktif($peserta), 422, 'Izin pemilih berkas sebelumnya belum ditutup.');
        $batasId = (int) $peserta->aktivitasKeamananUjianCbt()->whereIn('jenis', ['buka_mode_aman', 'reset_perangkat'])->max('id');
        $jumlah = $peserta->aktivitasKeamananUjianCbt()->where('jenis', 'pemilih_berkas')->where('id', '>', $batasId)
            ->where('metadata->soal_id', $soalId)->count();
        if ($jumlah >= max(1, (int) config('keamanan_ujian_mobile.izin_berkas_per_soal', 3))) {
            $this->tinjauKesinambungan($peserta, $perangkat, $ip, ['pemicu' => 'batas-pemilih-berkas', 'soal_id' => $soalId]);

            return $this->responsIzinBerkas($peserta, null, false);
        }
        $detik = min(max(10, min(90, (int) config('keamanan_ujian_mobile.izin_berkas_detik', 60))), app(BatasWaktuPesertaUjianCbtService::class)->sisaDetik($peserta));
        $izin = AktivitasKeamananUjianCbt::create([
            'peserta_ujian_cbt_id' => $peserta->id, 'jenis' => 'pemilih_berkas',
            'mulai_pada' => now(), 'perangkat' => $perangkat, 'ip' => $ip, 'dihitung' => false,
            'catatan' => 'Izin sementara pemilih berkas sistem; bukan pengecualian pindah aplikasi tanpa batas.',
            'metadata' => ['izin_id' => (string) Str::uuid(), 'permintaan_id' => $permintaanId,
                'soal_id' => $soalId, 'sesi_hash' => $identitas, 'batas_pada' => now()->addSeconds($detik)->toISOString()],
        ]);
        $peserta->forceFill(['heartbeat_terakhir_pada' => now()])->save();

        return $this->responsIzinBerkas($peserta, $izin, true);
    }

    public function selesaiIzinBerkas(Pengguna $pengguna, PesertaUjianCbt $peserta, string $izinId, string $identitas, string $hasil, string $perangkat, ?string $ip): array
    {
        $peserta = $this->pesertaMilikSiswa($pengguna, $peserta);
        $izin = $peserta->aktivitasKeamananUjianCbt()->where('jenis', 'pemilih_berkas')->where('metadata->izin_id', $izinId)->firstOrFail();
        abort_unless(hash_equals($izin->metadata['sesi_hash'], $identitas), 403);
        if ($izin->selesai_pada === null) {
            $valid = now()->lt(Carbon::parse($izin->metadata['batas_pada'])) && $hasil !== 'masa_habis';
            $izin->update(['selesai_pada' => now(),
                'durasi_detik' => max(0, (int) floor($izin->mulai_pada->diffInSeconds(now()))),
                'metadata' => array_merge($izin->metadata, ['hasil' => $valid ? $hasil : 'masa_habis', 'valid' => $valid]),
            ]);
            if (! $valid && $peserta->status === 'sedang_mengerjakan') {
                $this->tinjauKesinambungan($peserta, $perangkat, $ip, ['pemicu' => 'izin-berkas-habis', 'izin_id' => $izinId]);
            }
            if ($valid && $peserta->status === 'sedang_mengerjakan'
                && ! $peserta->aktivitasKeamananUjianCbt()->where('jenis', 'keluar_aplikasi')->whereNull('selesai_pada')->exists()) {
                // Only a timely, server-approved interval can advance continuity.
                $peserta->forceFill(['heartbeat_terakhir_pada' => now()])->save();
            }
        }

        return $this->responsIzinBerkas($peserta, $izin, ($izin->metadata['valid'] ?? false) === true);
    }

    private function responsIzinBerkas(PesertaUjianCbt $peserta, ?AktivitasKeamananUjianCbt $izin, bool $diterima): array
    {
        $sisa = $izin && $izin->selesai_pada === null ? max(0, (int) floor(now()->diffInSeconds(Carbon::parse($izin->metadata['batas_pada'])))) : 0;

        return ['protocol' => 1, 'diterima' => $diterima,
            'mode' => $peserta->status === 'selesai' ? 'selesai' : ($peserta->status === 'terblokir' ? 'ditahan' : 'pengerjaan'),
            'keamanan' => $this->ringkas($peserta),
            'izin' => $izin ? ['id' => $izin->metadata['izin_id'], 'soal_ujian_cbt_id' => (int) $izin->metadata['soal_id'],
                'sisa_detik' => $sisa, 'berlaku_sampai' => $izin->metadata['batas_pada']] : null];
    }

    private function periksaIzinBerkas(PesertaUjianCbt $peserta, string $perangkat, ?string $ip, bool $membukaKertas = false): void
    {
        $izin = $this->izinBerkasAktif($peserta);
        if (! $izin || $peserta->status !== 'sedang_mengerjakan') {
            return;
        }
        if ($membukaKertas || now()->gte(Carbon::parse($izin->metadata['batas_pada']))) {
            $izin->update(['selesai_pada' => now(), 'durasi_detik' => max(0, (int) floor($izin->mulai_pada->diffInSeconds(now()))),
                'metadata' => array_merge($izin->metadata, ['valid' => false, 'hasil' => $membukaKertas ? 'buka-ulang-kertas' : 'masa_habis'])]);
            if (app(BatasWaktuPesertaUjianCbtService::class)->sisaDetik($peserta) > 0) {
                $this->tinjauKesinambungan($peserta, $perangkat, $ip, ['pemicu' => 'izin-berkas-belum-rekonsiliasi', 'izin_id' => $izin->metadata['izin_id']]);
            }
        }
    }

    private function tutupIzinBerkasPetugas(PesertaUjianCbt $peserta, Pengguna $pengguna): void
    {
        $izin = $this->izinBerkasAktif($peserta);
        $izin?->update(['selesai_pada' => now(), 'metadata' => array_merge($izin->metadata, [
            'valid' => false, 'hasil' => 'keputusan-petugas', 'diselesaikan_oleh_pengguna_id' => $pengguna->id,
        ])]);
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
            ->whereNull('metadata->perlu_ditinjau')
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
            return $keamanan['pesan_tahanan'];
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
