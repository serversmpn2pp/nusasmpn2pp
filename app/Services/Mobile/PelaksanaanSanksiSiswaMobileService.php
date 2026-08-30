<?php

namespace App\Services\Mobile;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\SanksiPoinSiswa;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\AksesSanksiPoinService;
use App\Services\Pembinaan\CatatRiwayatSanksiPoinService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PelaksanaanSanksiSiswaMobileService
{
    public function __construct(
        private AksesSanksiPoinService $akses,
        private CatatRiwayatSanksiPoinService $riwayat,
        private NotifikasiPenggunaService $notifikasi,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tahunId = isset($filter['tahun_pelajaran_id'])
            ? (int) $filter['tahun_pelajaran_id']
            : TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = isset($filter['kelas_id']) ? (int) $filter['kelas_id'] : null;
        $status = (string) ($filter['status'] ?? 'aktif');
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));
        $dasar = $this->akses->terapkanCakupan(SanksiPoinSiswa::query(), $pengguna)
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId));

        $ringkasan = [
            'aktif' => (clone $dasar)->whereIn('status', ['menunggu', 'diproses'])->count(),
            'menunggu' => (clone $dasar)->where('status', 'menunggu')->count(),
            'diproses' => (clone $dasar)->where('status', 'diproses')->count(),
            'terlambat' => (clone $dasar)->whereIn('status', ['menunggu', 'diproses'])
                ->whereNotNull('batas_pelaksanaan')->whereDate('batas_pelaksanaan', '<', today())->count(),
            'selesai' => (clone $dasar)->where('status', 'selesai')->count(),
        ];

        $query = (clone $dasar)
            ->with($this->relasiRingkas($tahunId))
            ->withCount('buktiPelaksanaanSanksi')
            ->when($status === 'aktif', fn (Builder $query) => $query->whereIn('status', ['menunggu', 'diproses']))
            ->when(array_key_exists($status, SanksiPoinSiswa::DAFTAR_STATUS), fn (Builder $query) => $query->where('status', $status))
            ->when($kelasId, fn (Builder $query) => $query->whereHas('siswa.anggotaKelas', fn (Builder $query) => $query
                ->where('kelas_id', $kelasId)
                ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereHas('siswa', fn (Builder $query) => $query
                        ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]))
                        ->orWhereHas('aturanSanksiPoin', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            })
            ->orderByRaw("CASE status WHEN 'menunggu' THEN 1 WHEN 'diproses' THEN 2 WHEN 'selesai' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN batas_pelaksanaan IS NULL THEN 1 ELSE 0 END')
            ->orderBy('batas_pelaksanaan')
            ->latest('terpicu_pada');
        $paginasi = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginasi->items())->map(fn (SanksiPoinSiswa $sanksi) => $this->ringkas($sanksi))->values(),
            'ringkasan' => $ringkasan,
            'pilihan' => [
                'status' => $this->pilihanStatus(),
                'tahun_pelajaran' => TahunPelajaran::query()
                    ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get()
                    ->map(fn (TahunPelajaran $tahun) => [
                        'id' => (int) $tahun->id,
                        'nama' => $tahun->nama,
                        'aktif' => (bool) $tahun->aktif,
                    ])->values(),
                'kelas' => $this->daftarKelas($pengguna, $tahunId),
            ],
            'filter' => [
                'kata_kunci' => $kataKunci,
                'status' => $status,
                'tahun_pelajaran_id' => $tahunId,
                'kelas_id' => $kelasId,
            ],
            'paginasi' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total' => $paginasi->total(),
                'ada_halaman_berikutnya' => $paginasi->hasMorePages(),
            ],
            'hak_akses' => [
                'cakupan_luas' => $this->akses->aksesLuas($pengguna),
                'dapat_kelola_umum' => $pengguna->administrator() || $pengguna->memilikiIzin('poin_siswa.sanksi_kelola'),
            ],
        ];
    }

    public function rincian(Pengguna $pengguna, SanksiPoinSiswa $sanksi): array
    {
        abort_unless($this->akses->bolehLihat($pengguna, $sanksi), 403);
        $tahunId = $sanksi->tahun_pelajaran_id;
        $sanksi->load([
            'siswa.anggotaKelas' => fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunId)
                ->where('status_keanggotaan', 'aktif')
                ->with('kelas.waliKelas:id,nama_lengkap,nip'),
            'siswa.penugasanGuruWaliSiswa' => fn ($query) => $query
                ->where('aktif', true)->with('guruWali:id,nama_lengkap,nip'),
            'aturanSanksiPoin',
            'tahunPelajaran:id,nama,aktif',
            'petugasPegawai:id,nama_lengkap,nip',
            'diperbaruiOlehPengguna:id,nama',
            'buktiPelaksanaanSanksi' => fn ($query) => $query
                ->with('diunggahOlehPengguna:id,nama')->latest('diunggah_pada'),
            'riwayatSanksiPoinSiswa' => fn ($query) => $query
                ->with('dibuatOlehPengguna:id,nama')->latest('terjadi_pada')->latest('id'),
        ]);
        $anggota = $sanksi->siswa?->anggotaKelas?->first();
        $guruWali = $sanksi->siswa?->penugasanGuruWaliSiswa?->first()?->guruWali;

        return [
            'sanksi' => $this->ringkas($sanksi) + [
                'mulai_diproses_pada' => $sanksi->mulai_diproses_pada?->toISOString(),
                'dilaksanakan_pada' => $sanksi->dilaksanakan_pada?->toISOString(),
                'catatan' => $sanksi->catatan,
                'hasil_pelaksanaan' => $sanksi->hasil_pelaksanaan,
                'wali_kelas' => $this->pegawai($anggota?->kelas?->waliKelas),
                'guru_wali' => $this->pegawai($guruWali),
                'diperbarui_oleh' => $sanksi->diperbaruiOlehPengguna?->nama,
            ],
            'bukti' => $sanksi->buktiPelaksanaanSanksi->map(fn ($bukti) => [
                'id' => (int) $bukti->id,
                'nama_file' => $bukti->nama_file_asli,
                'tipe_file' => $bukti->tipe_file,
                'ukuran_file' => (int) $bukti->ukuran_file,
                'ukuran_ringkas' => $bukti->ukuranRingkas(),
                'keterangan' => $bukti->keterangan,
                'diunggah_oleh' => $bukti->diunggahOlehPengguna?->nama,
                'diunggah_pada' => $bukti->diunggah_pada?->toISOString(),
            ])->values(),
            'riwayat' => $sanksi->riwayatSanksiPoinSiswa->map(fn ($riwayat) => [
                'id' => (int) $riwayat->id,
                'jenis_kegiatan' => $riwayat->jenis_kegiatan,
                'judul' => $riwayat->judul,
                'status_sebelum' => $riwayat->status_sebelum,
                'label_status_sebelum' => $riwayat->status_sebelum
                    ? (SanksiPoinSiswa::DAFTAR_STATUS[$riwayat->status_sebelum] ?? $riwayat->status_sebelum)
                    : null,
                'status_sesudah' => $riwayat->status_sesudah,
                'label_status_sesudah' => $riwayat->status_sesudah
                    ? (SanksiPoinSiswa::DAFTAR_STATUS[$riwayat->status_sesudah] ?? $riwayat->status_sesudah)
                    : null,
                'catatan' => $riwayat->catatan,
                'pengguna' => $riwayat->dibuatOlehPengguna?->nama ?? 'Sistem NUSA',
                'terjadi_pada' => $riwayat->terjadi_pada?->toISOString(),
            ])->values(),
            'pilihan_status' => collect($this->statusDiizinkan($sanksi->status))
                ->map(fn (string $kode) => ['kode' => $kode, 'label' => SanksiPoinSiswa::DAFTAR_STATUS[$kode]])
                ->values(),
            'pegawai' => $this->akses->bolehKelola($pengguna, $sanksi) ? $this->daftarPetugas() : [],
            'hak_akses' => [
                'dapat_kelola' => $this->akses->bolehKelola($pengguna, $sanksi),
                'dapat_unduh_bukti' => true,
                'status_final' => $sanksi->sudahFinal(),
            ],
        ];
    }

    public function perbarui(Pengguna $pengguna, SanksiPoinSiswa $sanksi, array $data): SanksiPoinSiswa
    {
        abort_unless($this->akses->bolehKelola($pengguna, $sanksi), 403);
        abort_if($sanksi->sudahFinal(), 422, 'Sanksi yang sudah selesai atau dibatalkan tidak dapat diubah.');
        $statusBaru = $data['status'];
        if (! in_array($statusBaru, $this->statusDiizinkan($sanksi->status), true)) {
            throw ValidationException::withMessages(['status' => 'Perubahan status tersebut tidak diizinkan.']);
        }
        if (in_array($statusBaru, ['diproses', 'selesai'], true) && empty($data['petugas_pegawai_id'])) {
            throw ValidationException::withMessages(['petugas_pegawai_id' => 'Petugas penanggung jawab wajib dipilih.']);
        }
        if ($statusBaru === 'diproses' && empty($data['batas_pelaksanaan'])) {
            throw ValidationException::withMessages(['batas_pelaksanaan' => 'Batas pelaksanaan wajib diisi saat sanksi mulai diproses.']);
        }
        if ($statusBaru === 'selesai' && blank($data['hasil_pelaksanaan'] ?? null)) {
            throw ValidationException::withMessages(['hasil_pelaksanaan' => 'Hasil pelaksanaan wajib diisi sebelum sanksi diselesaikan.']);
        }
        if ($statusBaru === 'dibatalkan' && blank($data['catatan'] ?? null)) {
            throw ValidationException::withMessages(['catatan' => 'Alasan pembatalan wajib diisi.']);
        }
        if (! empty($data['batas_pelaksanaan']) && $sanksi->terpicu_pada
            && $data['batas_pelaksanaan'] < $sanksi->terpicu_pada->toDateString()) {
            throw ValidationException::withMessages(['batas_pelaksanaan' => 'Batas pelaksanaan tidak boleh sebelum sanksi terpicu.']);
        }
        if (! $pengguna->memilikiIzin('poin_siswa.sanksi_kelola')) {
            $data['petugas_pegawai_id'] = $pengguna->pegawai_id;
        }

        $statusSebelum = $sanksi->status;
        DB::transaction(function () use ($pengguna, $sanksi, $data, $statusBaru, $statusSebelum): void {
            $sanksi->update([
                'status' => $statusBaru,
                'petugas_pegawai_id' => $data['petugas_pegawai_id'] ?: null,
                'mulai_diproses_pada' => $statusBaru === 'diproses' && ! $sanksi->mulai_diproses_pada
                    ? now() : $sanksi->mulai_diproses_pada,
                'batas_pelaksanaan' => $data['batas_pelaksanaan'] ?: $sanksi->batas_pelaksanaan,
                'dilaksanakan_pada' => $statusBaru === 'selesai' ? now() : $sanksi->dilaksanakan_pada,
                'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : $sanksi->catatan,
                'hasil_pelaksanaan' => filled($data['hasil_pelaksanaan'] ?? null)
                    ? trim($data['hasil_pelaksanaan']) : $sanksi->hasil_pelaksanaan,
                'diperbarui_oleh_pengguna_id' => $pengguna->id,
            ]);
            $this->riwayat->catat(
                $sanksi,
                $statusSebelum === $statusBaru ? 'pelaksanaan_diperbarui' : 'status_diubah',
                $this->judulRiwayat($statusBaru, $statusSebelum === $statusBaru),
                $statusSebelum,
                $statusBaru,
                $data['catatan'] ?? $data['hasil_pelaksanaan'] ?? null,
                $pengguna->id,
                [
                    'petugas_pegawai_id' => $sanksi->petugas_pegawai_id,
                    'batas_pelaksanaan' => $sanksi->batas_pelaksanaan?->toDateString(),
                ],
            );
        });
        $this->kirimNotifikasiPerubahan($pengguna, $sanksi->fresh(), $statusSebelum);

        return $sanksi->refresh();
    }

    private function relasiRingkas(?int $tahunId): array
    {
        return [
            'siswa' => fn ($query) => $query->with(['anggotaKelas' => fn ($query) => $query
                ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                ->where('status_keanggotaan', 'aktif')->with('kelas:id,nama')]),
            'aturanSanksiPoin:id,batas_poin,nama,deskripsi',
            'tahunPelajaran:id,nama,aktif',
            'petugasPegawai:id,nama_lengkap,nip',
        ];
    }

    private function ringkas(SanksiPoinSiswa $sanksi): array
    {
        $kelas = $sanksi->siswa?->anggotaKelas?->first()?->kelas;

        return [
            'id' => (int) $sanksi->id,
            'siswa' => $sanksi->siswa ? [
                'id' => (int) $sanksi->siswa->id,
                'nama' => $sanksi->siswa->nama_lengkap,
                'nis' => $sanksi->siswa->nis,
                'nisn' => $sanksi->siswa->nisn,
            ] : null,
            'kelas' => $kelas ? ['id' => (int) $kelas->id, 'nama' => $kelas->nama] : null,
            'tahun_pelajaran' => $sanksi->tahunPelajaran ? [
                'id' => (int) $sanksi->tahunPelajaran->id,
                'nama' => $sanksi->tahunPelajaran->nama,
            ] : null,
            'aturan' => $sanksi->aturanSanksiPoin ? [
                'id' => (int) $sanksi->aturanSanksiPoin->id,
                'nama' => $sanksi->aturanSanksiPoin->nama,
                'batas_poin' => (int) $sanksi->aturanSanksiPoin->batas_poin,
                'deskripsi' => $sanksi->aturanSanksiPoin->deskripsi,
            ] : null,
            'petugas' => $this->pegawai($sanksi->petugasPegawai),
            'poin_saat_terpicu' => (int) $sanksi->poin_saat_terpicu,
            'status' => $sanksi->status,
            'label_status' => $sanksi->labelStatus(),
            'terpicu_pada' => $sanksi->terpicu_pada?->toISOString(),
            'batas_pelaksanaan' => $sanksi->batas_pelaksanaan?->toDateString(),
            'terlambat' => $sanksi->terlambat(),
            'jumlah_bukti' => (int) ($sanksi->bukti_pelaksanaan_sanksi_count ?? $sanksi->buktiPelaksanaanSanksi->count()),
        ];
    }

    private function daftarKelas(Pengguna $pengguna, ?int $tahunId): array
    {
        $siswaIds = $this->akses->terapkanCakupan(SanksiPoinSiswa::query(), $pengguna)
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->distinct()->pluck('siswa_id');

        return Kelas::query()
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->whereIn('siswa_id', $siswaIds)->where('status_keanggotaan', 'aktif'))
            ->orderBy('tingkat')->orderBy('nama')->get()
            ->map(fn (Kelas $kelas) => [
                'id' => (int) $kelas->id,
                'tahun_pelajaran_id' => (int) $kelas->tahun_pelajaran_id,
                'nama' => $kelas->nama,
                'tingkat' => (int) $kelas->tingkat,
            ])->values()->all();
    }

    private function daftarPetugas(): array
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->whereHas('pengguna', fn (Builder $query) => $query
                ->where('aktif', true)
                ->where(function (Builder $query): void {
                    $query->where('akun_sistem', true)
                        ->orWhereHas('daftarPeran', fn (Builder $query) => $query
                            ->where('peran.aktif', true)
                            ->whereHas('izin', fn (Builder $query) => $query
                                ->where('izin.kode', 'poin_siswa.sanksi_kelola')
                                ->where('izin.aktif', true)));
                }))
            ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nip'])
            ->map(fn (Pegawai $pegawai) => $this->pegawai($pegawai))->values()->all();
    }

    private function pegawai($pegawai): ?array
    {
        return $pegawai ? [
            'id' => (int) $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'nip' => $pegawai->nip,
        ] : null;
    }

    private function pilihanStatus(): array
    {
        return collect(['aktif' => 'Perlu Ditangani', 'semua' => 'Semua Status'] + SanksiPoinSiswa::DAFTAR_STATUS)
            ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])->values()->all();
    }

    private function statusDiizinkan(string $status): array
    {
        return match ($status) {
            'menunggu' => ['menunggu', 'diproses', 'dibatalkan'],
            'diproses' => ['diproses', 'selesai', 'dibatalkan'],
            default => [$status],
        };
    }

    private function judulRiwayat(string $status, bool $hanyaDiperbarui): string
    {
        if ($hanyaDiperbarui) {
            return 'Data pelaksanaan diperbarui';
        }

        return match ($status) {
            'diproses' => 'Sanksi mulai diproses',
            'selesai' => 'Sanksi dinyatakan selesai',
            'dibatalkan' => 'Sanksi dibatalkan',
            default => 'Status sanksi diperbarui',
        };
    }

    private function kirimNotifikasiPerubahan(Pengguna $pengguna, SanksiPoinSiswa $sanksi, string $statusSebelum): void
    {
        $sanksi->loadMissing(['siswa', 'aturanSanksiPoin', 'petugasPegawai']);
        $penerima = $this->notifikasi->penggunaDenganIzin('poin_siswa.sanksi_kelola', $pengguna->id);
        if ($sanksi->petugas_pegawai_id) {
            $penerima = $penerima->merge($this->notifikasi->penggunaUntukPegawai($sanksi->petugas_pegawai_id));
        }
        $anggota = $sanksi->siswa?->anggotaKelas()->where('tahun_pelajaran_id', $sanksi->tahun_pelajaran_id)
            ->where('status_keanggotaan', 'aktif')->with('kelas')->first();
        if ($anggota?->kelas?->wali_kelas_id) {
            $penerima = $penerima->merge($this->notifikasi->penggunaUntukPegawai($anggota->kelas->wali_kelas_id));
        }
        $guruWaliId = $sanksi->siswa?->penugasanGuruWaliSiswa()->where('aktif', true)->value('guru_wali_pegawai_id');
        if ($guruWaliId) {
            $penerima = $penerima->merge($this->notifikasi->penggunaUntukPegawai((int) $guruWaliId));
        }
        $judul = match ($sanksi->status) {
            'diproses' => 'Pelaksanaan sanksi dimulai',
            'selesai' => 'Pelaksanaan sanksi selesai',
            'dibatalkan' => 'Sanksi dibatalkan',
            default => 'Pelaksanaan sanksi diperbarui',
        };
        $this->notifikasi->kirimKeBanyak(
            $penerima->filter(fn (Pengguna $item) => (int) $item->id !== (int) $pengguna->id)->unique('id')->values(),
            $sanksi->status === 'dibatalkan' ? 'penting' : 'informasi',
            $judul,
            sprintf('%s untuk %s: %s.', $sanksi->aturanSanksiPoin?->nama ?? 'Sanksi', $sanksi->siswa?->nama_lengkap ?? 'siswa', $sanksi->labelStatus()),
            route('sanksi-poin-siswa.show', $sanksi, false),
            "sanksi-status:{$sanksi->id}:{$statusSebelum}:{$sanksi->status}",
        );
    }
}
