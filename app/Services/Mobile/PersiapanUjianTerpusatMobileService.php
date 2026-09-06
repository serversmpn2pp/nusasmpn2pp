<?php

namespace App\Services\Mobile;

use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\MataPelajaran;
use App\Models\PanitiaUjianCbt;
use App\Models\Pegawai;
use App\Models\PenempatanPesertaUjianCbt;
use App\Models\Pengguna;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\SesiKegiatanUjianCbt;
use App\Models\TahunPelajaran;
use App\Services\Cbt\BagiPesertaUjianTerpusat;
use App\Services\Cbt\KelolaJadwalUjianTerpusat;
use App\Services\Cbt\KodeMejaUjianTerpusat;
use App\Services\Cbt\SinkronkanPelaksanaanUjianTerpusat;
use App\Services\Cbt\SinkronkanPeranPanitiaUjian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersiapanUjianTerpusatMobileService
{
    public function __construct(
        private SinkronkanPeranPanitiaUjian $sinkronkanPeran,
        private BagiPesertaUjianTerpusat $pembagiPeserta,
        private KodeMejaUjianTerpusat $kodeMeja,
        private SinkronkanPelaksanaanUjianTerpusat $sinkronkanPelaksanaan,
        private KelolaJadwalUjianTerpusat $pengelolaJadwal,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $halaman = (int) ($filter['halaman'] ?? 1);
        $queryDasar = $this->queryDapatDiakses($pengguna);

        $paginator = (clone $queryDasar)
            ->with(['jenisUjianCbt', 'tahunPelajaran'])
            ->withCount([
                'panitiaUjianCbt',
                'sesiKegiatanUjianCbt',
                'ruangKegiatanUjianCbt',
                'jadwalUjianCbt',
            ])
            ->withSum([
                'ruangKegiatanUjianCbt as total_kapasitas' => fn (Builder $query) => $query->where('aktif', true),
            ], 'kapasitas')
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->when($kataKunci !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($kataKunci) {
                $query->whereRaw('LOWER(nama) LIKE ?', ['%'.mb_strtolower($kataKunci).'%'])
                    ->orWhereRaw('LOWER(kode) LIKE ?', ['%'.mb_strtolower($kataKunci).'%'])
                    ->orWhereHas('jenisUjianCbt', fn (Builder $query) => $query
                        ->whereRaw('LOWER(nama) LIKE ?', ['%'.mb_strtolower($kataKunci).'%']));
            }))
            ->orderByRaw("CASE WHEN status IN ('draft', 'aktif') THEN 0 ELSE 1 END")
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginator->items())->map(fn (KegiatanUjianCbt $item) => $this->ringkas($item))->values(),
            'ringkasan' => [
                'total' => (clone $queryDasar)->where('status', '!=', 'nonaktif')->count(),
                'persiapan' => (clone $queryDasar)->where('status', 'draft')->count(),
                'aktif' => (clone $queryDasar)->where('status', 'aktif')->count(),
                'selesai' => (clone $queryDasar)->where('status', 'selesai')->count(),
            ],
            'filter' => ['cari' => $kataKunci, 'status' => $status],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'referensi' => $this->referensi($pengguna, false),
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function detail(Pengguna $pengguna, KegiatanUjianCbt $kegiatan): array
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        $kegiatan->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'panitiaUjianCbt' => fn ($query) => $query->with('pegawai.pengguna')->orderBy('jabatan')->orderBy('id'),
            'sesiKegiatanUjianCbt' => fn ($query) => $query
                ->withCount(['kelompokPesertaKegiatanUjianCbt', 'jadwalUjianCbt'])
                ->orderBy('urutan')->orderBy('waktu_mulai'),
            'ruangKegiatanUjianCbt' => fn ($query) => $query
                ->withCount(['kelompokPesertaKegiatanUjianCbt', 'penempatanPesertaUjianCbt'])
                ->orderBy('urutan')->orderBy('kode'),
            'kelompokPesertaKegiatanUjianCbt' => fn ($query) => $query
                ->with(['sesiKegiatanUjianCbt', 'kelas', 'ruangKegiatanUjianCbt'])
                ->withCount('penempatanPesertaUjianCbt')
                ->orderBy('tingkat'),
            'jadwalUjianCbt' => fn ($query) => $query
                ->with([
                    'sesiKegiatanUjianCbt',
                    'mataPelajaran',
                    'kelas',
                    'ujianCbt' => fn ($query) => $query->withCount('soalUjianCbt'),
                ])
                ->orderBy('tanggal')->orderBy('waktu_mulai')->orderBy('tingkat'),
        ])->loadCount('jadwalUjianCbt');

        $daftarKelas = Kelas::query()
            ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->whereIn('tingkat', [7, 8, 9])
            ->where('aktif', true)
            ->withCount(['anggotaKelas as jumlah_siswa_aktif' => fn ($query) => $query
                ->where('status_keanggotaan', 'aktif')
                ->whereHas('siswa', fn ($query) => $query->where('aktif', true))])
            ->orderBy('tingkat')->orderBy('nama')->get()->groupBy('tingkat');
        $kelompokPerTingkat = $kegiatan->kelompokPesertaKegiatanUjianCbt->keyBy('tingkat');
        $jumlahJadwalPerTingkat = $kegiatan->jadwalUjianCbt()
            ->selectRaw('tingkat, COUNT(*) as jumlah')->groupBy('tingkat')
            ->pluck('jumlah', 'tingkat');
        $daftarMataPelajaran = MataPelajaran::query()
            ->where('aktif', true)
            ->with('pengaturanTingkat:id,mata_pelajaran_id,tahun_pelajaran_id,tingkat,aktif')
            ->orderBy('urutan')->orderBy('nama')->get()
            ->map(function (MataPelajaran $mataPelajaran) use ($kegiatan) {
                $memilikiPengaturan = $mataPelajaran->pengaturanTingkat->isNotEmpty();
                $tingkatTersedia = collect([7, 8, 9])->filter(function (int $tingkat) use ($mataPelajaran, $kegiatan, $memilikiPengaturan) {
                    if ($memilikiPengaturan) {
                        return $mataPelajaran->pengaturanTingkat->contains(fn ($pengaturan) => (
                            (int) $pengaturan->tahun_pelajaran_id === (int) $kegiatan->tahun_pelajaran_id
                            && (int) $pengaturan->tingkat === $tingkat
                            && $pengaturan->aktif
                        ));
                    }

                    return ! $mataPelajaran->tingkat || (int) $mataPelajaran->tingkat === $tingkat;
                })->values();

                return [
                    'id' => (int) $mataPelajaran->id,
                    'kode' => $mataPelajaran->kode,
                    'nama' => $mataPelajaran->nama,
                    'tingkat' => $tingkatTersedia,
                ];
            })
            ->filter(fn (array $mataPelajaran) => collect($mataPelajaran['tingkat'])->isNotEmpty())
            ->values();

        return [
            'kegiatan' => [
                ...$this->ringkas($kegiatan),
                'jenis_ujian_cbt_id' => (int) $kegiatan->jenis_ujian_cbt_id,
                'tahun_pelajaran_id' => (int) $kegiatan->tahun_pelajaran_id,
                'keterangan' => $kegiatan->keterangan,
            ],
            'panitia' => $kegiatan->panitiaUjianCbt->map(fn (PanitiaUjianCbt $item) => [
                'id' => (int) $item->id,
                'pegawai_id' => (int) $item->pegawai_id,
                'nama' => $item->pegawai?->nama_lengkap ?? '-',
                'nip' => $item->pegawai?->nip,
                'jabatan' => $item->jabatan,
                'label_jabatan' => $item->labelJabatan(),
                'catatan' => $item->catatan,
                'memiliki_akun' => (bool) $item->pegawai?->pengguna,
            ])->values(),
            'sesi' => $kegiatan->sesiKegiatanUjianCbt->map(fn (SesiKegiatanUjianCbt $item) => [
                'id' => (int) $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'waktu_mulai' => substr((string) $item->waktu_mulai, 0, 5),
                'waktu_selesai' => substr((string) $item->waktu_selesai, 0, 5),
                'label_waktu' => $item->labelWaktu(),
                'aktif' => (bool) $item->aktif,
                'keterangan' => $item->keterangan,
                'dapat_dihapus' => (int) $item->kelompok_peserta_kegiatan_ujian_cbt_count === 0
                    && (int) $item->jadwal_ujian_cbt_count === 0,
            ])->values(),
            'ruang' => $kegiatan->ruangKegiatanUjianCbt->map(fn (RuangKegiatanUjianCbt $item) => [
                'id' => (int) $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'lokasi' => $item->lokasi,
                'kapasitas' => (int) $item->kapasitas,
                'aktif' => (bool) $item->aktif,
                'keterangan' => $item->keterangan,
                'dapat_dihapus' => (int) $item->kelompok_peserta_kegiatan_ujian_cbt_count === 0
                    && (int) $item->penempatan_peserta_ujian_cbt_count === 0,
            ])->values(),
            'tahap_peserta' => [
                'tingkat' => collect([7, 8, 9])->map(function (int $tingkat) use ($daftarKelas, $kelompokPerTingkat, $jumlahJadwalPerTingkat) {
                    $kelas = $daftarKelas->get($tingkat, collect());
                    /** @var KelompokPesertaKegiatanUjianCbt|null $kelompok */
                    $kelompok = $kelompokPerTingkat->get($tingkat);

                    return [
                        'tingkat' => $tingkat,
                        'jumlah_siswa_aktif' => (int) $kelas->sum('jumlah_siswa_aktif'),
                        'kelas' => $kelas->map(fn (Kelas $item) => [
                            'id' => (int) $item->id,
                            'nama' => $item->nama,
                            'jumlah_siswa_aktif' => (int) $item->jumlah_siswa_aktif,
                        ])->values(),
                        'penetapan' => $kelompok ? [
                            'id' => (int) $kelompok->id,
                            'sesi_id' => (int) $kelompok->sesi_kegiatan_ujian_cbt_id,
                            'nama_sesi' => $kelompok->sesiKegiatanUjianCbt?->nama ?? '-',
                            'label_waktu' => $kelompok->sesiKegiatanUjianCbt?->labelWaktu() ?? '-',
                            'kelas_id' => $kelompok->kelas->modelKeys(),
                            'ruang_id' => $kelompok->ruangKegiatanUjianCbt->modelKeys(),
                            'jumlah_peserta' => (int) $kelompok->jumlah_peserta,
                            'jumlah_terbagi' => (int) $kelompok->penempatan_peserta_ujian_cbt_count,
                            'total_kapasitas' => (int) $kelompok->total_kapasitas,
                            'jumlah_jadwal' => (int) ($jumlahJadwalPerTingkat[$tingkat] ?? 0),
                            'dibangkitkan_pada' => $kelompok->dibangkitkan_pada?->toISOString(),
                            'dapat_dihapus' => (int) ($jumlahJadwalPerTingkat[$tingkat] ?? 0) === 0,
                        ] : null,
                    ];
                })->values(),
                'penggunaan_ruang' => $kegiatan->kelompokPesertaKegiatanUjianCbt
                    ->flatMap(fn (KelompokPesertaKegiatanUjianCbt $kelompok) => $kelompok->ruangKegiatanUjianCbt->map(fn (RuangKegiatanUjianCbt $ruang) => [
                        'ruang_id' => (int) $ruang->id,
                        'sesi_id' => (int) $kelompok->sesi_kegiatan_ujian_cbt_id,
                        'tingkat' => (int) $kelompok->tingkat,
                    ]))->values(),
            ],
            'tahap_jadwal' => [
                'items' => $kegiatan->jadwalUjianCbt->map(function (JadwalUjianCbt $jadwal) use ($kelompokPerTingkat) {
                    /** @var KelompokPesertaKegiatanUjianCbt|null $kelompok */
                    $kelompok = $kelompokPerTingkat->get($jadwal->tingkat);

                    return [
                        'id' => (int) $jadwal->id,
                        'tanggal' => $jadwal->tanggal?->format('Y-m-d'),
                        'mata_pelajaran_id' => (int) $jadwal->mata_pelajaran_id,
                        'mata_pelajaran' => $jadwal->mataPelajaran?->nama ?? '-',
                        'tingkat' => (int) $jadwal->tingkat,
                        'nama_sesi' => $jadwal->sesiKegiatanUjianCbt?->nama ?? $jadwal->label_sesi,
                        'label_waktu' => $jadwal->sesiKegiatanUjianCbt?->labelWaktu() ?? $jadwal->labelWaktu(),
                        'kelas' => $jadwal->kelas->pluck('nama')->values(),
                        'ruang' => $kelompok?->ruangKegiatanUjianCbt->pluck('nama')->values() ?? [],
                        'jumlah_peserta' => (int) ($kelompok?->jumlah_peserta ?? 0),
                        'status' => $jadwal->status,
                        'label_status' => $jadwal->labelStatus(),
                        'keterangan' => $jadwal->keterangan,
                        'terkunci' => $jadwal->terkunci(),
                        'paket' => $jadwal->ujianCbt ? [
                            'id' => (int) $jadwal->ujianCbt->id,
                            'status' => $jadwal->ujianCbt->status,
                            'jumlah_soal' => (int) $jadwal->ujianCbt->soal_ujian_cbt_count,
                        ] : null,
                        'dapat_dihapus' => ! $jadwal->ujian_cbt_id && ! $jadwal->terkunci(),
                    ];
                })->values(),
                'mata_pelajaran' => $daftarMataPelajaran,
            ],
            'referensi' => $this->referensi($pengguna, true),
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function aturPenetapan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, array $data): KelompokPesertaKegiatanUjianCbt
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);

        return $this->pembagiPeserta->atur(
            $kegiatan,
            (int) $data['tingkat'],
            (int) $data['sesi_kegiatan_ujian_cbt_id'],
            $data['kelas'],
            $data['ruang'],
        );
    }

    public function tambahJadwal(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, array $data): Collection
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);

        return $this->pengelolaJadwal->tambah($kegiatan, $data);
    }

    public function ubahJadwal(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
        array $data,
    ): JadwalUjianCbt {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);

        return $this->pengelolaJadwal->ubah($kegiatan, $jadwal, $data, $pengguna);
    }

    public function hapusJadwal(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
    ): void {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        $this->pengelolaJadwal->hapus($kegiatan, $jadwal);
    }

    public function bangkitkanPembagian(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        KelompokPesertaKegiatanUjianCbt $kelompok,
    ): KelompokPesertaKegiatanUjianCbt {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        $this->pastikanKelompokMilikKegiatan($kegiatan, $kelompok);

        return $this->pembagiPeserta->bangkitkan($kegiatan, $kelompok, $pengguna);
    }

    public function hapusPenetapan(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        KelompokPesertaKegiatanUjianCbt $kelompok,
    ): void {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        $this->pastikanKelompokMilikKegiatan($kegiatan, $kelompok);
        if ($kegiatan->jadwalUjianCbt()->where('tingkat', $kelompok->tingkat)->exists()) {
            throw ValidationException::withMessages([
                'peserta' => 'Hapus jadwal tingkat ini sebelum mengosongkan pembagian peserta.',
            ]);
        }
        $kelompok->delete();
    }

    public function rincianPembagian(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        KelompokPesertaKegiatanUjianCbt $kelompok,
    ): array {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        $this->pastikanKelompokMilikKegiatan($kegiatan, $kelompok);
        if ($this->kodeMeja->sinkronkanKelompok($kelompok)) {
            $this->sinkronkanPelaksanaan->sinkronkanKegiatan($kegiatan, $pengguna);
            $kelompok->unsetRelations();
        }
        $kegiatan->loadMissing(['jenisUjianCbt', 'tahunPelajaran']);
        $kelompok->load([
            'sesiKegiatanUjianCbt',
            'kelas',
            'ruangKegiatanUjianCbt',
            'penempatanPesertaUjianCbt' => fn ($query) => $query
                ->with(['ruangKegiatanUjianCbt', 'anggotaKelas.kelas', 'anggotaKelas.siswa'])
                ->orderBy('ruang_kegiatan_ujian_cbt_id')->orderBy('nomor_meja'),
        ]);
        $penempatanPerRuang = $kelompok->penempatanPesertaUjianCbt->groupBy('ruang_kegiatan_ujian_cbt_id');

        return [
            'kegiatan' => [
                'id' => (int) $kegiatan->id,
                'kode' => $kegiatan->kode,
                'nama' => $kegiatan->nama,
                'jenis_ujian' => $kegiatan->jenisUjianCbt?->nama ?? '-',
                'tahun_pelajaran' => $kegiatan->tahunPelajaran?->nama ?? '-',
            ],
            'kelompok' => [
                'id' => (int) $kelompok->id,
                'tingkat' => (int) $kelompok->tingkat,
                'nama_sesi' => $kelompok->sesiKegiatanUjianCbt?->nama ?? '-',
                'label_waktu' => $kelompok->sesiKegiatanUjianCbt?->labelWaktu() ?? '-',
                'jumlah_kelas' => $kelompok->kelas->count(),
                'nama_kelas' => $kelompok->kelas->pluck('nama')->values(),
                'jumlah_peserta' => (int) $kelompok->jumlah_peserta,
                'total_kapasitas' => (int) $kelompok->total_kapasitas,
            ],
            'ruang' => $kelompok->ruangKegiatanUjianCbt->map(function (RuangKegiatanUjianCbt $ruang) use ($penempatanPerRuang) {
                $daftar = $penempatanPerRuang->get($ruang->id, collect());

                return [
                    'id' => (int) $ruang->id,
                    'kode' => $ruang->kode,
                    'nama' => $ruang->nama,
                    'lokasi' => $ruang->lokasi,
                    'kapasitas' => (int) $ruang->kapasitas,
                    'jumlah_terisi' => $daftar->count(),
                    'peserta' => $daftar->map(fn ($item) => [
                        'id' => (int) $item->id,
                        'nomor_meja' => (int) $item->nomor_meja,
                        'kode_meja' => $item->kode_meja,
                        'nomor_peserta' => $item->nomor_peserta,
                        'nama' => $item->anggotaKelas?->siswa?->nama_lengkap ?? '-',
                        'nisn' => $item->anggotaKelas?->siswa?->nisn,
                        'kelas' => $item->anggotaKelas?->kelas?->nama ?? '-',
                    ])->values(),
                ];
            })->values(),
        ];
    }

    public function tambahKegiatan(Pengguna $pengguna, array $data): KegiatanUjianCbt
    {
        return KegiatanUjianCbt::create([
            ...$this->rapikanKegiatan($data),
            'kode' => $this->buatKodeSaran((int) $data['tahun_pelajaran_id']),
            'dibuat_oleh_pengguna_id' => $pengguna->id,
        ]);
    }

    public function ubahKegiatan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, array $data): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        DB::transaction(function () use ($kegiatan, $data) {
            $kegiatan->update($this->rapikanKegiatan($data));
            $kegiatan->panitiaUjianCbt()->with('pegawai')->get()
                ->each(fn (PanitiaUjianCbt $panitia) => $this->sinkronkanPeran->sinkronkan($panitia->pegawai));
        });
    }

    public function hapusKegiatan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        if ($kegiatan->status !== 'draft' || $kegiatan->jadwalUjianCbt()->exists()) {
            throw ValidationException::withMessages([
                'kegiatan' => 'Hanya Ujian Terpusat berstatus persiapan dan belum memiliki jadwal yang dapat dihapus.',
            ]);
        }

        DB::transaction(function () use ($kegiatan) {
            $pegawai = $kegiatan->panitiaUjianCbt()->with('pegawai')->get()->pluck('pegawai')->filter();
            $kegiatan->delete();
            $pegawai->each(fn (Pegawai $item) => $this->sinkronkanPeran->sinkronkan($item));
        });
    }

    public function simpanPanitia(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, array $data): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        $pegawai = Pegawai::findOrFail($data['pegawai_id']);
        $kegiatan->panitiaUjianCbt()->updateOrCreate(
            ['pegawai_id' => $pegawai->id],
            [
                'jabatan' => $data['jabatan'],
                'aktif' => true,
                'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
                'ditugaskan_oleh_pengguna_id' => $pengguna->id,
            ],
        );
        $this->sinkronkanPeran->sinkronkan($pegawai);
    }

    public function hapusPanitia(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, PanitiaUjianCbt $panitia): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        abort_unless((int) $panitia->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        $pegawai = $panitia->pegawai;
        $panitia->delete();
        $this->sinkronkanPeran->sinkronkan($pegawai);
    }

    public function tambahSesi(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, array $data): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        $urutan = (int) $kegiatan->sesiKegiatanUjianCbt()->max('urutan') + 1;
        $kegiatan->sesiKegiatanUjianCbt()->create([
            ...$this->rapikanSesi($data),
            'kode' => 'S'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT),
            'urutan' => $urutan,
        ]);
    }

    public function ubahSesi(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, SesiKegiatanUjianCbt $sesi, array $data): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        abort_unless((int) $sesi->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        $rapi = $this->rapikanSesi($data);
        DB::transaction(function () use ($sesi, $rapi) {
            $sesi->update($rapi);
            $sesi->jadwalUjianCbt()->update([
                'waktu_mulai' => $rapi['waktu_mulai'],
                'waktu_selesai' => $rapi['waktu_selesai'],
                'label_sesi' => $rapi['nama'],
            ]);
        });
    }

    public function hapusSesi(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, SesiKegiatanUjianCbt $sesi): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        abort_unless((int) $sesi->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        if ($sesi->kelompokPesertaKegiatanUjianCbt()->exists() || $sesi->jadwalUjianCbt()->exists()) {
            throw ValidationException::withMessages(['sesi' => 'Sesi sudah digunakan dalam pembagian peserta atau jadwal sehingga tidak dapat dihapus.']);
        }
        $sesi->delete();
    }

    public function tambahRuang(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, array $data): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        $urutan = (int) $kegiatan->ruangKegiatanUjianCbt()->max('urutan') + 1;
        $kegiatan->ruangKegiatanUjianCbt()->create([
            ...$this->rapikanRuang($data),
            'kode' => 'R'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT),
            'urutan' => $urutan,
        ]);
    }

    public function ubahRuang(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, RuangKegiatanUjianCbt $ruang, array $data): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        abort_unless((int) $ruang->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        $rapi = $this->rapikanRuang($data);
        $maksimalTerisi = (int) PenempatanPesertaUjianCbt::query()
            ->where('ruang_kegiatan_ujian_cbt_id', $ruang->id)
            ->selectRaw('COUNT(*) as jumlah')
            ->groupBy('kelompok_peserta_kegiatan_ujian_cbt_id')
            ->get()->max('jumlah');
        if ($rapi['kapasitas'] < $maksimalTerisi) {
            throw ValidationException::withMessages([
                'kapasitas' => "Kapasitas tidak boleh kurang dari {$maksimalTerisi} karena ruang sudah terisi dalam pembagian peserta.",
            ]);
        }

        $ruang->update($rapi);
        $ruang->kelompokPesertaKegiatanUjianCbt()->with('ruangKegiatanUjianCbt')->get()
            ->each(fn ($kelompok) => $kelompok->update([
                'total_kapasitas' => $kelompok->ruangKegiatanUjianCbt->sum('kapasitas'),
            ]));
    }

    public function hapusRuang(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, RuangKegiatanUjianCbt $ruang): void
    {
        $this->pastikanDapatDiakses($pengguna, $kegiatan);
        abort_unless((int) $ruang->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        if ($ruang->kelompokPesertaKegiatanUjianCbt()->exists() || $ruang->penempatanPesertaUjianCbt()->exists()) {
            throw ValidationException::withMessages(['ruang' => 'Ruang sudah digunakan dalam pembagian peserta sehingga tidak dapat dihapus.']);
        }
        $ruang->delete();
    }

    private function queryDapatDiakses(Pengguna $pengguna): Builder
    {
        return KegiatanUjianCbt::query()
            ->when(! $pengguna->memilikiIzin(['cbt.kelola', 'cbt.terpusat_lihat']), fn (Builder $query) => $query
                ->whereHas('panitiaUjianCbt', fn (Builder $query) => $query
                    ->where('pegawai_id', $pengguna->pegawai_id)
                    ->where('aktif', true)));
    }

    private function ringkas(KegiatanUjianCbt $item): array
    {
        return [
            'id' => (int) $item->id,
            'kode' => $item->kode,
            'nama' => $item->nama,
            'jenis_ujian' => $item->jenisUjianCbt?->nama ?? '-',
            'tahun_pelajaran' => $item->tahunPelajaran?->nama ?? '-',
            'semester' => $item->semester,
            'tanggal_mulai' => $item->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $item->tanggal_selesai?->format('Y-m-d'),
            'status' => $item->status,
            'label_status' => $item->labelStatus(),
            'jumlah_panitia' => (int) ($item->panitia_ujian_cbt_count ?? $item->panitiaUjianCbt()->count()),
            'jumlah_sesi' => (int) ($item->sesi_kegiatan_ujian_cbt_count ?? $item->sesiKegiatanUjianCbt()->count()),
            'jumlah_ruang' => (int) ($item->ruang_kegiatan_ujian_cbt_count ?? $item->ruangKegiatanUjianCbt()->count()),
            'jumlah_jadwal' => (int) ($item->jadwal_ujian_cbt_count ?? $item->jadwalUjianCbt()->count()),
            'total_kapasitas' => (int) ($item->total_kapasitas ?? $item->ruangKegiatanUjianCbt()->where('aktif', true)->sum('kapasitas')),
            'dapat_dihapus' => $item->status === 'draft'
                && (int) ($item->jadwal_ujian_cbt_count ?? $item->jadwalUjianCbt()->count()) === 0,
        ];
    }

    private function referensi(Pengguna $pengguna, bool $sertakanPegawai): array
    {
        return [
            'jenis_ujian' => JenisUjianCbt::query()->where('aktif', true)->where('kode', '!=', 'ASESMEN_KELAS')
                ->orderBy('urutan')->orderBy('nama')->get()->map(fn (JenisUjianCbt $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                ])->values(),
            'tahun_pelajaran' => TahunPelajaran::query()->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get()
                ->map(fn (TahunPelajaran $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                    'aktif' => (bool) $item->aktif,
                ])->values(),
            'status' => collect(KegiatanUjianCbt::DAFTAR_STATUS)->map(fn (string $label, string $kode) => [
                'kode' => $kode,
                'label' => $label,
            ])->values(),
            'jabatan_panitia' => collect(PanitiaUjianCbt::DAFTAR_JABATAN)->map(fn (string $label, string $kode) => [
                'kode' => $kode,
                'label' => $label,
            ])->values(),
            'pegawai' => $sertakanPegawai && $pengguna->memilikiIzin('cbt.kelola')
                ? Pegawai::query()->with('pengguna')->where('aktif', true)->orderBy('nama_lengkap')->get()
                    ->map(fn (Pegawai $item) => [
                        'id' => (int) $item->id,
                        'nama' => $item->nama_lengkap,
                        'nip' => $item->nip,
                        'memiliki_akun' => (bool) $item->pengguna,
                    ])->values()
                : [],
        ];
    }

    private function hakAkses(Pengguna $pengguna): array
    {
        return [
            'dapat_kelola_utama' => $pengguna->memilikiIzin('cbt.kelola'),
            'dapat_kelola_persiapan' => $pengguna->memilikiIzin(['cbt.kelola', 'cbt.panitia']),
        ];
    }

    private function rapikanKegiatan(array $data): array
    {
        return [
            'jenis_ujian_cbt_id' => (int) $data['jenis_ujian_cbt_id'],
            'tahun_pelajaran_id' => (int) $data['tahun_pelajaran_id'],
            'nama' => trim($data['nama']),
            'semester' => $data['semester'],
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'status' => $data['status'],
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ];
    }

    private function rapikanSesi(array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'waktu_mulai' => $data['waktu_mulai'],
            'waktu_selesai' => $data['waktu_selesai'],
            'aktif' => (bool) $data['aktif'],
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ];
    }

    private function rapikanRuang(array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'lokasi' => filled($data['lokasi'] ?? null) ? trim($data['lokasi']) : null,
            'kapasitas' => (int) $data['kapasitas'],
            'aktif' => (bool) $data['aktif'],
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ];
    }

    private function buatKodeSaran(int $tahunPelajaranId): string
    {
        $tahun = TahunPelajaran::find($tahunPelajaranId)?->nama ?: now()->format('Y');
        $tahun = preg_replace('/\D+/', '', $tahun);
        $prefix = 'UT-'.substr($tahun, 0, 4);
        $urutan = KegiatanUjianCbt::query()->where('kode', 'like', $prefix.'-%')->count() + 1;

        return sprintf('%s-%03d', $prefix, $urutan);
    }

    private function pastikanDapatDiakses(Pengguna $pengguna, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($kegiatan->dapatDiaksesOleh($pengguna), 403);
    }

    private function pastikanKelompokMilikKegiatan(
        KegiatanUjianCbt $kegiatan,
        KelompokPesertaKegiatanUjianCbt $kelompok,
    ): void {
        abort_unless((int) $kelompok->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
    }
}
