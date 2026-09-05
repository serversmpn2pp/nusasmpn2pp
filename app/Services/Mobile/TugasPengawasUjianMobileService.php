<?php

namespace App\Services\Mobile;

use App\Models\BuktiRuangUjianCbt;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Services\Cbt\NotifikasiUjianTerpusatService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TugasPengawasUjianMobileService
{
    public function __construct(private NotifikasiUjianTerpusatService $notifikasi) {}

    public function daftar(Pengguna $pengguna): array
    {
        abort_unless($pengguna->pegawai_id, 403);

        $tugas = PengawasRuangUjianTerpusat::query()
            ->where(function ($query) use ($pengguna) {
                $query->where('pengawas_utama_pegawai_id', $pengguna->pegawai_id)
                    ->orWhere('pengawas_pendamping_pegawai_id', $pengguna->pegawai_id);
            })
            ->with([
                'jadwalUjianCbt.kegiatanUjianCbt.jenisUjianCbt',
                'jadwalUjianCbt.mataPelajaran',
                'ruangKegiatanUjianCbt',
            ])
            ->get();

        $ruang = RuangUjianCbt::query()
            ->whereIn('jadwal_ujian_cbt_id', $tugas->pluck('jadwal_ujian_cbt_id')->filter())
            ->whereIn('ruang_kegiatan_ujian_cbt_id', $tugas->pluck('ruang_kegiatan_ujian_cbt_id')->filter())
            ->withCount('pesertaUjianCbt')
            ->get()
            ->keyBy(fn (RuangUjianCbt $item) => $item->jadwal_ujian_cbt_id.'-'.$item->ruang_kegiatan_ujian_cbt_id);

        $items = $tugas->map(function (PengawasRuangUjianTerpusat $tugas) use ($pengguna, $ruang) {
            $jadwal = $tugas->jadwalUjianCbt;
            $operasional = $ruang->get($tugas->jadwal_ujian_cbt_id.'-'.$tugas->ruang_kegiatan_ujian_cbt_id);

            return [
                'id' => (int) $tugas->id,
                'ruang_id' => $operasional ? (int) $operasional->id : null,
                'dapat_dibuka' => (bool) $operasional,
                'kegiatan' => $jadwal?->kegiatanUjianCbt?->nama ?? 'Ujian Terpusat',
                'jenis_ujian' => $jadwal?->kegiatanUjianCbt?->jenisUjianCbt?->nama,
                'mata_pelajaran' => $jadwal?->mataPelajaran?->nama ?? 'Mata pelajaran belum ditentukan',
                'tanggal' => $jadwal?->tanggal?->toDateString(),
                'waktu' => $jadwal?->labelWaktu(),
                'ruang' => $tugas->ruangKegiatanUjianCbt?->nama ?? 'Ruang belum ditentukan',
                'peran' => (int) $tugas->pengawas_utama_pegawai_id === (int) $pengguna->pegawai_id
                    ? 'Pengawas utama'
                    : 'Pengawas pendamping',
                'status' => $operasional?->status,
                'label_status' => $operasional?->labelStatus() ?? 'Paket belum diterbitkan',
                'status_bukti' => $operasional?->status_bukti,
                'label_status_bukti' => $operasional?->labelStatusBukti() ?? 'Ruang belum disiapkan',
                'jumlah_peserta' => (int) ($operasional?->peserta_ujian_cbt_count ?? 0),
            ];
        })->sortBy(fn (array $item) => sprintf(
            '%s %s %s',
            $item['tanggal'] ?? '9999-12-31',
            $item['waktu'] ?? '99:99',
            $item['ruang'],
        ))->values();

        return [
            'ringkasan' => [
                'jumlah' => $items->count(),
                'hari_ini' => $items->where('tanggal', now()->toDateString())->count(),
                'berlangsung' => $items->where('status', 'berlangsung')->count(),
                'perlu_bukti' => $items->whereIn('status_bukti', [
                    'belum_diunggah',
                    'sebagian',
                    'siap_dikirim',
                    'perlu_diulang',
                ])->count(),
            ],
            'items' => $items,
        ];
    }

    public function detail(Pengguna $pengguna, RuangUjianCbt $ruang): array
    {
        $this->pastikanBolehMelihat($pengguna, $ruang);
        $ruang->load([
            'ujianCbt.jenisUjianCbt',
            'ujianCbt.tahunPelajaran',
            'jadwalUjianCbt.kegiatanUjianCbt.jenisUjianCbt',
            'jadwalUjianCbt.mataPelajaran',
            'ruangKegiatanUjianCbt',
            'pengawasUtama',
            'pengawasPendamping',
            'buktiRuangUjianCbt' => fn ($query) => $query->with('diunggahOleh')->orderBy('jenis')->orderBy('diunggah_pada'),
            'pesertaUjianCbt' => fn ($query) => $query
                ->with(['anggotaKelas.siswa', 'kelasUjianCbt.kelas'])
                ->withCount('jawabanPesertaUjianCbt'),
        ]);

        $bolehMengelola = $this->bolehMengelola($pengguna, $ruang);
        $buktiTerkunci = in_array($ruang->status_bukti, ['menunggu_pemeriksaan', 'valid'], true);
        $peserta = $ruang->pesertaUjianCbt
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%05d %s',
                $item->nomor_meja ?? 9999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();

        return [
            'ruang' => $this->ringkasRuang($pengguna, $ruang),
            'ringkasan' => $this->ringkasanPeserta($peserta),
            'status_kehadiran' => collect(PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN)
                ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])
                ->values(),
            'peserta' => $peserta->map(fn (PesertaUjianCbt $item) => $this->ringkasPeserta($item))->values(),
            'bukti' => $ruang->buktiRuangUjianCbt->map(fn (BuktiRuangUjianCbt $item) => [
                'id' => (int) $item->id,
                'jenis' => $item->jenis,
                'label_jenis' => $item->labelJenis(),
                'nama_file' => $item->nama_file_asli,
                'tipe_file' => $item->tipe_file,
                'ukuran' => (int) $item->ukuran_file,
                'ukuran_ringkas' => $item->ukuranRingkas(),
                'diunggah_pada' => $item->diunggah_pada?->toISOString(),
                'diunggah_oleh' => $item->diunggahOleh?->nama,
            ])->values(),
            'kemampuan' => [
                'mengelola_ruang' => $bolehMengelola,
                'mengubah_kehadiran' => $bolehMengelola && $ruang->status !== 'selesai',
                'mereset_perangkat' => $bolehMengelola && $ruang->status !== 'selesai',
                'membuka_mode_aman' => $bolehMengelola && $ruang->status !== 'selesai',
                'mengubah_bukti' => $bolehMengelola && ! $buktiTerkunci,
                'mengirim_bukti' => $bolehMengelola && ! $buktiTerkunci,
            ],
            'dihasilkan_pada' => now()->toISOString(),
        ];
    }

    public function ubahStatus(Pengguna $pengguna, RuangUjianCbt $ruang, string $aksi): array
    {
        $this->pastikanBolehMengelola($pengguna, $ruang);

        DB::transaction(function () use ($pengguna, $ruang, $aksi) {
            $terkunci = RuangUjianCbt::query()->lockForUpdate()->findOrFail($ruang->id);

            if ($aksi === 'mulai') {
                if ($terkunci->status === 'selesai') {
                    throw ValidationException::withMessages(['aksi' => 'Ruang yang sudah selesai tidak dapat dimulai kembali.']);
                }

                $terkunci->update([
                    'status' => 'berlangsung',
                    'waktu_mulai_aktual' => $terkunci->waktu_mulai_aktual ?: now(),
                ]);

                return;
            }

            $masihMengerjakan = $terkunci->pesertaUjianCbt()
                ->whereIn('status', ['sedang_mengerjakan', 'terblokir'])
                ->exists();
            if ($masihMengerjakan) {
                throw ValidationException::withMessages([
                    'aksi' => 'Masih ada peserta yang mengerjakan atau ditahan Mode Aman.',
                ]);
            }

            $terkunci->update([
                'status' => 'selesai',
                'waktu_mulai_aktual' => $terkunci->waktu_mulai_aktual ?: now(),
                'waktu_selesai_aktual' => now(),
                'dikunci_pada' => $terkunci->dikunci_pada ?: now(),
                'dikunci_oleh_pengguna_id' => $terkunci->dikunci_oleh_pengguna_id ?: $pengguna->id,
            ]);
        });

        return $this->detail($pengguna, $ruang->fresh());
    }

    public function simpanCatatan(Pengguna $pengguna, RuangUjianCbt $ruang, array $data): array
    {
        $this->pastikanBolehMengelola($pengguna, $ruang);
        $ruang->update(collect($data)->map(fn ($nilai) => filled($nilai) ? trim($nilai) : null)->all());

        return $this->detail($pengguna, $ruang->fresh());
    }

    public function ubahKehadiran(
        Pengguna $pengguna,
        RuangUjianCbt $ruang,
        PesertaUjianCbt $peserta,
        string $status,
        ?string $catatan,
    ): array {
        $this->pastikanBolehMengelola($pengguna, $ruang);
        $this->pastikanPesertaMilikRuang($ruang, $peserta);
        abort_if($ruang->status === 'selesai', 422, 'Presensi ruang yang sudah selesai tidak dapat diubah.');

        DB::transaction(function () use ($pengguna, $peserta, $status, $catatan) {
            $terkunci = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($peserta->id);
            $berubah = $terkunci->status_kehadiran_ujian !== $status;
            $perubahan = [
                'status_kehadiran_ujian' => $status,
                'catatan_kehadiran_ujian' => filled($catatan) ? trim($catatan) : null,
            ];

            if ($status === 'belum_absen') {
                $perubahan['absen_ujian_pada'] = null;
                $perubahan['absen_ujian_oleh_pengguna_id'] = null;
            } elseif ($berubah || ! $terkunci->absen_ujian_pada) {
                $perubahan['absen_ujian_pada'] = now();
                $perubahan['absen_ujian_oleh_pengguna_id'] = $pengguna->id;
            }

            $terkunci->update($perubahan);
        });

        return $this->detail($pengguna, $ruang->fresh());
    }

    public function resetPerangkat(Pengguna $pengguna, RuangUjianCbt $ruang, PesertaUjianCbt $peserta): array
    {
        $this->pastikanBolehMengelola($pengguna, $ruang);
        $this->pastikanPesertaMilikRuang($ruang, $peserta);
        abort_if($ruang->status === 'selesai' || $peserta->status === 'selesai', 422, 'Perangkat peserta yang sudah selesai tidak dapat direset.');

        $peserta->forceFill([
            'perangkat_terakhir' => null,
            'user_agent_terakhir' => null,
            'ip_terakhir' => null,
        ])->save();

        return $this->detail($pengguna, $ruang->fresh());
    }

    public function unggahBukti(
        Pengguna $pengguna,
        RuangUjianCbt $ruang,
        string $jenis,
        UploadedFile $file,
    ): array {
        $this->pastikanBolehMengelola($pengguna, $ruang);
        $this->pastikanBuktiDapatDiubah($ruang);
        $lokasi = $file->store("cbt/{$ruang->ujian_cbt_id}/ruang/{$ruang->id}/bukti-pengawas", 'local');

        try {
            DB::transaction(function () use ($pengguna, $ruang, $jenis, $file, $lokasi) {
                $ruang->buktiRuangUjianCbt()->create([
                    'jenis' => $jenis,
                    'lokasi_file' => $lokasi,
                    'nama_file_asli' => $file->getClientOriginalName(),
                    'tipe_file' => $file->getMimeType(),
                    'ukuran_file' => $file->getSize(),
                    'diunggah_oleh_pengguna_id' => $pengguna->id,
                    'diunggah_pada' => now(),
                ]);
                $this->segarkanStatusBukti($ruang);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($lokasi);
            throw $exception;
        }

        return $this->detail($pengguna, $ruang->fresh());
    }

    public function hapusBukti(
        Pengguna $pengguna,
        RuangUjianCbt $ruang,
        BuktiRuangUjianCbt $bukti,
    ): array {
        $this->pastikanBolehMengelola($pengguna, $ruang);
        $this->pastikanBuktiDapatDiubah($ruang);
        abort_unless((int) $bukti->ruang_ujian_cbt_id === (int) $ruang->id, 404);
        $lokasi = $bukti->lokasi_file;

        DB::transaction(function () use ($bukti, $ruang) {
            $bukti->delete();
            $this->segarkanStatusBukti($ruang);
        });
        Storage::disk('local')->delete($lokasi);

        return $this->detail($pengguna, $ruang->fresh());
    }

    public function kirimBukti(Pengguna $pengguna, RuangUjianCbt $ruang): array
    {
        $this->pastikanBolehMengelola($pengguna, $ruang);
        $this->pastikanBuktiDapatDiubah($ruang);
        $jenis = $ruang->buktiRuangUjianCbt()->distinct()->pluck('jenis');

        if (! $jenis->contains(BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR)
            || ! $jenis->contains(BuktiRuangUjianCbt::JENIS_BERITA_ACARA)) {
            throw ValidationException::withMessages([
                'bukti' => 'Unggah daftar hadir dan berita acara sebelum mengirim bukti.',
            ]);
        }

        $ruang->update([
            'status_bukti' => 'menunggu_pemeriksaan',
            'bukti_diajukan_pada' => now(),
            'bukti_diajukan_oleh_pengguna_id' => $pengguna->id,
            'catatan_pemeriksaan_bukti' => null,
            'bukti_diperiksa_pada' => null,
            'bukti_diperiksa_oleh_pengguna_id' => null,
        ]);
        $this->notifikasi->kirimBuktiKepadaPanitia($ruang->fresh(), $pengguna->id);

        return $this->detail($pengguna, $ruang->fresh());
    }

    private function ringkasRuang(Pengguna $pengguna, RuangUjianCbt $ruang): array
    {
        $jadwal = $ruang->jadwalUjianCbt;
        $kegiatan = $jadwal?->kegiatanUjianCbt;

        return [
            'id' => (int) $ruang->id,
            'kode' => $ruang->kode,
            'nama' => $ruang->nama,
            'lokasi' => $ruang->lokasi ?: $ruang->ruangKegiatanUjianCbt?->lokasi,
            'status' => $ruang->status,
            'label_status' => $ruang->labelStatus(),
            'terkunci' => $ruang->terkunci(),
            'kegiatan' => $kegiatan?->nama ?? $ruang->ujianCbt?->nama ?? 'Ujian Terpusat',
            'jenis_ujian' => $kegiatan?->jenisUjianCbt?->nama ?? $ruang->ujianCbt?->jenisUjianCbt?->nama,
            'mata_pelajaran' => $jadwal?->mataPelajaran?->nama ?? $ruang->ujianCbt?->mataPelajaran?->nama ?? '-',
            'tingkat' => (int) ($jadwal?->tingkat ?? $ruang->ujianCbt?->tingkat ?? 0),
            'tanggal' => $jadwal?->tanggal?->toDateString(),
            'waktu' => $jadwal?->labelWaktu(),
            'waktu_mulai_aktual' => $ruang->waktu_mulai_aktual?->toISOString(),
            'waktu_selesai_aktual' => $ruang->waktu_selesai_aktual?->toISOString(),
            'pengawas_utama' => $ruang->pengawasUtama?->nama_lengkap,
            'pengawas_pendamping' => $ruang->pengawasPendamping?->nama_lengkap,
            'peran_saya' => (int) $pengguna->pegawai_id === (int) $ruang->pengawas_utama_pegawai_id
                ? 'Pengawas utama'
                : ((int) $pengguna->pegawai_id === (int) $ruang->pengawas_pendamping_pegawai_id
                    ? 'Pengawas pendamping'
                    : 'Pengelola CBT'),
            'status_bukti' => $ruang->status_bukti,
            'label_status_bukti' => $ruang->labelStatusBukti(),
            'catatan_pemeriksaan_bukti' => $ruang->catatan_pemeriksaan_bukti,
            'berita_acara' => $ruang->berita_acara,
            'hambatan' => $ruang->hambatan,
            'tindak_lanjut' => $ruang->tindak_lanjut,
            'catatan' => $ruang->catatan,
        ];
    }

    private function ringkasanPeserta($peserta): array
    {
        $status = $peserta->countBy(fn (PesertaUjianCbt $item) => $item->statusPelaksanaan());

        return [
            'total' => $peserta->count(),
            'hadir' => $peserta->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat'])->count(),
            'belum_hadir' => (int) ($status['belum_hadir'] ?? 0),
            'tidak_hadir' => (int) ($status['tidak_hadir'] ?? 0),
            'hadir_belum_mulai' => (int) ($status['hadir_belum_mulai'] ?? 0),
            'sedang_mengerjakan' => (int) ($status['sedang_mengerjakan'] ?? 0),
            'selesai' => (int) ($status['selesai'] ?? 0),
            'terblokir' => (int) ($status['terblokir'] ?? 0),
            'jumlah_pindah_aplikasi' => (int) $peserta->sum('jumlah_pindah_aplikasi'),
        ];
    }

    private function ringkasPeserta(PesertaUjianCbt $peserta): array
    {
        $siswa = $peserta->anggotaKelas?->siswa;
        $kehadiran = $peserta->status_kehadiran_ujian ?: 'belum_absen';

        return [
            'id' => (int) $peserta->id,
            'nama' => $siswa?->nama_lengkap ?? 'Siswa',
            'nisn' => $siswa?->nisn,
            'kelas' => $peserta->kelasUjianCbt?->kelas?->nama ?? '-',
            'nomor_peserta' => $peserta->nomor_peserta,
            'nomor_meja' => $peserta->nomor_meja,
            'status' => $peserta->statusPelaksanaan(),
            'label_status' => $peserta->labelStatusPelaksanaan(),
            'kehadiran' => $kehadiran,
            'label_kehadiran' => PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN[$kehadiran] ?? str($kehadiran)->headline()->toString(),
            'catatan_kehadiran' => $peserta->catatan_kehadiran_ujian,
            'waktu_absen' => $peserta->absen_ujian_pada?->toISOString(),
            'waktu_mulai' => $peserta->waktu_mulai?->toISOString(),
            'waktu_selesai' => $peserta->waktu_selesai?->toISOString(),
            'jawaban_tersimpan' => (int) ($peserta->jawaban_peserta_ujian_cbt_count ?? 0),
            'perangkat_terikat' => filled($peserta->perangkat_terakhir),
            'perangkat' => $peserta->perangkat_terakhir,
            'jumlah_pindah_aplikasi' => (int) $peserta->jumlah_pindah_aplikasi,
            'durasi_di_luar_aplikasi_detik' => (int) $peserta->durasi_di_luar_aplikasi_detik,
            'heartbeat_terakhir_pada' => $peserta->heartbeat_terakhir_pada?->toISOString(),
            'ditahan_mode_aman_pada' => $peserta->ditahan_mode_aman_pada?->toISOString(),
        ];
    }

    private function segarkanStatusBukti(RuangUjianCbt $ruang): void
    {
        $jenis = $ruang->buktiRuangUjianCbt()->distinct()->pluck('jenis');
        $lengkap = $jenis->contains(BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR)
            && $jenis->contains(BuktiRuangUjianCbt::JENIS_BERITA_ACARA);

        $ruang->update([
            'status_bukti' => $lengkap ? 'siap_dikirim' : ($jenis->isNotEmpty() ? 'sebagian' : 'belum_diunggah'),
        ]);
    }

    private function pastikanBuktiDapatDiubah(RuangUjianCbt $ruang): void
    {
        abort_if(
            in_array($ruang->status_bukti, ['menunggu_pemeriksaan', 'valid'], true),
            422,
            'Bukti yang sudah dikirim tidak dapat diubah.',
        );
    }

    private function pastikanPesertaMilikRuang(RuangUjianCbt $ruang, PesertaUjianCbt $peserta): void
    {
        abort_unless((int) $peserta->ruang_ujian_cbt_id === (int) $ruang->id, 404);
    }

    private function pastikanBolehMelihat(Pengguna $pengguna, RuangUjianCbt $ruang): void
    {
        if ($this->bolehMengelola($pengguna, $ruang)) {
            return;
        }

        $ruang->loadMissing('jadwalUjianCbt.kegiatanUjianCbt');
        $kegiatan = $ruang->jadwalUjianCbt?->kegiatanUjianCbt;
        abort_unless(
            $kegiatan
            && $pengguna->memilikiIzin(['cbt.panitia', 'cbt.terpusat_lihat'])
            && $kegiatan->dapatDiaksesOleh($pengguna),
            403,
        );
    }

    private function pastikanBolehMengelola(Pengguna $pengguna, RuangUjianCbt $ruang): void
    {
        abort_unless($this->bolehMengelola($pengguna, $ruang), 403);
    }

    private function bolehMengelola(Pengguna $pengguna, RuangUjianCbt $ruang): bool
    {
        if ($pengguna->administrator() || $pengguna->memilikiIzin('cbt.kelola')) {
            return true;
        }

        return filled($pengguna->pegawai_id)
            && in_array((int) $pengguna->pegawai_id, [
                (int) $ruang->pengawas_utama_pegawai_id,
                (int) $ruang->pengawas_pendamping_pegawai_id,
            ], true);
    }
}
