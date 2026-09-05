<?php

namespace App\Services\Mobile;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Pegawai;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RiwayatPergantianPengawasUjian;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\RuangUjianCbt;
use App\Services\Cbt\NotifikasiUjianTerpusatService;
use App\Services\Cbt\SinkronkanPelaksanaanUjianTerpusat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PelaksanaanUjianTerpusatMobileService
{
    public function __construct(
        private readonly SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
        private readonly NotifikasiUjianTerpusatService $notifikasi,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $status = (string) ($filter['status'] ?? 'semua');
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 12);
        $cakupan = $this->queryKegiatanDalamCakupan($pengguna);

        $query = (clone $cakupan)
            ->with(['jenisUjianCbt', 'tahunPelajaran'])
            ->withCount([
                'jadwalUjianCbt',
                'jadwalUjianCbt as paket_siap_count' => fn (Builder $query) => $query
                    ->whereHas('ujianCbt', fn (Builder $query) => $query
                        ->whereIn('status', ['terjadwal', 'berlangsung', 'selesai'])),
            ])
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereHas('jenisUjianCbt', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            });

        $paginator = $query->orderByDesc('tanggal_mulai')->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);
        $items = collect($paginator->items());
        $kegiatanIds = $items->pluck('id');
        $jumlahPeserta = PesertaUjianCbt::query()
            ->whereHas('ujianCbt.jadwalUjianCbt', fn (Builder $query) => $query
                ->whereIn('kegiatan_ujian_cbt_id', $kegiatanIds))
            ->selectRaw('ujian_cbt_id, count(*) as jumlah')
            ->groupBy('ujian_cbt_id')
            ->pluck('jumlah', 'ujian_cbt_id');
        $paketPerKegiatan = JadwalUjianCbt::query()
            ->whereIn('kegiatan_ujian_cbt_id', $kegiatanIds)
            ->whereNotNull('ujian_cbt_id')
            ->get(['kegiatan_ujian_cbt_id', 'ujian_cbt_id'])
            ->groupBy('kegiatan_ujian_cbt_id');

        return [
            'ringkasan' => [
                'total' => (clone $cakupan)->count(),
                'aktif' => (clone $cakupan)->where('status', 'aktif')->count(),
                'persiapan' => (clone $cakupan)->where('status', 'draft')->count(),
                'selesai' => (clone $cakupan)->where('status', 'selesai')->count(),
            ],
            'items' => $items->map(function (KegiatanUjianCbt $kegiatan) use ($paketPerKegiatan, $jumlahPeserta): array {
                $paketIds = $paketPerKegiatan->get($kegiatan->id, collect())->pluck('ujian_cbt_id');

                return [
                    'id' => (int) $kegiatan->id,
                    'kode' => $kegiatan->kode,
                    'nama' => $kegiatan->nama,
                    'jenis' => $kegiatan->jenisUjianCbt?->nama,
                    'tahun_pelajaran' => $kegiatan->tahunPelajaran?->nama,
                    'semester' => ucfirst((string) $kegiatan->semester),
                    'tanggal_mulai' => $kegiatan->tanggal_mulai?->toDateString(),
                    'tanggal_selesai' => $kegiatan->tanggal_selesai?->toDateString(),
                    'status' => $kegiatan->status,
                    'label_status' => $kegiatan->labelStatus(),
                    'jumlah_jadwal' => (int) $kegiatan->jadwal_ujian_cbt_count,
                    'paket_siap' => (int) $kegiatan->paket_siap_count,
                    'jumlah_peserta' => (int) $paketIds->sum(fn ($id) => (int) ($jumlahPeserta[$id] ?? 0)),
                ];
            })->values(),
            'referensi' => [
                'status' => [
                    ['kode' => 'semua', 'label' => 'Semua status'],
                    ...collect(KegiatanUjianCbt::DAFTAR_STATUS)
                        ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])
                        ->values()->all(),
                ],
            ],
            'filter' => ['kata_kunci' => $kataKunci, 'status' => $status],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function rincian(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, array $filter = []): array
    {
        $this->pastikanBolehMelihat($pengguna, $kegiatan);
        $this->sinkronisasi->sinkronkanKegiatan($kegiatan, $pengguna);
        $kegiatan->load(['jenisUjianCbt', 'tahunPelajaran']);

        $jadwal = $kegiatan->jadwalUjianCbt()
            ->with([
                'sesiKegiatanUjianCbt', 'mataPelajaran', 'kelas',
                'ujianCbt.jenisUjianCbt',
                'pengawasRuangUjianTerpusat.pengawasUtama',
                'pengawasRuangUjianTerpusat.pengawasPendamping',
                'pengawasRuangUjianTerpusat.riwayatPergantian.pegawaiLama',
                'pengawasRuangUjianTerpusat.riwayatPergantian.pegawaiBaru',
            ])
            ->orderBy('tanggal')->orderBy('waktu_mulai')->orderBy('tingkat')->get();
        $paketIds = $jadwal->pluck('ujian_cbt_id')->filter()->map(fn ($id) => (int) $id)->values();
        $jadwalIds = $jadwal->pluck('id');
        $ruang = RuangUjianCbt::query()
            ->whereIn('jadwal_ujian_cbt_id', $jadwalIds)
            ->with(['ruangKegiatanUjianCbt', 'pengawasUtama', 'pengawasPendamping'])
            ->withCount([
                'pesertaUjianCbt',
                'pesertaUjianCbt as belum_hadir_count' => fn (Builder $query) => $query
                    ->where('status', 'aktif')->where(fn (Builder $query) => $query
                    ->whereNull('status_kehadiran_ujian')->orWhere('status_kehadiran_ujian', 'belum_absen')),
                'pesertaUjianCbt as hadir_belum_mulai_count' => fn (Builder $query) => $query
                    ->where('status', 'aktif')->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat']),
                'pesertaUjianCbt as tidak_hadir_count' => fn (Builder $query) => $query
                    ->where('status', 'aktif')->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa']),
                'pesertaUjianCbt as sedang_count' => fn (Builder $query) => $query->where('status', 'sedang_mengerjakan'),
                'pesertaUjianCbt as selesai_count' => fn (Builder $query) => $query->where('status', 'selesai'),
                'pesertaUjianCbt as terblokir_count' => fn (Builder $query) => $query->where('status', 'terblokir'),
            ])->orderBy('kode')->get();
        $ruangPerJadwal = $ruang->groupBy('jadwal_ujian_cbt_id');
        $bolehMengelola = $this->bolehMengelola($pengguna, $kegiatan);

        $pesertaQuery = PesertaUjianCbt::query()->whereIn('ujian_cbt_id', $paketIds);
        $ringkasan = $this->ringkasanPeserta(clone $pesertaQuery);
        $peserta = $this->peserta($pesertaQuery, $filter);
        $peringatan = $this->peringatan($ruang, $paketIds);

        return [
            'kegiatan' => [
                'id' => (int) $kegiatan->id,
                'kode' => $kegiatan->kode,
                'nama' => $kegiatan->nama,
                'jenis' => $kegiatan->jenisUjianCbt?->nama,
                'tahun_pelajaran' => $kegiatan->tahunPelajaran?->nama,
                'semester' => ucfirst((string) $kegiatan->semester),
                'periode' => $kegiatan->labelPeriode(),
                'status' => $kegiatan->status,
                'label_status' => $kegiatan->labelStatus(),
            ],
            'ringkasan' => $ringkasan + [
                'jumlah_jadwal' => $jadwal->count(),
                'jumlah_ruang' => $ruang->count(),
                'ruang_berlangsung' => $ruang->where('status', 'berlangsung')->count(),
                'bukti_menunggu' => $ruang->where('status_bukti', 'menunggu_pemeriksaan')->count(),
            ],
            'jadwal' => $jadwal->map(fn (JadwalUjianCbt $item) => $this->ringkasJadwal(
                $item,
                $ruangPerJadwal->get($item->id, collect()),
                $bolehMengelola,
            ))->values(),
            'peserta' => $peserta,
            'peringatan' => $peringatan,
            'referensi' => [
                'status_peserta' => [
                    ['kode' => 'semua', 'label' => 'Semua status'],
                    ...collect(PesertaUjianCbt::DAFTAR_STATUS_PELAKSANAAN)
                        ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])
                        ->values()->all(),
                ],
                'pegawai' => $bolehMengelola
                    ? Pegawai::query()->where('aktif', true)->orderBy('nama_lengkap')
                        ->get(['id', 'nama_lengkap', 'nip'])->map(fn (Pegawai $pegawai) => [
                            'id' => (int) $pegawai->id,
                            'nama' => $pegawai->nama_lengkap,
                            'nip' => $pegawai->nip,
                        ])->values()
                    : [],
            ],
            'kemampuan' => [
                'mengatur_pengawas' => $bolehMengelola,
                'membuka_mode_aman' => $bolehMengelola,
                'melihat_ruang' => true,
            ],
            'dihasilkan_pada' => now()->toISOString(),
        ];
    }

    public function aturPengawas(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
        RuangKegiatanUjianCbt $ruang,
        string $peran,
        int $pegawaiId,
        ?string $alasan,
    ): array {
        $this->pastikanBolehMengelola($pengguna, $kegiatan);
        $this->pastikanRelasi($kegiatan, $jadwal, $ruang);
        $pegawaiBaru = Pegawai::query()->where('aktif', true)->findOrFail($pegawaiId);

        [$pegawaiLama, $penugasanBaru] = DB::transaction(function () use (
            $pengguna, $jadwal, $ruang, $peran, $pegawaiBaru, $alasan,
        ): array {
            $penugasan = PengawasRuangUjianTerpusat::query()->firstOrCreate(
                ['jadwal_ujian_cbt_id' => $jadwal->id, 'ruang_kegiatan_ujian_cbt_id' => $ruang->id],
                ['ditugaskan_oleh_pengguna_id' => $pengguna->id],
            );
            $penugasan = PengawasRuangUjianTerpusat::query()->lockForUpdate()->findOrFail($penugasan->id);
            $kolom = $peran === 'utama' ? 'pengawas_utama_pegawai_id' : 'pengawas_pendamping_pegawai_id';
            $kolomLain = $peran === 'utama' ? 'pengawas_pendamping_pegawai_id' : 'pengawas_utama_pegawai_id';
            $pegawaiLama = $penugasan->{$kolom} ? Pegawai::query()->find($penugasan->{$kolom}) : null;

            if ((int) $penugasan->{$kolomLain} === (int) $pegawaiBaru->id) {
                throw ValidationException::withMessages(['pegawai_id' => 'Pegawai ini sudah bertugas pada posisi lain di ruang yang sama.']);
            }
            if ($pegawaiLama && (int) $pegawaiLama->id === (int) $pegawaiBaru->id) {
                throw ValidationException::withMessages(['pegawai_id' => 'Pilih pengawas yang berbeda dari pengawas saat ini.']);
            }
            if ($pegawaiLama && mb_strlen(trim((string) $alasan)) < 5) {
                throw ValidationException::withMessages(['alasan' => 'Alasan penggantian minimal 5 karakter agar riwayat tugas jelas.']);
            }

            if ($pegawaiLama) {
                RiwayatPergantianPengawasUjian::query()->create([
                    'pengawas_ruang_ujian_terpusat_id' => $penugasan->id,
                    'jadwal_ujian_cbt_id' => $jadwal->id,
                    'ruang_kegiatan_ujian_cbt_id' => $ruang->id,
                    'peran_pengawas' => $peran,
                    'pegawai_lama_id' => $pegawaiLama->id,
                    'pegawai_baru_id' => $pegawaiBaru->id,
                    'alasan' => trim((string) $alasan),
                    'diganti_oleh_pengguna_id' => $pengguna->id,
                    'diganti_pada' => now(),
                ]);
            }

            $penugasan->update([$kolom => $pegawaiBaru->id, 'ditugaskan_oleh_pengguna_id' => $pengguna->id]);

            return [$pegawaiLama, $penugasan->fresh()];
        });

        $this->sinkronisasi->sinkronkanJadwal($jadwal->fresh(), $pengguna);
        if ($pegawaiLama) {
            $this->notifikasi->kirimPenggantianPengawas(
                $jadwal, $ruang, $pegawaiLama, $pegawaiBaru, $peran, trim((string) $alasan),
            );
        } else {
            $this->notifikasi->kirimTugasPengawas($jadwal, $ruang, $pegawaiBaru->id, $peran);
        }

        return [
            'penugasan_id' => (int) $penugasanBaru->id,
            'jenis' => $pegawaiLama ? 'penggantian' : 'penugasan',
            'pengawas_lama' => $pegawaiLama?->nama_lengkap,
            'pengawas_baru' => $pegawaiBaru->nama_lengkap,
        ];
    }

    private function queryKegiatanDalamCakupan(Pengguna $pengguna): Builder
    {
        abort_unless($pengguna->memilikiIzin(['cbt.panitia', 'cbt.terpusat_lihat', 'cbt.kelola']), 403);

        return KegiatanUjianCbt::query()
            ->where('status', '!=', 'nonaktif')
            ->when(
                ! $pengguna->memilikiIzin(['cbt.kelola', 'cbt.terpusat_lihat']),
                fn (Builder $query) => $query->whereHas('panitiaUjianCbt', fn (Builder $query) => $query
                    ->where('pegawai_id', $pengguna->pegawai_id)->where('aktif', true)),
            );
    }

    private function pastikanBolehMelihat(Pengguna $pengguna, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($kegiatan->dapatDiaksesOleh($pengguna), 403);
    }

    private function bolehMengelola(Pengguna $pengguna, KegiatanUjianCbt $kegiatan): bool
    {
        return $kegiatan->dapatDiaksesOleh($pengguna)
            && $pengguna->memilikiIzin(['cbt.panitia', 'cbt.kelola']);
    }

    private function pastikanBolehMengelola(Pengguna $pengguna, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($this->bolehMengelola($pengguna, $kegiatan), 403);
    }

    private function pastikanRelasi(
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
        RuangKegiatanUjianCbt $ruang,
    ): void {
        abort_unless((int) $jadwal->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        abort_unless((int) $ruang->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        abort_unless($kegiatan->kelompokPesertaKegiatanUjianCbt()
            ->where('tingkat', $jadwal->tingkat)
            ->whereHas('ruangKegiatanUjianCbt', fn (Builder $query) => $query->whereKey($ruang->id))
            ->exists(), 404);
    }

    private function ringkasanPeserta(Builder $query): array
    {
        $baris = $query->selectRaw("count(*) as total,
            sum(case when status = 'aktif' and (status_kehadiran_ujian is null or status_kehadiran_ujian = 'belum_absen') then 1 else 0 end) as belum_hadir,
            sum(case when status = 'aktif' and status_kehadiran_ujian in ('hadir','terlambat') then 1 else 0 end) as hadir_belum_mulai,
            sum(case when status = 'aktif' and status_kehadiran_ujian in ('sakit','izin','alfa') then 1 else 0 end) as tidak_hadir,
            sum(case when status = 'sedang_mengerjakan' then 1 else 0 end) as sedang_mengerjakan,
            sum(case when status = 'selesai' then 1 else 0 end) as selesai,
            sum(case when status = 'terblokir' then 1 else 0 end) as terblokir")
            ->first();

        return collect(['total', 'belum_hadir', 'hadir_belum_mulai', 'tidak_hadir', 'sedang_mengerjakan', 'selesai', 'terblokir'])
            ->mapWithKeys(fn (string $kolom) => [$kolom => (int) ($baris->{$kolom} ?? 0)])->all();
    }

    private function peserta(Builder $query, array $filter): array
    {
        $status = (string) ($filter['status_peserta'] ?? 'semua');
        $jadwalId = isset($filter['jadwal_id']) ? (int) $filter['jadwal_id'] : null;
        $ruangId = isset($filter['ruang_id']) ? (int) $filter['ruang_id'] : null;
        $kataKunci = trim((string) ($filter['kata_kunci_peserta'] ?? ''));
        $halaman = (int) ($filter['halaman_peserta'] ?? 1);

        $query->with(['anggotaKelas.siswa', 'kelasUjianCbt.kelas', 'ruangUjianCbt', 'ujianCbt.jadwalUjianCbt.mataPelajaran'])
            ->withCount([
                'jawabanPesertaUjianCbt as jawaban_tersimpan_count' => fn (Builder $query) => $query
                    ->whereNotNull('jawaban'),
            ])
            ->when($jadwalId, fn (Builder $query) => $query->whereHas('ujianCbt.jadwalUjianCbt', fn (Builder $query) => $query->whereKey($jadwalId)))
            ->when($ruangId, fn (Builder $query) => $query->where('ruang_ujian_cbt_id', $ruangId))
            ->when($status !== 'semua', fn (Builder $query) => $this->terapkanStatus($query, $status))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereRaw('LOWER(nomor_peserta) LIKE ?', [$pola])
                        ->orWhereHas('anggotaKelas.siswa', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola]));
                });
            });
        $paginator = $query->orderBy('ruang_ujian_cbt_id')->orderBy('nomor_meja')
            ->paginate(30, ['*'], 'halaman_peserta', $halaman);

        return [
            'items' => collect($paginator->items())->map(function (PesertaUjianCbt $item): array {
                $jadwal = $item->ujianCbt?->jadwalUjianCbt?->first();
                $terlambatHeartbeat = $item->status === 'sedang_mengerjakan'
                    && $item->heartbeat_terakhir_pada
                    && $item->heartbeat_terakhir_pada->lt(now()->subMinutes(2));

                return [
                    'id' => (int) $item->id,
                    'nama' => $item->anggotaKelas?->siswa?->nama_lengkap ?? 'Siswa',
                    'nisn' => $item->anggotaKelas?->siswa?->nisn,
                    'nomor_peserta' => $item->nomor_peserta,
                    'kelas' => $item->kelasUjianCbt?->kelas?->nama ?? '-',
                    'ruang' => $item->ruangUjianCbt?->nama ?? '-',
                    'ruang_id' => $item->ruang_ujian_cbt_id ? (int) $item->ruang_ujian_cbt_id : null,
                    'jadwal_id' => $jadwal ? (int) $jadwal->id : null,
                    'mata_pelajaran' => $jadwal?->mataPelajaran?->nama ?? '-',
                    'status' => $item->statusPelaksanaan(),
                    'label_status' => $item->labelStatusPelaksanaan(),
                    'jawaban_tersimpan' => (int) $item->jawaban_tersimpan_count,
                    'jumlah_pindah_aplikasi' => (int) $item->jumlah_pindah_aplikasi,
                    'heartbeat_terakhir_pada' => $item->heartbeat_terakhir_pada?->toISOString(),
                    'heartbeat_terlambat' => (bool) $terlambatHeartbeat,
                    'dapat_dibuka_mode_aman' => $item->status === 'terblokir',
                ];
            })->values(),
            'filter' => [
                'status' => $status,
                'jadwal_id' => $jadwalId,
                'ruang_id' => $ruangId,
                'kata_kunci' => $kataKunci,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    private function ringkasJadwal(JadwalUjianCbt $jadwal, $ruang, bool $bolehMengelola): array
    {
        $paket = $jadwal->ujianCbt;

        return [
            'id' => (int) $jadwal->id,
            'mata_pelajaran' => $jadwal->mataPelajaran?->nama ?? 'Mata pelajaran belum ditentukan',
            'tingkat' => (int) $jadwal->tingkat,
            'kelas' => $jadwal->kelas->pluck('nama')->values(),
            'tanggal' => $jadwal->tanggal?->toDateString(),
            'waktu' => $jadwal->labelWaktu(),
            'sesi' => $jadwal->sesiKegiatanUjianCbt?->nama ?: $jadwal->label_sesi,
            'status' => $jadwal->status,
            'label_status' => $jadwal->labelStatus(),
            'paket' => $paket ? [
                'id' => (int) $paket->id,
                'nama' => $paket->nama,
                'status' => $paket->status,
                'label_status' => $paket->labelStatus(),
                'token' => $paket->token,
                'memerlukan_token' => (bool) $paket->jenisUjianCbt?->memerlukan_token,
            ] : null,
            'ruang' => $ruang->map(fn (RuangUjianCbt $item) => [
                'id' => (int) $item->id,
                'ruang_kegiatan_id' => (int) $item->ruang_kegiatan_ujian_cbt_id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'lokasi' => $item->lokasi,
                'status' => $item->status,
                'label_status' => $item->labelStatus(),
                'status_bukti' => $item->status_bukti,
                'label_status_bukti' => $item->labelStatusBukti(),
                'pengawas_utama' => $item->pengawasUtama ? [
                    'id' => (int) $item->pengawasUtama->id,
                    'nama' => $item->pengawasUtama->nama_lengkap,
                ] : null,
                'pengawas_pendamping' => $item->pengawasPendamping ? [
                    'id' => (int) $item->pengawasPendamping->id,
                    'nama' => $item->pengawasPendamping->nama_lengkap,
                ] : null,
                'ringkasan' => [
                    'total' => (int) $item->peserta_ujian_cbt_count,
                    'belum_hadir' => (int) $item->belum_hadir_count,
                    'hadir_belum_mulai' => (int) $item->hadir_belum_mulai_count,
                    'tidak_hadir' => (int) $item->tidak_hadir_count,
                    'sedang_mengerjakan' => (int) $item->sedang_count,
                    'selesai' => (int) $item->selesai_count,
                    'terblokir' => (int) $item->terblokir_count,
                ],
                'dapat_mengatur_pengawas' => $bolehMengelola,
            ])->values(),
        ];
    }

    private function peringatan($ruang, $paketIds): array
    {
        $items = collect();
        $ruang->filter(fn (RuangUjianCbt $item) => ! $item->pengawas_utama_pegawai_id)
            ->each(fn (RuangUjianCbt $item) => $items->push([
                'jenis' => 'pengawas_kosong', 'judul' => 'Pengawas utama belum ditentukan',
                'keterangan' => $item->nama, 'ruang_id' => (int) $item->id,
            ]));
        PesertaUjianCbt::query()->whereIn('ujian_cbt_id', $paketIds)
            ->where('status', 'terblokir')->with(['anggotaKelas.siswa', 'ruangUjianCbt'])
            ->limit(20)->get()->each(fn (PesertaUjianCbt $item) => $items->push([
                'jenis' => 'mode_aman', 'judul' => 'Peserta ditahan Mode Aman',
                'keterangan' => ($item->anggotaKelas?->siswa?->nama_lengkap ?? 'Siswa').' · '.($item->ruangUjianCbt?->nama ?? '-'),
                'peserta_id' => (int) $item->id, 'ruang_id' => (int) $item->ruang_ujian_cbt_id,
            ]));
        PesertaUjianCbt::query()->whereIn('ujian_cbt_id', $paketIds)
            ->where('status', 'sedang_mengerjakan')->whereNotNull('heartbeat_terakhir_pada')
            ->where('heartbeat_terakhir_pada', '<', now()->subMinutes(2))
            ->with(['anggotaKelas.siswa', 'ruangUjianCbt'])->limit(20)->get()
            ->each(fn (PesertaUjianCbt $item) => $items->push([
                'jenis' => 'heartbeat', 'judul' => 'Koneksi peserta perlu diperiksa',
                'keterangan' => ($item->anggotaKelas?->siswa?->nama_lengkap ?? 'Siswa').' · '.($item->ruangUjianCbt?->nama ?? '-'),
                'peserta_id' => (int) $item->id, 'ruang_id' => (int) $item->ruang_ujian_cbt_id,
            ]));

        return $items->take(40)->values()->all();
    }

    private function terapkanStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'belum_hadir' => $query->where('status', 'aktif')->where(fn (Builder $query) => $query
                ->whereNull('status_kehadiran_ujian')->orWhere('status_kehadiran_ujian', 'belum_absen')),
            'hadir_belum_mulai' => $query->where('status', 'aktif')->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat']),
            'tidak_hadir' => $query->where('status', 'aktif')->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa']),
            'sedang_mengerjakan', 'selesai', 'nonaktif', 'terblokir' => $query->where('status', $status),
            default => $query,
        };
    }
}
