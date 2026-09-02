<?php

namespace App\Services\Mobile;

use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\PeringatanDiniSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\AksesRekapPoinSiswaService;
use App\Services\Pembinaan\ProsesPeringatanDiniSiswaService;
use Illuminate\Database\Eloquent\Builder;

class PeringatanDiniSiswaMobileService
{
    public function __construct(
        private AksesRekapPoinSiswaService $akses,
        private ProsesPeringatanDiniSiswaService $proses,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tahunId = isset($filter['tahun_pelajaran_id'])
            ? (int) $filter['tahun_pelajaran_id']
            : TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = isset($filter['kelas_id']) ? (int) $filter['kelas_id'] : null;
        $jenis = (string) ($filter['jenis'] ?? 'semua');
        $tingkat = (string) ($filter['tingkat'] ?? 'semua');
        $status = (string) ($filter['status'] ?? 'aktif');
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));
        $siswaIds = $this->querySiswaCakupan($pengguna, $tahunId, $kelasId, $kataKunci)
            ->pluck('siswa.id');
        $cakupan = PeringatanDiniSiswa::query()
            ->whereIn('siswa_id', $siswaIds)
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId));
        $aktif = (clone $cakupan)->where('status', 'aktif');
        $ringkasan = [
            'total_aktif' => (clone $aktif)->count(),
            'penting' => (clone $aktif)->where('tingkat', 'penting')->count(),
            'mendekati_sanksi' => (clone $aktif)->where('jenis', 'mendekati_sanksi')->count(),
            'pola_berulang' => (clone $aktif)->whereIn('jenis', ['pelanggaran_berulang', 'sering_terlambat'])->count(),
            'sanksi_aktif' => (clone $aktif)->where('jenis', 'sanksi_belum_selesai')->count(),
        ];

        $paginasi = (clone $cakupan)
            ->with($this->relasi($tahunId))
            ->when($jenis !== 'semua', fn (Builder $query) => $query->where('jenis', $jenis))
            ->when($tingkat !== 'semua', fn (Builder $query) => $query->where('tingkat', $tingkat))
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN tingkat = 'penting' THEN 0 ELSE 1 END")
            ->latest('terakhir_terdeteksi_pada')
            ->latest('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginasi->items())->map(fn (PeringatanDiniSiswa $item) => $this->ringkas($item))->values(),
            'ringkasan' => $ringkasan,
            'pilihan' => [
                'jenis' => $this->pilihan(PeringatanDiniSiswa::DAFTAR_JENIS),
                'tingkat' => $this->pilihan(PeringatanDiniSiswa::DAFTAR_TINGKAT),
                'status' => $this->pilihan(PeringatanDiniSiswa::DAFTAR_STATUS),
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
                'jenis' => $jenis,
                'tingkat' => $tingkat,
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
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function rincian(Pengguna $pengguna, PeringatanDiniSiswa $peringatan): array
    {
        $peringatan->load($this->relasi($peringatan->tahun_pelajaran_id));
        abort_unless($peringatan->siswa && $this->akses->bolehLihat(
            $pengguna,
            $peringatan->siswa,
            $peringatan->tahun_pelajaran_id,
        ), 403);

        return [
            'peringatan' => $this->ringkas($peringatan),
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function proses(Pengguna $pengguna, ?int $tahunId): array
    {
        abort_unless($pengguna->administrator() || $pengguna->memilikiIzin('poin_siswa.pengaturan'), 403);

        return $this->proses->proses($tahunId);
    }

    private function querySiswaCakupan(
        Pengguna $pengguna,
        ?int $tahunId,
        ?int $kelasId = null,
        string $kataKunci = '',
    ): Builder {
        $query = Siswa::query()
            ->where('aktif', true)
            ->when($tahunId, fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('tahun_pelajaran_id', $tahunId)
                ->where('status_keanggotaan', 'aktif')))
            ->when($kelasId, fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')
                ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]);
                });
            });
        $this->akses->terapkanCakupan($query, $pengguna, $tahunId);

        return $query;
    }

    private function relasi(?int $tahunId): array
    {
        return [
            'siswa' => fn ($query) => $query->with([
                'anggotaKelas' => fn ($query) => $query
                    ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                    ->where('status_keanggotaan', 'aktif')
                    ->with('kelas:id,nama,wali_kelas_id'),
                'penugasanGuruWaliSiswa' => fn ($query) => $query
                    ->where('aktif', true)->with('guruWali:id,nama_lengkap,nip'),
                'pendampinganSiswa' => fn ($query) => $query
                    ->where('status', 'dalam_proses')
                    ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                    ->with('petugasPegawai:id,nama_lengkap,nip')
                    ->latest('id'),
            ]),
            'tahunPelajaran:id,nama,aktif',
            'sanksiPoinSiswa.aturanSanksiPoin:id,nama,batas_poin',
        ];
    }

    private function ringkas(PeringatanDiniSiswa $item): array
    {
        $anggota = $item->siswa?->anggotaKelas?->first();
        $guruWali = $item->siswa?->penugasanGuruWaliSiswa?->first()?->guruWali;
        $pendampingan = $item->siswa?->pendampinganSiswa?->first();
        $sanksi = $item->sanksiPoinSiswa;

        return [
            'id' => (int) $item->id,
            'siswa' => $item->siswa ? [
                'id' => (int) $item->siswa->id,
                'nama' => $item->siswa->nama_lengkap,
                'nis' => $item->siswa->nis,
                'nisn' => $item->siswa->nisn,
            ] : null,
            'kelas' => $anggota?->kelas ? [
                'id' => (int) $anggota->kelas->id,
                'nama' => $anggota->kelas->nama,
            ] : null,
            'tahun_pelajaran' => $item->tahunPelajaran ? [
                'id' => (int) $item->tahunPelajaran->id,
                'nama' => $item->tahunPelajaran->nama,
            ] : null,
            'guru_wali' => $guruWali ? [
                'id' => (int) $guruWali->id,
                'nama' => $guruWali->nama_lengkap,
                'nip' => $guruWali->nip,
            ] : null,
            'jenis' => $item->jenis,
            'label_jenis' => $item->labelJenis(),
            'tingkat' => $item->tingkat,
            'label_tingkat' => $item->labelTingkat(),
            'status' => $item->status,
            'label_status' => $item->labelStatus(),
            'judul' => $item->judul,
            'pesan' => $item->pesan,
            'data_pendukung' => $item->data_pendukung ?? [],
            'data_pendukung_ringkas' => $this->dataPendukungRingkas($item),
            'siklus' => (int) $item->siklus,
            'terdeteksi_pada' => $item->terdeteksi_pada?->toISOString(),
            'terakhir_terdeteksi_pada' => $item->terakhir_terdeteksi_pada?->toISOString(),
            'diselesaikan_pada' => $item->diselesaikan_pada?->toISOString(),
            'pendampingan_aktif' => $pendampingan ? [
                'id' => (int) $pendampingan->id,
                'jenis' => $pendampingan->jenis_tindakan,
                'label_jenis' => $pendampingan->labelJenis(),
                'tanggal' => $pendampingan->tanggal_tindak_lanjut?->toDateString(),
                'petugas' => $pendampingan->petugasPegawai ? [
                    'id' => (int) $pendampingan->petugasPegawai->id,
                    'nama' => $pendampingan->petugasPegawai->nama_lengkap,
                ] : null,
            ] : null,
            'sanksi' => $sanksi ? [
                'id' => (int) $sanksi->id,
                'nama' => $sanksi->aturanSanksiPoin?->nama ?? 'Sanksi poin',
                'status' => $sanksi->status,
                'label_status' => $sanksi->labelStatus(),
                'batas_pelaksanaan' => $sanksi->batas_pelaksanaan?->toDateString(),
                'terlambat' => $sanksi->terlambat(),
            ] : null,
        ];
    }

    private function dataPendukungRingkas(PeringatanDiniSiswa $item): array
    {
        $data = $item->data_pendukung ?? [];

        return match ($item->jenis) {
            'mendekati_sanksi' => [
                ['label' => 'Poin saat ini', 'nilai' => (int) ($data['total_poin'] ?? 0)],
                ['label' => 'Sisa menuju ambang', 'nilai' => (int) ($data['jarak_poin'] ?? 0).' poin'],
                ['label' => 'Progres ambang', 'nilai' => (int) ($data['persentase'] ?? 0).'%'],
            ],
            'pelanggaran_berulang' => [
                ['label' => 'Jumlah pelanggaran', 'nilai' => (int) ($data['jumlah_pelanggaran'] ?? 0)],
                ['label' => 'Poin periode', 'nilai' => (int) ($data['total_poin_periode'] ?? 0)],
                ['label' => 'Periode deteksi', 'nilai' => (int) ($data['periode_hari'] ?? 0).' hari'],
            ],
            'sering_terlambat' => [
                ['label' => 'Jumlah terlambat', 'nilai' => (int) ($data['jumlah_keterlambatan'] ?? 0).' kali'],
                ['label' => 'Total keterlambatan', 'nilai' => (int) ($data['total_menit'] ?? 0).' menit'],
                ['label' => 'Periode deteksi', 'nilai' => (int) ($data['periode_hari'] ?? 0).' hari'],
            ],
            'sanksi_belum_selesai' => [
                ['label' => 'Status sanksi', 'nilai' => $item->sanksiPoinSiswa?->labelStatus() ?? ($data['status_sanksi'] ?? '-')],
                ['label' => 'Batas pelaksanaan', 'nilai' => $data['batas_pelaksanaan'] ?? '-'],
                ['label' => 'Keterlambatan', 'nilai' => ($data['terlambat'] ?? false) ? 'Melewati batas' : 'Belum melewati batas'],
            ],
            default => [],
        };
    }

    private function daftarKelas(Pengguna $pengguna, ?int $tahunId): array
    {
        $siswaIds = $this->querySiswaCakupan($pengguna, $tahunId)->pluck('siswa.id');

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

    private function pilihan(array $items): array
    {
        return collect($items)->map(fn (string $label, string $kode) => [
            'kode' => $kode,
            'label' => $label,
        ])->values()->all();
    }

    private function hakAkses(Pengguna $pengguna): array
    {
        return [
            'cakupan_luas' => $this->akses->aksesLuas($pengguna),
            'dapat_proses' => $pengguna->administrator() || $pengguna->memilikiIzin('poin_siswa.pengaturan'),
            'dapat_kelola_pendampingan' => $pengguna->administrator() || $pengguna->memilikiIzin('poin_siswa.pendampingan_kelola'),
        ];
    }
}
