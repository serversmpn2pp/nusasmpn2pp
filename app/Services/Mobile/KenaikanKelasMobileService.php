<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\TahunPelajaran;
use App\Services\Kelas\KenaikanKelasService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class KenaikanKelasMobileService
{
    public function __construct(
        private readonly KenaikanKelasService $prosesKenaikan,
    ) {}

    public function daftar(array $filter): array
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->withCount('kelas')
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();
        $tahunAsalId = isset($filter['tahun_asal_id'])
            ? (int) $filter['tahun_asal_id']
            : (int) ($tahunPelajaran->firstWhere('aktif', true)?->id ?? $tahunPelajaran->first()?->id ?? 0);
        $tahunTujuanId = isset($filter['tahun_tujuan_id'])
            ? (int) $filter['tahun_tujuan_id']
            : null;
        $kelasAsalId = isset($filter['kelas_asal_id'])
            ? (int) $filter['kelas_asal_id']
            : null;
        $tahunAsal = $tahunPelajaran->firstWhere('id', $tahunAsalId);
        $tahunTujuan = $tahunTujuanId
            ? $tahunPelajaran->firstWhere('id', $tahunTujuanId)
            : null;

        if ($tahunAsal && $tahunTujuan && $tahunAsal->is($tahunTujuan)) {
            throw ValidationException::withMessages([
                'tahun_tujuan_id' => 'Tahun pelajaran tujuan harus berbeda dari tahun asal.',
            ]);
        }

        $kelasAsal = $tahunAsal
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunAsal->id)
                ->withCount('anggotaKelas')
                ->orderByDesc('aktif')
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $kelasAsalDipilih = $kelasAsalId
            ? $kelasAsal->firstWhere('id', $kelasAsalId)
            : null;

        if ($kelasAsalId && ! $kelasAsalDipilih) {
            throw ValidationException::withMessages([
                'kelas_asal_id' => 'Kelas asal tidak berada pada tahun pelajaran yang dipilih.',
            ]);
        }

        $kelasTujuan = $tahunTujuan
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunTujuan->id)
                ->where('aktif', true)
                ->withCount('anggotaKelas')
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $anggota = collect();
        $saranKelasId = null;

        if ($kelasAsalDipilih && $tahunTujuan) {
            $anggota = $kelasAsalDipilih->anggotaKelas()
                ->with('siswa:id,nama_lengkap,nis,nisn,foto,jenis_kelamin,aktif')
                ->orderByRaw('nomor_absen IS NULL')
                ->orderBy('nomor_absen')
                ->orderBy('id')
                ->get();
            $anggotaTujuan = AnggotaKelas::query()
                ->where('tahun_pelajaran_id', $tahunTujuan->id)
                ->whereIn('siswa_id', $anggota->pluck('siswa_id'))
                ->with('kelas:id,nama,tingkat,kapasitas,aktif')
                ->get()
                ->keyBy('siswa_id');
            $saranKelasId = $this->ambilSaranKelasTujuan($kelasAsalDipilih, $kelasTujuan)?->id;
            $anggota = $anggota
                ->filter(fn (AnggotaKelas $item) => $item->siswa !== null)
                ->map(function (AnggotaKelas $item) use ($anggotaTujuan, $saranKelasId) {
                    $tujuanSaatIni = $anggotaTujuan->get($item->siswa_id);

                    return [
                        'id' => (int) $item->id,
                        'nomor_absen' => $item->nomor_absen,
                        'siswa' => [
                            'id' => (int) $item->siswa->id,
                            'nama' => $item->siswa->nama_lengkap,
                            'nis' => $item->siswa->nis,
                            'nisn' => $item->siswa->nisn,
                            'jenis_kelamin' => $item->siswa->jenis_kelamin,
                            'foto_url' => $item->siswa->foto ? asset('storage/'.$item->siswa->foto) : null,
                            'aktif' => (bool) $item->siswa->aktif,
                        ],
                        'penempatan_tujuan' => $tujuanSaatIni ? [
                            'anggota_kelas_id' => (int) $tujuanSaatIni->id,
                            'kelas' => $this->siapkanKelas($tujuanSaatIni->kelas),
                        ] : null,
                        'kelas_tujuan_disarankan_id' => $tujuanSaatIni?->kelas_id
                            ? (int) $tujuanSaatIni->kelas_id
                            : ($saranKelasId ? (int) $saranKelasId : null),
                        'keterangan_awal' => $tujuanSaatIni?->keterangan ?: 'Penempatan massal',
                    ];
                })
                ->values();
        }

        $peringatan = $this->peringatan(
            $tahunPelajaran,
            $tahunAsal,
            $tahunTujuan,
            $kelasAsalDipilih,
            $kelasTujuan,
        );

        return [
            'tahun_pelajaran' => $tahunPelajaran
                ->map(fn (TahunPelajaran $tahun) => [
                    'id' => (int) $tahun->id,
                    'nama' => $tahun->nama,
                    'aktif' => (bool) $tahun->aktif,
                    'tanggal_mulai' => $tahun->tanggal_mulai?->toDateString(),
                    'tanggal_selesai' => $tahun->tanggal_selesai?->toDateString(),
                    'jumlah_kelas' => (int) $tahun->kelas_count,
                ])
                ->values(),
            'kelas_asal' => $kelasAsal->map(fn (Kelas $kelas) => $this->siapkanKelas($kelas))->values(),
            'kelas_tujuan' => $kelasTujuan->map(fn (Kelas $kelas) => $this->siapkanKelas($kelas))->values(),
            'kelas_asal_dipilih' => $kelasAsalDipilih ? $this->siapkanKelas($kelasAsalDipilih) : null,
            'anggota' => $anggota,
            'ringkasan' => [
                'jumlah_siswa_asal' => $anggota->count(),
                'sudah_ditempatkan' => $anggota->whereNotNull('penempatan_tujuan')->count(),
                'belum_ditempatkan' => $anggota->whereNull('penempatan_tujuan')->count(),
                'jumlah_kelas_tujuan' => $kelasTujuan->count(),
            ],
            'filter' => [
                'tahun_asal_id' => $tahunAsal?->id ? (int) $tahunAsal->id : null,
                'tahun_tujuan_id' => $tahunTujuan?->id ? (int) $tahunTujuan->id : null,
                'kelas_asal_id' => $kelasAsalDipilih?->id ? (int) $kelasAsalDipilih->id : null,
            ],
            'saran_kelas_tujuan_id' => $saranKelasId ? (int) $saranKelasId : null,
            'siap_diproses' => $kelasAsalDipilih !== null
                && $tahunTujuan !== null
                && $kelasTujuan->isNotEmpty()
                && $anggota->isNotEmpty(),
            'peringatan' => $peringatan,
        ];
    }

    public function proses(
        TahunPelajaran $tahunAsal,
        TahunPelajaran $tahunTujuan,
        Kelas $kelasAsal,
        array $penempatan,
    ): array {
        return $this->prosesKenaikan->proses(
            $tahunAsal,
            $tahunTujuan,
            $kelasAsal,
            $penempatan,
        );
    }

    private function siapkanKelas(?Kelas $kelas): ?array
    {
        if (! $kelas) {
            return null;
        }

        $jumlahAnggota = (int) ($kelas->anggota_kelas_count ?? $kelas->anggotaKelas()->count());
        $kapasitas = $kelas->kapasitas ? (int) $kelas->kapasitas : null;

        return [
            'id' => (int) $kelas->id,
            'nama' => $kelas->nama,
            'tingkat' => (int) $kelas->tingkat,
            'kapasitas' => $kapasitas,
            'jumlah_siswa' => $jumlahAnggota,
            'sisa_kapasitas' => $kapasitas === null ? null : max(0, $kapasitas - $jumlahAnggota),
            'aktif' => (bool) $kelas->aktif,
        ];
    }

    private function ambilSaranKelasTujuan(Kelas $kelasAsal, Collection $kelasTujuan): ?Kelas
    {
        $tingkatTujuan = $kelasAsal->tingkat && $kelasAsal->tingkat < 9
            ? $kelasAsal->tingkat + 1
            : $kelasAsal->tingkat;
        $rombel = $this->ambilRombel($kelasAsal->nama);

        return $kelasTujuan->first(function (Kelas $kelas) use ($tingkatTujuan, $rombel) {
            return (int) $kelas->tingkat === (int) $tingkatTujuan
                && $this->ambilRombel($kelas->nama) === $rombel;
        }) ?? $kelasTujuan->firstWhere('tingkat', $tingkatTujuan);
    }

    private function ambilRombel(string $namaKelas): string
    {
        $namaKelas = mb_strtoupper(trim($namaKelas));

        if (preg_match('/([A-Z])$/', $namaKelas, $cocok)) {
            return $cocok[1];
        }

        return '';
    }

    private function peringatan(
        Collection $tahunPelajaran,
        ?TahunPelajaran $tahunAsal,
        ?TahunPelajaran $tahunTujuan,
        ?Kelas $kelasAsal,
        Collection $kelasTujuan,
    ): array {
        return collect([
            $tahunPelajaran->count() < 2
                ? 'Kenaikan kelas membutuhkan minimal dua tahun pelajaran.'
                : null,
            $tahunAsal && ! $tahunTujuan
                ? 'Pilih tahun pelajaran tujuan.'
                : null,
            $tahunTujuan && $kelasTujuan->isEmpty()
                ? 'Tahun tujuan belum memiliki kelas aktif.'
                : null,
            $tahunAsal && ! $kelasAsal
                ? 'Pilih kelas asal untuk menampilkan siswa.'
                : null,
        ])->filter()->values()->all();
    }
}
