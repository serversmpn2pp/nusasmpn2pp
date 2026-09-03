<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\CatatRiwayatPembinaanService;
use App\Services\Pembinaan\PengaturanBatasProsesPelanggaranService;
use App\Services\Pembinaan\PenugasanGuruBkTingkatService;
use App\Services\Pembinaan\SimpanBuktiLaporanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaporkanKejadianMobileService
{
    public function __construct(
        private CatatRiwayatPembinaanService $riwayat,
        private SimpanBuktiLaporanService $bukti,
        private PengaturanBatasProsesPelanggaranService $batasProses,
        private NotifikasiPenggunaService $notifikasi,
        private PenugasanGuruBkTingkatService $penugasanBk,
    ) {}

    public function referensi(): array
    {
        $tahunAktifId = TahunPelajaran::query()
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->value('id');

        return [
            'nilai_awal' => [
                'tanggal_kejadian' => now()->toDateString(),
                'tahun_pelajaran_id' => $tahunAktifId ? (int) $tahunAktifId : null,
            ],
            'batas' => [
                'maksimal_siswa' => 100,
                'maksimal_saksi' => 10,
                'maksimal_bukti' => 5,
                'maksimal_bukti_mb' => 10,
            ],
            'tahun_pelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('tanggal_mulai')
                ->get()
                ->map(fn (TahunPelajaran $tahun) => [
                    'id' => (int) $tahun->id,
                    'nama' => $tahun->nama,
                    'aktif' => (bool) $tahun->aktif,
                ])
                ->values(),
            'kelas' => Kelas::query()
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat'])
                ->map(fn (Kelas $kelas) => [
                    'id' => (int) $kelas->id,
                    'tahun_pelajaran_id' => (int) $kelas->tahun_pelajaran_id,
                    'nama' => $kelas->nama,
                    'tingkat' => (int) $kelas->tingkat,
                ])
                ->values(),
            'siswa' => Siswa::query()
                ->where('aktif', true)
                ->with(['anggotaKelas' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->with('kelas:id,nama')])
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nis', 'nisn'])
                ->map(fn (Siswa $siswa) => [
                    'id' => (int) $siswa->id,
                    'nama' => $siswa->nama_lengkap,
                    'nis' => $siswa->nis,
                    'nisn' => $siswa->nisn,
                    'penempatan' => $siswa->anggotaKelas
                        ->map(fn (AnggotaKelas $anggota) => [
                            'tahun_pelajaran_id' => (int) $anggota->tahun_pelajaran_id,
                            'kelas_id' => (int) $anggota->kelas_id,
                            'kelas' => $anggota->kelas?->nama,
                        ])
                        ->values(),
                ])
                ->values(),
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $daftarBukti
     */
    public function simpan(array $data, array $daftarBukti, Pengguna $pengguna): array
    {
        $siswaIds = collect($data['siswa_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $daftarSaksi = $this->rapikanSaksi($data['daftar_saksi'] ?? []);
        $keteranganBukti = filled($data['keterangan_bukti'] ?? null)
            ? trim((string) $data['keterangan_bukti'])
            : null;

        $laporan = DB::transaction(function () use (
            $data,
            $daftarBukti,
            $pengguna,
            $siswaIds,
            $daftarSaksi,
            $keteranganBukti,
        ) {
            $daftarLaporan = collect();
            foreach ($siswaIds as $siswaId) {
                $konteks = $this->konteksSiswa(
                    $siswaId,
                    $data['tahun_pelajaran_id'] ?? null,
                    $data['kelas_id'] ?? null,
                    $data['tanggal_kejadian'],
                );

                $item = LaporanPembinaanSiswa::create([
                    'nomor_laporan' => $this->buatNomorLaporan($data['tanggal_kejadian']),
                    'jenis_laporan' => 'kejadian',
                    'sumber_laporan' => 'manual',
                    'tanggal_kejadian' => $data['tanggal_kejadian'],
                    'waktu_kejadian' => filled($data['waktu_kejadian'] ?? null) ? $data['waktu_kejadian'] : null,
                    'tempat_kejadian' => filled($data['tempat_kejadian'] ?? null) ? trim((string) $data['tempat_kejadian']) : null,
                    'siswa_id' => $siswaId,
                    'tahun_pelajaran_id' => $konteks['tahun_pelajaran_id'],
                    'kelas_id' => $konteks['kelas_id'],
                    'anggota_kelas_id' => $konteks['anggota_kelas_id'],
                    'pelapor_pegawai_id' => $pengguna->pegawai_id,
                    'wali_kelas_pegawai_id' => $konteks['wali_kelas_pegawai_id'],
                    'guru_wali_pegawai_id' => $konteks['guru_wali_pegawai_id'],
                    'tingkat' => 'ringan',
                    'status' => 'baru',
                    'status_verifikasi' => 'diajukan',
                    'total_poin' => 0,
                    'kronologi' => trim((string) $data['kronologi']),
                    'tindakan_awal' => filled($data['tindakan_awal'] ?? null) ? trim((string) $data['tindakan_awal']) : null,
                    'dibuat_oleh_pengguna_id' => $pengguna->id,
                ]);

                $this->batasProses->tetapkanBatas($item);
                $this->riwayat->catat(
                    $item,
                    'laporan_dibuat',
                    'Laporan dibuat',
                    $siswaIds->count() > 1
                        ? 'Laporan dibuat bersama laporan siswa lain dalam satu kejadian kolektif.'
                        : 'Laporan awal dicatat dan siap ditindaklanjuti.',
                    null,
                    'diajukan',
                    $pengguna->id,
                    ['jumlah_siswa_dilaporkan' => $siswaIds->count()],
                );
                $this->simpanSaksi($item, $daftarSaksi, $pengguna->id);
                $daftarLaporan->push($item);
            }

            $this->bukti->simpanBanyakUntukLaporan(
                $daftarLaporan,
                $daftarBukti,
                $keteranganBukti,
                $pengguna->id,
            );

            return $daftarLaporan;
        });

        $this->kirimNotifikasi($laporan, $pengguna);

        return [
            'jumlah_laporan' => $laporan->count(),
            'laporan' => $laporan->map(fn (LaporanPembinaanSiswa $item) => [
                'id' => (int) $item->id,
                'nomor_laporan' => $item->nomor_laporan,
                'siswa_id' => (int) $item->siswa_id,
                'status_verifikasi' => $item->status_verifikasi,
                'batas_proses_pada' => $item->batas_proses_pada?->toIso8601String(),
            ])->values(),
        ];
    }

    private function konteksSiswa(int $siswaId, mixed $tahunId, mixed $kelasId, string $tanggal): array
    {
        $tahunId = is_numeric($tahunId) ? (int) $tahunId : null;
        $kelasId = is_numeric($kelasId) ? (int) $kelasId : null;
        if (! $tahunId && $kelasId) {
            $tahunId = Kelas::query()->whereKey($kelasId)->value('tahun_pelajaran_id');
        }
        if (! $tahunId) {
            $tahunId = TahunPelajaran::query()->where('aktif', true)->latest('tanggal_mulai')->value('id');
        }

        $queryAnggota = AnggotaKelas::query()
            ->where('siswa_id', $siswaId)
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId));
        $anggota = (clone $queryAnggota)
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->first()
            ?? $queryAnggota->first()
            ?? AnggotaKelas::query()
                ->where('siswa_id', $siswaId)
                ->where('status_keanggotaan', 'aktif')
                ->latest('tahun_pelajaran_id')
                ->first();

        $kelasId = $anggota?->kelas_id;
        $tahunId = $anggota?->tahun_pelajaran_id ?? $tahunId;

        return [
            'tahun_pelajaran_id' => $tahunId,
            'kelas_id' => $kelasId,
            'anggota_kelas_id' => $anggota?->id,
            'wali_kelas_pegawai_id' => $kelasId ? Kelas::query()->whereKey($kelasId)->value('wali_kelas_id') : null,
            'guru_wali_pegawai_id' => PenugasanGuruWaliSiswa::query()
                ->where('siswa_id', $siswaId)
                ->where('tanggal_mulai', '<=', $tanggal)
                ->where(fn ($query) => $query
                    ->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $tanggal))
                ->latest('tanggal_mulai')
                ->value('guru_wali_pegawai_id'),
        ];
    }

    private function buatNomorLaporan(string $tanggal): string
    {
        $prefix = 'PB-'.CarbonImmutable::parse($tanggal)->format('Ymd');
        $urutan = LaporanPembinaanSiswa::query()->where('nomor_laporan', 'like', $prefix.'-%')->count() + 1;
        do {
            $nomor = sprintf('%s-%04d', $prefix, $urutan++);
        } while (LaporanPembinaanSiswa::query()->where('nomor_laporan', $nomor)->exists());

        return $nomor;
    }

    private function rapikanSaksi(array $daftarSaksi): array
    {
        $hasil = [];
        foreach ($daftarSaksi as $index => $saksi) {
            $nama = trim((string) ($saksi['nama_saksi'] ?? ''));
            $pernyataan = trim((string) ($saksi['pernyataan'] ?? ''));
            if ($nama === '' && $pernyataan === '') {
                continue;
            }
            if ($nama === '' || $pernyataan === '') {
                throw ValidationException::withMessages([
                    "daftar_saksi.{$index}.nama_saksi" => 'Nama dan pernyataan saksi harus diisi lengkap.',
                ]);
            }
            $hasil[] = [
                'jenis_saksi' => $saksi['jenis_saksi'] ?? 'lainnya',
                'nama_saksi' => $nama,
                'pernyataan' => $pernyataan,
            ];
        }

        return $hasil;
    }

    private function simpanSaksi(LaporanPembinaanSiswa $laporan, array $saksi, int $penggunaId): void
    {
        foreach ($saksi as $item) {
            $laporan->saksiLaporanPembinaanSiswa()->create($item + [
                'dibuat_oleh_pengguna_id' => $penggunaId,
            ]);
        }
        if ($saksi !== []) {
            $this->riwayat->catat(
                $laporan,
                'saksi_ditambahkan',
                'Pernyataan saksi ditambahkan',
                count($saksi).' saksi awal dicatat bersama laporan.',
                'diajukan',
                'diajukan',
                $penggunaId,
                ['jumlah_saksi' => count($saksi)],
            );
        }
    }

    private function kirimNotifikasi(Collection $laporan, Pengguna $pengguna): void
    {
        $pertama = $laporan->first();
        if (! $pertama) {
            return;
        }

        $laporan
            ->each(fn (LaporanPembinaanSiswa $item) => $item->loadMissing('kelas:id,nama,tingkat'))
            ->groupBy(fn (LaporanPembinaanSiswa $item) => $this->penugasanBk->tingkatLaporan($item) ?? 'tanpa-tingkat')
            ->each(function (Collection $laporanTingkat, int|string $tingkat) use ($laporan, $pengguna): void {
                $acuan = $laporanTingkat->first();
                $kolektif = $laporan->count() > 1;
                $this->notifikasi->kirimKeBanyak(
                    $this->penugasanBk->penerimaNotifikasi($acuan, $pengguna->id),
                    'peringatan',
                    $kolektif
                        ? 'Laporan kolektif menunggu pemeriksaan BK'
                        : 'Laporan kejadian menunggu pemeriksaan BK',
                    $kolektif
                        ? $laporanTingkat->count().' siswa'.(is_numeric($tingkat) ? ' tingkat '.$tingkat : '').' dilaporkan dalam kejadian yang sama.'
                        : ($acuan->siswa()->value('nama_lengkap') ?? 'Siswa').' memiliki laporan kejadian baru.',
                    $kolektif
                        ? route('laporan-pembinaan-siswa.index', [], false)
                        : route('laporan-pembinaan-siswa.show', $acuan, false),
                    $kolektif
                        ? "laporan-kolektif-baru:{$laporan->first()->id}:{$laporan->last()->id}"
                        : "laporan-pembinaan-baru:{$acuan->id}",
                    ['jumlah_siswa' => $laporanTingkat->count(), 'tingkat' => is_numeric($tingkat) ? (int) $tingkat : null],
                );
            });

        foreach ($laporan as $item) {
            if (! $item->wali_kelas_pegawai_id || (int) $item->wali_kelas_pegawai_id === (int) $pengguna->pegawai_id) {
                continue;
            }
            $item->loadMissing(['siswa:id,nama_lengkap', 'kelas:id,nama']);
            $this->notifikasi->kirimKeBanyak(
                $this->notifikasi->penggunaUntukPegawai((int) $item->wali_kelas_pegawai_id)
                    ->filter(fn (Pengguna $penerima): bool => (int) $penerima->id !== (int) $pengguna->id)
                    ->values(),
                'peringatan',
                'Siswa kelas Anda dilaporkan',
                sprintf(
                    '%s dari %s dilaporkan dan menunggu pemeriksaan BK.',
                    $item->siswa?->nama_lengkap ?? 'Siswa',
                    $item->kelas?->nama ?? 'kelas Anda',
                ),
                route('laporan-pembinaan-siswa.show', $item, false),
                "laporan-pembinaan-wali-kelas:{$item->id}",
                ['laporan_pembinaan_siswa_id' => $item->id],
            );
        }
    }
}
