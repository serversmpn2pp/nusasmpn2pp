<?php

namespace App\Services\Mobile;

use App\Models\BuktiLaporanPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\AksesLaporanPembinaanService;
use App\Services\Pembinaan\AntreanVerifikasiPelanggaranService;
use Illuminate\Database\Eloquent\Builder;

class LaporanSiswaMobileService
{
    public function __construct(private AksesLaporanPembinaanService $akses) {}

    public function daftar(Pengguna $pengguna, array $filter, bool $khususGuruWali = false): array
    {
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $tingkat = $filter['tingkat'] ?? 'semua';
        $jenis = $filter['jenis_laporan'] ?? 'semua';
        $verifikasi = $filter['status_verifikasi'] ?? 'semua';
        $tahunId = isset($filter['tahun_pelajaran_id']) ? (int) $filter['tahun_pelajaran_id'] : null;
        $kelasId = isset($filter['kelas_id']) ? (int) $filter['kelas_id'] : null;
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));
        $cakupan = $khususGuruWali
            ? $this->queryCakupanGuruWali($pengguna)
            : $this->queryCakupan($pengguna);

        $ringkasan = [
            'total' => (clone $cakupan)->count(),
            'kejadian' => (clone $cakupan)->where('jenis_laporan', 'kejadian')->count(),
            'pembinaan' => (clone $cakupan)->where('jenis_laporan', 'pembinaan')->count(),
            'pelanggaran' => (clone $cakupan)->where('jenis_laporan', 'pelanggaran')->count(),
            'menunggu_bk' => (clone $cakupan)->whereIn('status_verifikasi', AntreanVerifikasiPelanggaranService::STATUS_BK)->count(),
            'menunggu_wakil' => (clone $cakupan)->whereIn('status_verifikasi', AntreanVerifikasiPelanggaranService::STATUS_WAKIL)->count(),
            'disahkan' => (clone $cakupan)->where('status_verifikasi', 'disahkan')->count(),
        ];

        $query = (clone $cakupan)
            ->with([
                'siswa:id,nama_lengkap,nis,nisn',
                'kelas:id,nama,tingkat',
                'tahunPelajaran:id,nama,aktif',
                'kategoriPembinaanSiswa:id,nama',
                'pelaporPegawai:id,nama_lengkap,nip',
                'dibuatOlehPengguna:id,nama',
            ])
            ->withCount([
                'butirPelanggaranLaporan',
                'buktiLaporanPembinaanSiswa',
                'saksiLaporanPembinaanSiswa',
                'klarifikasiSiswaPembinaan',
                'tindakLanjutPembinaanSiswa',
            ])
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->when($tingkat !== 'semua', fn (Builder $query) => $query->where('tingkat', $tingkat))
            ->when($jenis !== 'semua', fn (Builder $query) => $query->where('jenis_laporan', $jenis))
            ->when($verifikasi !== 'semua', fn (Builder $query) => $query->where('status_verifikasi', $verifikasi))
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->when($kelasId, fn (Builder $query) => $query->where('kelas_id', $kelasId))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereRaw('LOWER(nomor_laporan) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(tempat_kejadian, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(kronologi, '')) LIKE ?", [$pola])
                        ->orWhereHas('siswa', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                            ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                            ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]))
                        ->orWhereHas('butirPelanggaranLaporan', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama_pelanggaran) LIKE ?', [$pola])
                            ->orWhereRaw('LOWER(kode_pelanggaran) LIKE ?', [$pola]));
                });
            })
            ->orderByDesc('tanggal_kejadian')
            ->orderByDesc('id');

        $paginasi = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);
        $kelasCakupan = (clone $cakupan)->whereNotNull('kelas_id')->distinct()->pluck('kelas_id');

        return [
            'items' => collect($paginasi->items())->map(fn (LaporanPembinaanSiswa $laporan) => $this->ringkas($laporan))->values(),
            'ringkasan' => $ringkasan,
            'pilihan' => [
                'status' => $this->pilihan(LaporanPembinaanSiswa::DAFTAR_STATUS),
                'tingkat' => $this->pilihan(LaporanPembinaanSiswa::DAFTAR_TINGKAT),
                'jenis_laporan' => $this->pilihan(LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN),
                'status_verifikasi' => $this->pilihan(LaporanPembinaanSiswa::DAFTAR_STATUS_VERIFIKASI),
                'tahun_pelajaran' => TahunPelajaran::query()
                    ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get()
                    ->map(fn (TahunPelajaran $tahun) => [
                        'id' => (int) $tahun->id,
                        'nama' => $tahun->nama,
                        'aktif' => (bool) $tahun->aktif,
                    ])->values(),
                'kelas' => Kelas::query()
                    ->when($khususGuruWali || ! $this->akses->aksesLuas($pengguna), fn (Builder $query) => $query->whereIn('id', $kelasCakupan))
                    ->orderBy('tingkat')->orderBy('nama')->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat'])
                    ->map(fn (Kelas $kelas) => [
                        'id' => (int) $kelas->id,
                        'tahun_pelajaran_id' => (int) $kelas->tahun_pelajaran_id,
                        'nama' => $kelas->nama,
                        'tingkat' => (int) $kelas->tingkat,
                    ])->values(),
            ],
            'filter' => [
                'kata_kunci' => $kataKunci,
                'status' => $status,
                'tingkat' => $tingkat,
                'jenis_laporan' => $jenis,
                'status_verifikasi' => $verifikasi,
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
                'cakupan_luas' => ! $khususGuruWali && $this->akses->aksesLuas($pengguna),
                'dapat_melaporkan' => $pengguna->memilikiIzin('poin_siswa.lapor'),
                'konteks_guru_wali' => $khususGuruWali,
            ],
        ];
    }

    public function daftarGuruWali(Pengguna $pengguna, array $filter): array
    {
        $this->pastikanGuruWali($pengguna);

        return $this->daftar($pengguna, $filter, true);
    }

    public function rincian(Pengguna $pengguna, LaporanPembinaanSiswa $laporan): array
    {
        $this->akses->pastikanBolehLihat($pengguna, $laporan);
        $laporan->load([
            'siswa:id,nama_lengkap,nis,nisn',
            'kelas:id,nama,tingkat',
            'tahunPelajaran:id,nama,aktif',
            'kategoriPembinaanSiswa:id,nama',
            'pelaporPegawai:id,nama_lengkap,nip',
            'waliKelasPegawai:id,nama_lengkap,nip',
            'guruWaliPegawai:id,nama_lengkap,nip',
            'dibuatOlehPengguna:id,nama',
            'butirPelanggaranLaporan',
            'verifikasiBkPelanggaran' => fn ($query) => $query->with(['bkPegawai:id,nama_lengkap', 'pengguna:id,nama'])->latest('diverifikasi_pada'),
            'persetujuanPelanggaran' => fn ($query) => $query->with(['pegawai:id,nama_lengkap', 'pengguna:id,nama'])->latest('diputuskan_pada'),
            'buktiLaporanPembinaanSiswa' => fn ($query) => $query->latest('diunggah_pada'),
            'saksiLaporanPembinaanSiswa' => fn ($query) => $query->oldest(),
            'klarifikasiSiswaPembinaan' => fn ($query) => $query->with('dicatatOlehPengguna:id,nama')->latest('disampaikan_pada'),
            'tindakLanjutPembinaanSiswa' => fn ($query) => $query->with('petugasPegawai:id,nama_lengkap')->orderByDesc('tanggal_tindak_lanjut')->orderByDesc('id'),
            'riwayatProsesPembinaanSiswa' => fn ($query) => $query->with('pengguna:id,nama')->latest('terjadi_pada')->latest('id'),
        ]);

        return [
            'laporan' => $this->ringkas($laporan) + [
                'kronologi' => $laporan->kronologi,
                'tindakan_awal' => $laporan->tindakan_awal,
                'wali_kelas' => $this->pegawai($laporan->waliKelasPegawai),
                'guru_wali' => $this->pegawai($laporan->guruWaliPegawai),
            ],
            'butir_pelanggaran' => $laporan->butirPelanggaranLaporan->map(fn ($item) => [
                'id' => (int) $item->id,
                'jenis_pelanggaran_id' => (int) $item->jenis_pelanggaran_siswa_id,
                'kode' => $item->kode_pelanggaran,
                'nama' => $item->nama_pelanggaran,
                'tingkat' => $item->tingkat,
                'poin' => (int) $item->poin,
                'catatan' => $item->catatan,
            ])->values(),
            'pemeriksaan_bk' => $laporan->verifikasiBkPelanggaran->map(fn ($item) => [
                'id' => (int) $item->id,
                'hasil' => $item->hasil,
                'label_hasil' => $item->labelHasil(),
                'catatan' => $item->catatan,
                'petugas' => $item->bkPegawai?->nama_lengkap ?? $item->pengguna?->nama,
                'diproses_pada' => $item->diverifikasi_pada?->toISOString(),
            ])->values(),
            'persetujuan' => $laporan->persetujuanPelanggaran->map(fn ($item) => [
                'id' => (int) $item->id,
                'jenis' => $item->jenis_persetujuan,
                'label_jenis' => $item->labelJenis(),
                'keputusan' => $item->keputusan,
                'label_keputusan' => $item->labelKeputusan(),
                'catatan' => $item->catatan,
                'petugas' => $item->pegawai?->nama_lengkap ?? $item->pengguna?->nama,
                'diproses_pada' => $item->diputuskan_pada?->toISOString(),
            ])->values(),
            'bukti' => $laporan->buktiLaporanPembinaanSiswa->map(fn (BuktiLaporanPembinaanSiswa $item) => [
                'id' => (int) $item->id,
                'jenis' => $item->jenis,
                'nama_file' => $item->nama_file_asli,
                'tipe_file' => $item->tipe_file,
                'ukuran_file' => (int) $item->ukuran_file,
                'ukuran_ringkas' => $item->ukuranRingkas(),
                'keterangan' => $item->keterangan,
                'diunggah_pada' => $item->diunggah_pada?->toISOString(),
            ])->values(),
            'saksi' => $laporan->saksiLaporanPembinaanSiswa->map(fn ($item) => [
                'id' => (int) $item->id,
                'jenis' => $item->jenis_saksi,
                'label_jenis' => $item->labelJenis(),
                'nama' => $item->nama_saksi,
                'pernyataan' => $item->pernyataan,
                'dicatat_pada' => $item->created_at?->toISOString(),
            ])->values(),
            'klarifikasi' => $laporan->klarifikasiSiswaPembinaan->map(fn ($item) => [
                'id' => (int) $item->id,
                'metode' => $item->metode,
                'label_metode' => $item->labelMetode(),
                'isi' => $item->isi_klarifikasi,
                'pendamping' => $item->pendamping,
                'dicatat_oleh' => $item->dicatatOlehPengguna?->nama,
                'disampaikan_pada' => $item->disampaikan_pada?->toISOString(),
            ])->values(),
            'tindak_lanjut' => $laporan->tindakLanjutPembinaanSiswa->map(fn ($item) => [
                'id' => (int) $item->id,
                'jenis' => $item->jenis_tindak_lanjut,
                'label_jenis' => $item->labelJenis(),
                'tanggal' => $item->tanggal_tindak_lanjut?->toDateString(),
                'waktu' => $item->waktuTindakLanjutRingkas(),
                'petugas' => $item->petugasPegawai?->nama_lengkap,
                'ringkasan' => $item->ringkasan,
                'hasil' => $item->hasil,
                'rencana_lanjutan' => $item->rencana_lanjutan,
                'status' => $item->status_laporan,
                'label_status' => $item->labelStatusLaporan(),
            ])->values(),
            'linimasa' => $laporan->riwayatProsesPembinaanSiswa->map(fn ($item) => [
                'id' => (int) $item->id,
                'kode' => $item->kode_kegiatan,
                'judul' => $item->judul,
                'keterangan' => $item->keterangan,
                'status_sebelum' => $item->status_sebelum,
                'status_sesudah' => $item->status_sesudah,
                'pengguna' => $item->pengguna?->nama,
                'terjadi_pada' => $item->terjadi_pada?->toISOString(),
            ])->values(),
            'hak_akses' => [
                'dapat_kelola_fakta' => $this->akses->bolehKelolaFakta($pengguna, $laporan),
                'dapat_mencatat_klarifikasi' => $this->akses->bolehMencatatKlarifikasi($pengguna, $laporan),
            ],
        ];
    }

    public function rincianGuruWali(Pengguna $pengguna, LaporanPembinaanSiswa $laporan): array
    {
        $this->pastikanLaporanGuruWali($pengguna, $laporan);

        return $this->rincian($pengguna, $laporan);
    }

    public function pastikanLaporanGuruWali(Pengguna $pengguna, LaporanPembinaanSiswa $laporan): void
    {
        $this->pastikanGuruWali($pengguna);
        abort_unless(in_array((int) $laporan->siswa_id, $pengguna->siswaWaliIds(), true), 403);
    }

    private function queryCakupan(Pengguna $pengguna): Builder
    {
        $query = LaporanPembinaanSiswa::query();
        if ($this->akses->aksesLuas($pengguna)) {
            return $query;
        }

        $kelasWaliIds = $pengguna->kelasWaliIds();
        $siswaWaliIds = $pengguna->siswaWaliIds();

        return $query->where(function (Builder $query) use ($pengguna, $kelasWaliIds, $siswaWaliIds): void {
            $query->where('dibuat_oleh_pengguna_id', $pengguna->id)
                ->when($pengguna->pegawai_id, fn (Builder $query) => $query->orWhere('pelapor_pegawai_id', $pengguna->pegawai_id))
                ->when($kelasWaliIds !== [], fn (Builder $query) => $query->orWhereIn('kelas_id', $kelasWaliIds))
                ->when($siswaWaliIds !== [], fn (Builder $query) => $query->orWhereIn('siswa_id', $siswaWaliIds));
        });
    }

    private function queryCakupanGuruWali(Pengguna $pengguna): Builder
    {
        $siswaWaliIds = $pengguna->siswaWaliIds();

        return LaporanPembinaanSiswa::query()
            ->when(
                $siswaWaliIds === [],
                fn (Builder $query) => $query->whereRaw('1 = 0'),
                fn (Builder $query) => $query->whereIn('siswa_id', $siswaWaliIds),
            );
    }

    private function pastikanGuruWali(Pengguna $pengguna): void
    {
        abort_unless($pengguna->pegawai_id && $pengguna->memilikiPeran('guru_wali'), 403);
    }

    public function ringkas(LaporanPembinaanSiswa $laporan): array
    {
        return [
            'id' => (int) $laporan->id,
            'nomor_laporan' => $laporan->nomor_laporan,
            'jenis_laporan' => $laporan->jenis_laporan,
            'label_jenis_laporan' => $laporan->labelJenisLaporan(),
            'sumber_laporan' => $laporan->sumber_laporan,
            'tanggal_kejadian' => $laporan->tanggal_kejadian?->toDateString(),
            'waktu_kejadian' => $laporan->waktuKejadianRingkas(),
            'tempat_kejadian' => $laporan->tempat_kejadian,
            'siswa' => $laporan->siswa ? [
                'id' => (int) $laporan->siswa->id,
                'nama' => $laporan->siswa->nama_lengkap,
                'nis' => $laporan->siswa->nis,
                'nisn' => $laporan->siswa->nisn,
            ] : null,
            'kelas' => $laporan->kelas ? [
                'id' => (int) $laporan->kelas->id,
                'nama' => $laporan->kelas->nama,
                'tingkat' => (int) $laporan->kelas->tingkat,
            ] : null,
            'tahun_pelajaran' => $laporan->tahunPelajaran ? [
                'id' => (int) $laporan->tahunPelajaran->id,
                'nama' => $laporan->tahunPelajaran->nama,
            ] : null,
            'kategori' => $laporan->kategoriPembinaanSiswa ? [
                'id' => (int) $laporan->kategoriPembinaanSiswa->id,
                'nama' => $laporan->kategoriPembinaanSiswa->nama,
            ] : null,
            'pelapor' => $this->pegawai($laporan->pelaporPegawai) ?? ($laporan->dibuatOlehPengguna ? [
                'id' => null,
                'nama' => $laporan->dibuatOlehPengguna->nama,
                'nip' => null,
            ] : null),
            'tingkat' => $laporan->tingkat,
            'label_tingkat' => $laporan->labelTingkat(),
            'status' => $laporan->status,
            'label_status' => $laporan->labelStatus(),
            'status_verifikasi' => $laporan->status_verifikasi,
            'label_status_verifikasi' => $laporan->labelStatusVerifikasi(),
            'total_poin' => (int) $laporan->total_poin,
            'tenggat' => [
                'tahap' => $laporan->tahap_batas_proses,
                'label_tahap' => match ($laporan->tahap_batas_proses) {
                    'pemeriksaan_bk' => 'Pemeriksaan BK',
                    'pengesahan_wakil' => 'Pengesahan Wakil Kesiswaan',
                    default => $laporan->tahap_batas_proses ? str($laporan->tahap_batas_proses)->headline()->toString() : null,
                },
                'pada' => $laporan->batas_proses_pada?->toISOString(),
                'terlambat' => (bool) ($laporan->batas_proses_pada?->isPast() && ! $this->akses->statusFinal($laporan)),
            ],
            'jumlah_butir' => (int) ($laporan->butir_pelanggaran_laporan_count ?? $laporan->butirPelanggaranLaporan->count()),
            'jumlah_bukti' => (int) ($laporan->bukti_laporan_pembinaan_siswa_count ?? $laporan->buktiLaporanPembinaanSiswa->count()),
            'jumlah_saksi' => (int) ($laporan->saksi_laporan_pembinaan_siswa_count ?? $laporan->saksiLaporanPembinaanSiswa->count()),
            'jumlah_klarifikasi' => (int) ($laporan->klarifikasi_siswa_pembinaan_count ?? $laporan->klarifikasiSiswaPembinaan->count()),
            'jumlah_tindak_lanjut' => (int) ($laporan->tindak_lanjut_pembinaan_siswa_count ?? $laporan->tindakLanjutPembinaanSiswa->count()),
            'dibuat_pada' => $laporan->created_at?->toISOString(),
        ];
    }

    private function pegawai($pegawai): ?array
    {
        return $pegawai ? [
            'id' => (int) $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'nip' => $pegawai->nip,
        ] : null;
    }

    private function pilihan(array $items): array
    {
        return collect($items)->map(fn (string $label, string $kode) => [
            'kode' => $kode,
            'label' => $label,
        ])->values()->all();
    }
}
