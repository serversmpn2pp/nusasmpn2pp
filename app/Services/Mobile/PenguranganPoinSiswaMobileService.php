<?php

namespace App\Services\Mobile;

use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\PenguranganPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\ProsesPoinSiswaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PenguranganPoinSiswaMobileService
{
    public const DAFTAR_KEGIATAN = [
        'Juara lomba tingkat sekolah',
        'Juara tingkat kota/kabupaten',
        'Juara tingkat provinsi',
        'Aktif organisasi',
        "Hafalan Al-Qur'an/kegiatan keagamaan",
        'Teladan disiplin',
    ];

    public const DAFTAR_POIN = [10, 15, 20, 30];

    public function __construct(
        private ProsesPoinSiswaService $prosesPoin,
        private NotifikasiPenggunaService $notifikasi,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tahunAktif = TahunPelajaran::query()
            ->where('aktif', true)->latest('tanggal_mulai')->first();
        $tahunId = isset($filter['tahun_pelajaran_id'])
            ? (int) $filter['tahun_pelajaran_id']
            : $tahunAktif?->id;
        $kelasId = isset($filter['kelas_id']) ? (int) $filter['kelas_id'] : null;
        $status = (string) ($filter['status'] ?? 'semua');
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));

        $dasar = PenguranganPoinSiswa::query()
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->when(! $tahunId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($kelasId, fn (Builder $query) => $query->whereHas('siswa.anggotaKelas', fn (Builder $query) => $query
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')
                ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereRaw('LOWER(jenis_kegiatan) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(deskripsi, '')) LIKE ?", [$pola])
                        ->orWhereHas('siswa', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                            ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$pola])
                            ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]));
                });
            });

        $ringkasan = [
            'semua' => (clone $dasar)->count(),
            'diajukan' => (clone $dasar)->where('status', 'diajukan')->count(),
            'disetujui' => (clone $dasar)->where('status', 'disetujui')->count(),
            'ditolak' => (clone $dasar)->where('status', 'ditolak')->count(),
            'poin_disetujui' => (int) (clone $dasar)->where('status', 'disetujui')->sum('poin_pengurangan'),
        ];

        $query = (clone $dasar)
            ->with($this->relasi($tahunId))
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'diajukan' THEN 0 WHEN 'disetujui' THEN 1 ELSE 2 END")
            ->latest('tanggal_kegiatan')
            ->latest('id');
        $paginasi = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'items' => collect($paginasi->items())
                ->map(fn (PenguranganPoinSiswa $item) => $this->ringkas($item, $pengguna))
                ->values(),
            'ringkasan' => $ringkasan,
            'pilihan' => [
                'status' => collect(['semua' => 'Semua Status'] + PenguranganPoinSiswa::DAFTAR_STATUS)
                    ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])
                    ->values(),
                'tahun_pelajaran' => TahunPelajaran::query()
                    ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get()
                    ->map(fn (TahunPelajaran $tahun) => [
                        'id' => (int) $tahun->id,
                        'nama' => $tahun->nama,
                        'aktif' => (bool) $tahun->aktif,
                    ])->values(),
                'kelas' => $this->daftarKelas($tahunId),
                'siswa' => $this->bolehMengajukan($pengguna) && $tahunAktif
                    ? $this->daftarSiswaBersaldo((int) $tahunAktif->id)
                    : [],
                'kegiatan' => self::DAFTAR_KEGIATAN,
                'poin' => self::DAFTAR_POIN,
            ],
            'filter' => [
                'kata_kunci' => $kataKunci,
                'status' => $status,
                'tahun_pelajaran_id' => $tahunId,
                'kelas_id' => $kelasId,
            ],
            'tahun_pelajaran_aktif' => $tahunAktif ? [
                'id' => (int) $tahunAktif->id,
                'nama' => $tahunAktif->nama,
            ] : null,
            'paginasi' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total' => $paginasi->total(),
                'ada_halaman_berikutnya' => $paginasi->hasMorePages(),
            ],
            'hak_akses' => [
                'dapat_mengajukan' => $this->bolehMengajukan($pengguna),
                'dapat_memutuskan' => $this->bolehMemutuskan($pengguna),
            ],
        ];
    }

    public function buat(Pengguna $pengguna, array $data, ?UploadedFile $bukti): PenguranganPoinSiswa
    {
        abort_unless($this->bolehMengajukan($pengguna), 403);
        $tahunAktif = TahunPelajaran::query()
            ->where('aktif', true)->latest('tanggal_mulai')->first();
        if (! $tahunAktif) {
            throw ValidationException::withMessages([
                'siswa_id' => 'Pengajuan penghargaan belum dapat dibuat karena tidak ada tahun pelajaran aktif.',
            ]);
        }

        $saldo = $this->prosesPoin->totalPoin((int) $data['siswa_id'], (int) $tahunAktif->id);
        if ($saldo <= 0) {
            throw ValidationException::withMessages([
                'siswa_id' => 'Penghargaan hanya dapat diajukan untuk siswa yang masih memiliki saldo poin.',
            ]);
        }

        $lokasiBukti = $bukti?->store('bukti-pengurangan-poin', 'public');
        $pengurangan = PenguranganPoinSiswa::create([
            'siswa_id' => (int) $data['siswa_id'],
            'tahun_pelajaran_id' => $tahunAktif->id,
            'tanggal_kegiatan' => $data['tanggal_kegiatan'],
            'jenis_kegiatan' => trim($data['jenis_kegiatan']),
            'deskripsi' => filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null,
            'poin_pengurangan' => (int) $data['poin_pengurangan'],
            'bukti' => $lokasiBukti,
            'status' => 'diajukan',
            'diajukan_oleh_pengguna_id' => $pengguna->id,
        ]);
        $pengurangan->loadMissing('siswa:id,nama_lengkap');

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaDenganPeran('wakil_pimpinan_kesiswaan', $pengguna->id),
            'peringatan',
            'Pengajuan penghargaan menunggu persetujuan',
            sprintf(
                'Pengurangan %d poin untuk %s diajukan melalui kegiatan %s.',
                $pengurangan->poin_pengurangan,
                $pengurangan->siswa?->nama_lengkap ?? 'siswa',
                $pengurangan->jenis_kegiatan,
            ),
            route('pengurangan-poin-siswa.index', ['status' => 'diajukan'], false),
            "pengurangan-poin-diajukan:{$pengurangan->id}",
            [
                'pengurangan_poin_siswa_id' => $pengurangan->id,
                'siswa_id' => $pengurangan->siswa_id,
                'poin_pengurangan' => $pengurangan->poin_pengurangan,
            ],
        );

        return $pengurangan->refresh()->load($this->relasi((int) $tahunAktif->id));
    }

    public function putuskan(
        Pengguna $pengguna,
        PenguranganPoinSiswa $pengurangan,
        string $keputusan,
        ?string $catatan,
    ): array {
        abort_unless($this->bolehMemutuskan($pengguna), 403);
        abort_if($pengurangan->status !== 'diajukan', 422, 'Pengajuan ini sudah diputuskan.');

        $diterapkan = 0;
        if ($keputusan === 'disetujui') {
            $diterapkan = $this->prosesPoin->setujuiPengurangan(
                $pengurangan,
                $pengguna->pegawai_id,
                $catatan,
            );
            $pengurangan->refresh()->loadMissing('siswa:id,nama_lengkap');
            $this->notifikasi->kirimKeBanyak(
                $this->notifikasi->penggunaOrangTuaUntukSiswa((int) $pengurangan->siswa_id),
                'berhasil',
                'Pengurangan poin anak disetujui',
                sprintf(
                    '%d poin untuk %s dikurangi melalui kegiatan %s.',
                    $diterapkan,
                    $pengurangan->siswa?->nama_lengkap ?? 'anak Anda',
                    $pengurangan->jenis_kegiatan,
                ),
                route('pembinaan-poin-anak.index', ['tab' => 'poin'], false),
                "pengurangan-poin-orang-tua:{$pengurangan->id}:disetujui",
                [
                    'pengurangan_poin_siswa_id' => $pengurangan->id,
                    'siswa_id' => $pengurangan->siswa_id,
                    'poin_pengurangan' => $diterapkan,
                ],
            );
        } else {
            $pengurangan->update([
                'status' => 'ditolak',
                'disetujui_oleh_pegawai_id' => $pengguna->pegawai_id,
                'diputuskan_pada' => now(),
                'catatan_keputusan' => filled($catatan) ? trim($catatan) : null,
            ]);
        }

        return [
            'diterapkan' => $diterapkan,
            'pengurangan' => $pengurangan->refresh()->load($this->relasi((int) $pengurangan->tahun_pelajaran_id)),
        ];
    }

    public function ringkas(PenguranganPoinSiswa $item, Pengguna $pengguna): array
    {
        $kelas = $item->siswa?->anggotaKelas?->first()?->kelas;
        $bukti = null;
        if ($item->bukti && Storage::disk('public')->exists($item->bukti)) {
            $tipeFile = Storage::disk('public')->mimeType($item->bukti);
            $bukti = [
                'nama_file' => 'Bukti penghargaan.'.pathinfo($item->bukti, PATHINFO_EXTENSION),
                'tipe_file' => is_string($tipeFile) ? $tipeFile : null,
                'ukuran_file' => Storage::disk('public')->size($item->bukti),
            ];
        }

        return [
            'id' => (int) $item->id,
            'siswa' => $item->siswa ? [
                'id' => (int) $item->siswa->id,
                'nama' => $item->siswa->nama_lengkap,
                'nis' => $item->siswa->nis,
                'nisn' => $item->siswa->nisn,
            ] : null,
            'kelas' => $kelas ? ['id' => (int) $kelas->id, 'nama' => $kelas->nama] : null,
            'tahun_pelajaran' => $item->tahunPelajaran ? [
                'id' => (int) $item->tahunPelajaran->id,
                'nama' => $item->tahunPelajaran->nama,
            ] : null,
            'tanggal_kegiatan' => $item->tanggal_kegiatan?->toDateString(),
            'jenis_kegiatan' => $item->jenis_kegiatan,
            'deskripsi' => $item->deskripsi,
            'poin_pengurangan' => (int) $item->poin_pengurangan,
            'status' => $item->status,
            'label_status' => PenguranganPoinSiswa::DAFTAR_STATUS[$item->status] ?? $item->status,
            'bukti' => $bukti,
            'diajukan_oleh' => $item->diajukanOlehPengguna?->nama,
            'disetujui_oleh' => $item->disetujuiOlehPegawai?->nama_lengkap,
            'diputuskan_pada' => $item->diputuskan_pada?->toISOString(),
            'catatan_keputusan' => $item->catatan_keputusan,
            'dapat_diputuskan' => $item->status === 'diajukan' && $this->bolehMemutuskan($pengguna),
        ];
    }

    public function bolehMelihat(Pengguna $pengguna): bool
    {
        return $this->bolehMengajukan($pengguna) || $this->bolehMemutuskan($pengguna);
    }

    private function bolehMengajukan(Pengguna $pengguna): bool
    {
        return $pengguna->administrator() || $pengguna->memilikiIzin('poin_siswa.reward_kelola');
    }

    private function bolehMemutuskan(Pengguna $pengguna): bool
    {
        return $pengguna->administrator() || $pengguna->memilikiIzin('poin_siswa.putus_konflik');
    }

    private function relasi(?int $tahunId): array
    {
        return [
            'siswa' => fn ($query) => $query->with(['anggotaKelas' => fn ($query) => $query
                ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                ->where('status_keanggotaan', 'aktif')
                ->with('kelas:id,nama')]),
            'tahunPelajaran:id,nama,aktif',
            'diajukanOlehPengguna:id,nama',
            'disetujuiOlehPegawai:id,nama_lengkap',
        ];
    }

    private function daftarKelas(?int $tahunId): array
    {
        return Kelas::query()
            ->when($tahunId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunId))
            ->when(! $tahunId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where('aktif', true)
            ->orderBy('tingkat')->orderBy('nama')->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat'])
            ->map(fn (Kelas $kelas) => [
                'id' => (int) $kelas->id,
                'tahun_pelajaran_id' => (int) $kelas->tahun_pelajaran_id,
                'nama' => $kelas->nama,
                'tingkat' => (int) $kelas->tingkat,
            ])->values()->all();
    }

    private function daftarSiswaBersaldo(int $tahunId): array
    {
        $saldo = TransaksiPoinSiswa::query()
            ->select('siswa_id')
            ->selectRaw('SUM(poin) AS saldo_poin')
            ->where('tahun_pelajaran_id', $tahunId)
            ->groupBy('siswa_id')
            ->havingRaw('SUM(poin) > 0');

        return Siswa::query()
            ->joinSub($saldo, 'saldo_poin_aktif', fn ($join) => $join->on('saldo_poin_aktif.siswa_id', '=', 'siswa.id'))
            ->select(['siswa.id', 'siswa.nama_lengkap', 'siswa.nis', 'siswa.nisn', 'saldo_poin_aktif.saldo_poin'])
            ->where('siswa.aktif', true)
            ->with(['anggotaKelas' => fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunId)
                ->where('status_keanggotaan', 'aktif')
                ->with('kelas:id,nama')])
            ->orderBy('siswa.nama_lengkap')->get()
            ->map(function (Siswa $siswa): array {
                $kelas = $siswa->anggotaKelas->first()?->kelas;

                return [
                    'id' => (int) $siswa->id,
                    'nama' => $siswa->nama_lengkap,
                    'nis' => $siswa->nis,
                    'nisn' => $siswa->nisn,
                    'saldo_poin' => (int) $siswa->saldo_poin,
                    'kelas' => $kelas ? ['id' => (int) $kelas->id, 'nama' => $kelas->nama] : null,
                ];
            })->values()->all();
    }
}
