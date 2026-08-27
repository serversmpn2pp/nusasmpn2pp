<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\JenisPerangkatAjar;
use App\Models\Pengguna;
use App\Models\PerangkatAjar;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\PerangkatAjar\PenugasanPerangkatAjarService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PerangkatAjarSayaMobileService
{
    public const BATAS_PDF_KILOBYTE = 10240;

    private const BATAS_PDF_BYTE = 10 * 1024 * 1024;

    public function __construct(
        private PenugasanPerangkatAjarService $penugasanService,
        private NotifikasiPenggunaService $notifikasiService,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $pegawai = $pengguna->pegawai;
        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'nama', 'aktif']);
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            $filter['tahun_pelajaran_id'] ?? null,
            $tahunPelajaran,
        );
        $semester = (int) ($filter['semester'] ?? 1);
        $penugasan = $this->penugasanService->untukGuru($pegawai?->id, $tahunPelajaranId);
        $jenis = JenisPerangkatAjar::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
        $dokumen = PerangkatAjar::query()
            ->with(['mataPelajaran:id,nama', 'jenisPerangkatAjar:id,nama,wajib'])
            ->when(
                $pegawai,
                fn ($query) => $query->where('pegawai_id', $pegawai->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->where('semester', $semester)
            ->whereIn('mata_pelajaran_id', $penugasan->pluck('mata_pelajaran_id'))
            ->whereIn('jenis_perangkat_ajar_id', $jenis->pluck('id'))
            ->get();
        $kunciPenugasan = $penugasan->pluck('kunci');
        $dokumenTerpetakan = $dokumen
            ->filter(fn (PerangkatAjar $item) => $item->tingkat
                && $kunciPenugasan->contains(
                    $this->penugasanService->kunci($item->mata_pelajaran_id, $item->tingkat),
                ))
            ->keyBy(fn (PerangkatAjar $item) => $this->kunciDokumen(
                $item->mata_pelajaran_id,
                $item->tingkat,
                $item->jenis_perangkat_ajar_id,
            ));
        $jenisWajibIds = $jenis->where('wajib', true)->pluck('id');
        $jumlahWajib = $penugasan->count() * $jenisWajibIds->count();
        $jumlahTerunggah = $dokumenTerpetakan
            ->whereIn('jenis_perangkat_ajar_id', $jenisWajibIds)
            ->count();

        return [
            'pegawai' => $pegawai ? [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
            ] : null,
            'tahun_pelajaran' => $tahunPelajaran->map(fn (TahunPelajaran $tahun) => [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'aktif' => (bool) $tahun->aktif,
            ])->values(),
            'filter' => [
                'tahun_pelajaran_id' => $tahunPelajaranId,
                'semester' => $semester,
            ],
            'ringkasan' => [
                'wajib' => $jumlahWajib,
                'terunggah' => $jumlahTerunggah,
                'kelengkapan' => $jumlahWajib > 0
                    ? min(100, (int) round($jumlahTerunggah / $jumlahWajib * 100))
                    : 0,
                'menunggu' => $dokumenTerpetakan->where('status', 'menunggu_pemeriksaan')->count(),
                'perlu_perbaikan' => $dokumenTerpetakan->where('status', 'perlu_perbaikan')->count(),
            ],
            'penugasan' => $penugasan->map(function (array $item) use ($jenis, $dokumenTerpetakan): array {
                $mataPelajaran = $item['mata_pelajaran'];

                return [
                    'mata_pelajaran' => [
                        'id' => (int) $mataPelajaran->id,
                        'nama' => $mataPelajaran->nama,
                    ],
                    'tingkat' => (int) $item['tingkat'],
                    'label_tingkat' => $item['label_tingkat'],
                    'dokumen' => $jenis->map(function (JenisPerangkatAjar $jenis) use ($dokumenTerpetakan, $mataPelajaran, $item): array {
                        $dokumen = $dokumenTerpetakan->get($this->kunciDokumen(
                            $mataPelajaran->id,
                            $item['tingkat'],
                            $jenis->id,
                        ));

                        return [
                            'jenis' => $this->ringkasJenis($jenis),
                            'perangkat_ajar' => $dokumen ? $this->ringkasDokumen($dokumen) : null,
                        ];
                    })->values(),
                ];
            })->values(),
            'dokumen_tanpa_tingkat' => $dokumen
                ->whereNull('tingkat')
                ->map(fn (PerangkatAjar $item) => $this->ringkasDokumen($item))
                ->values(),
            'jenis_perangkat' => $jenis->map(fn (JenisPerangkatAjar $item) => $this->ringkasJenis($item))->values(),
            'batas_unggah' => $this->informasiBatasUnggahPdf(),
        ];
    }

    public function rincian(Pengguna $pengguna, PerangkatAjar $perangkatAjar): array
    {
        $this->pastikanPemilik($pengguna, $perangkatAjar);
        $perangkatAjar->load([
            'tahunPelajaran:id,nama,aktif',
            'mataPelajaran:id,nama',
            'jenisPerangkatAjar:id,nama,wajib,deskripsi',
            'pemeriksa:id,nama_lengkap',
            'riwayatFile.pengunggah:id,nama',
        ]);
        $tingkat = $this->penugasanService
            ->untukGuru($perangkatAjar->pegawai_id, $perangkatAjar->tahun_pelajaran_id)
            ->where('mata_pelajaran_id', $perangkatAjar->mata_pelajaran_id)
            ->pluck('tingkat')
            ->when(
                $perangkatAjar->tingkat,
                fn ($items) => $items->push((int) $perangkatAjar->tingkat),
            )
            ->unique()
            ->sort()
            ->values();

        return [
            'perangkat_ajar' => $this->ringkasDokumen($perangkatAjar) + [
                'catatan_guru' => $perangkatAjar->catatan_guru,
                'catatan_pemeriksa' => $perangkatAjar->catatan_pemeriksa,
                'pemeriksa' => $perangkatAjar->pemeriksa?->nama_lengkap,
                'diperiksa_pada' => $perangkatAjar->diperiksa_pada?->toISOString(),
                'tahun_pelajaran' => [
                    'id' => (int) $perangkatAjar->tahunPelajaran->id,
                    'nama' => $perangkatAjar->tahunPelajaran->nama,
                ],
                'mata_pelajaran' => [
                    'id' => (int) $perangkatAjar->mataPelajaran->id,
                    'nama' => $perangkatAjar->mataPelajaran->nama,
                ],
                'jenis' => $this->ringkasJenis($perangkatAjar->jenisPerangkatAjar),
            ],
            'tingkat_tersedia' => $tingkat,
            'riwayat' => $perangkatAjar->riwayatFile->map(fn ($item) => [
                'id' => (int) $item->id,
                'nama_file' => $item->nama_file_asli,
                'ukuran_file' => (int) $item->ukuran_file,
                'catatan' => $item->catatan,
                'diunggah_pada' => $item->diunggah_pada?->toISOString(),
                'pengunggah' => $item->pengunggah?->nama,
            ])->values(),
            'batas_unggah' => $this->informasiBatasUnggahPdf(),
        ];
    }

    public function tambah(
        Pengguna $pengguna,
        array $data,
        UploadedFile $file,
    ): PerangkatAjar {
        $pegawai = $this->pegawaiAktif($pengguna);
        $this->pastikanPenugasanGuru(
            $pegawai->id,
            (int) $data['tahun_pelajaran_id'],
            (int) $data['mata_pelajaran_id'],
            (int) $data['tingkat'],
        );
        $this->pastikanJenisAktif((int) $data['jenis_perangkat_ajar_id']);

        $sudahAda = PerangkatAjar::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('semester', $data['semester'])
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('tingkat', $data['tingkat'])
            ->where('jenis_perangkat_ajar_id', $data['jenis_perangkat_ajar_id'])
            ->exists();
        if ($sudahAda) {
            throw ValidationException::withMessages([
                'jenis_perangkat_ajar_id' => 'Dokumen ini sudah pernah diunggah. Gunakan fitur revisi pada dokumen yang sudah ada.',
            ]);
        }

        $lokasi = $this->simpanFile(
            $file,
            $pegawai->id,
            (int) $data['tahun_pelajaran_id'],
            (int) $data['semester'],
            (int) $data['tingkat'],
        );
        $waktu = now();

        try {
            $perangkatAjar = DB::transaction(function () use ($pengguna, $pegawai, $data, $file, $lokasi, $waktu): PerangkatAjar {
                $perangkatAjar = PerangkatAjar::create([
                    ...$data,
                    ...$this->dataFile($file, $lokasi),
                    'pegawai_id' => $pegawai->id,
                    'status' => 'menunggu_pemeriksaan',
                    'diunggah_pada' => $waktu,
                ]);
                $this->simpanRiwayat($pengguna, $perangkatAjar, $file, $lokasi, $waktu, $data['catatan_guru'] ?? null);

                return $perangkatAjar;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($lokasi);
            throw $exception;
        }

        $this->kirimNotifikasi($pengguna, $perangkatAjar);

        return $perangkatAjar;
    }

    public function ubah(
        Pengguna $pengguna,
        PerangkatAjar $perangkatAjar,
        array $data,
        ?UploadedFile $file,
    ): void {
        $this->pastikanPemilik($pengguna, $perangkatAjar);
        $this->pastikanPenugasanGuru(
            $perangkatAjar->pegawai_id,
            $perangkatAjar->tahun_pelajaran_id,
            $perangkatAjar->mata_pelajaran_id,
            (int) $data['tingkat'],
        );
        $duplikat = PerangkatAjar::query()
            ->whereKeyNot($perangkatAjar->id)
            ->where('pegawai_id', $perangkatAjar->pegawai_id)
            ->where('tahun_pelajaran_id', $perangkatAjar->tahun_pelajaran_id)
            ->where('semester', $perangkatAjar->semester)
            ->where('mata_pelajaran_id', $perangkatAjar->mata_pelajaran_id)
            ->where('tingkat', $data['tingkat'])
            ->where('jenis_perangkat_ajar_id', $perangkatAjar->jenis_perangkat_ajar_id)
            ->exists();
        if ($duplikat) {
            throw ValidationException::withMessages([
                'tingkat' => 'Perangkat ajar untuk tingkat ini sudah tersedia.',
            ]);
        }

        $tingkatBerubah = (int) $perangkatAjar->tingkat !== (int) $data['tingkat'];
        if (! $file) {
            $perangkatAjar->update([
                ...$data,
                ...($tingkatBerubah ? $this->dataMenungguPemeriksaan() : []),
            ]);
            if ($tingkatBerubah) {
                $this->kirimNotifikasi($pengguna, $perangkatAjar->fresh());
            }

            return;
        }

        $lokasi = $this->simpanFile(
            $file,
            $perangkatAjar->pegawai_id,
            $perangkatAjar->tahun_pelajaran_id,
            $perangkatAjar->semester,
            (int) $data['tingkat'],
        );
        $waktu = now();
        try {
            DB::transaction(function () use ($pengguna, $perangkatAjar, $data, $file, $lokasi, $waktu): void {
                $perangkatAjar->update([
                    ...$data,
                    ...$this->dataFile($file, $lokasi),
                    ...$this->dataMenungguPemeriksaan(),
                    'diunggah_pada' => $waktu,
                ]);
                $this->simpanRiwayat($pengguna, $perangkatAjar, $file, $lokasi, $waktu, $data['catatan_guru'] ?? null);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($lokasi);
            throw $exception;
        }

        $this->kirimNotifikasi($pengguna, $perangkatAjar);
    }

    private function ringkasJenis(JenisPerangkatAjar $jenis): array
    {
        return [
            'id' => (int) $jenis->id,
            'kode' => $jenis->kode,
            'nama' => $jenis->nama,
            'deskripsi' => $jenis->deskripsi,
            'wajib' => (bool) $jenis->wajib,
        ];
    }

    private function ringkasDokumen(PerangkatAjar $item): array
    {
        return [
            'id' => (int) $item->id,
            'judul' => $item->judul,
            'tingkat' => $item->tingkat ? (int) $item->tingkat : null,
            'label_tingkat' => $item->tingkatTampil(),
            'nama_file' => $item->nama_file_asli,
            'ukuran_file' => (int) $item->ukuran_file,
            'status' => $item->status,
            'label_status' => $item->labelStatus(),
            'diunggah_pada' => $item->diunggah_pada?->toISOString(),
        ];
    }

    private function pegawaiAktif(Pengguna $pengguna)
    {
        $pegawai = $pengguna->pegawai;
        abort_unless($pegawai, 403, 'Akun belum terhubung dengan data pegawai.');

        return $pegawai;
    }

    private function pastikanPemilik(Pengguna $pengguna, PerangkatAjar $perangkatAjar): void
    {
        abort_unless(
            $pengguna->pegawai_id
                && (int) $pengguna->pegawai_id === (int) $perangkatAjar->pegawai_id,
            403,
        );
    }

    private function pastikanPenugasanGuru(int $pegawaiId, int $tahunId, int $mapelId, int $tingkat): void
    {
        $ditugaskan = GuruMataPelajaran::query()
            ->where('pegawai_id', $pegawaiId)
            ->where('tahun_pelajaran_id', $tahunId)
            ->where('mata_pelajaran_id', $mapelId)
            ->where('aktif', true)
            ->whereHas('kelas', fn ($query) => $query
                ->where('aktif', true)
                ->where('tingkat', $tingkat))
            ->exists();
        if (! $ditugaskan) {
            throw ValidationException::withMessages([
                'tingkat' => 'Pilih tingkat yang sesuai dengan kelas yang diajar untuk mata pelajaran ini.',
            ]);
        }
    }

    private function pastikanJenisAktif(int $jenisId): void
    {
        if (! JenisPerangkatAjar::whereKey($jenisId)->where('aktif', true)->exists()) {
            throw ValidationException::withMessages([
                'jenis_perangkat_ajar_id' => 'Jenis perangkat ajar ini tidak aktif.',
            ]);
        }
    }

    private function simpanFile(UploadedFile $file, int $pegawaiId, int $tahunId, int $semester, int $tingkat): string
    {
        return $file->store(
            "perangkat-ajar/{$pegawaiId}/{$tahunId}/semester-{$semester}/tingkat-{$tingkat}",
            'local',
        );
    }

    private function dataFile(UploadedFile $file, string $lokasi): array
    {
        return [
            'lokasi_file' => $lokasi,
            'nama_file_asli' => $file->getClientOriginalName(),
            'tipe_file' => $file->getMimeType() ?: 'application/pdf',
            'ukuran_file' => $file->getSize(),
        ];
    }

    private function simpanRiwayat(
        Pengguna $pengguna,
        PerangkatAjar $perangkatAjar,
        UploadedFile $file,
        string $lokasi,
        $waktu,
        ?string $catatan,
    ): void {
        $perangkatAjar->riwayatFile()->create([
            ...$this->dataFile($file, $lokasi),
            'diunggah_oleh_pengguna_id' => $pengguna->id,
            'catatan' => filled($catatan) ? trim($catatan) : null,
            'diunggah_pada' => $waktu,
        ]);
    }

    private function dataMenungguPemeriksaan(): array
    {
        return [
            'status' => 'menunggu_pemeriksaan',
            'pemeriksa_pegawai_id' => null,
            'catatan_pemeriksa' => null,
            'diperiksa_pada' => null,
        ];
    }

    private function kirimNotifikasi(Pengguna $pengguna, PerangkatAjar $perangkatAjar): void
    {
        $perangkatAjar->loadMissing(['pegawai', 'mataPelajaran', 'jenisPerangkatAjar']);
        $penerima = $this->notifikasiService->penggunaDenganPeran(
            ['administrator', 'wakil_pimpinan_kurikulum'],
            $pengguna->id,
        );
        $waktuVersi = $perangkatAjar->diunggah_pada?->format('Uv') ?? $perangkatAjar->updated_at?->format('Uv');
        $this->notifikasiService->kirimKeBanyak(
            $penerima,
            'peringatan',
            'Perangkat ajar menunggu pemeriksaan',
            sprintf(
                '%s mengunggah %s untuk mata pelajaran %s tingkat %s.',
                $perangkatAjar->pegawai?->nama_lengkap ?? 'Seorang guru',
                $perangkatAjar->jenisPerangkatAjar?->nama ?? 'perangkat ajar',
                $perangkatAjar->mataPelajaran?->nama ?? '-',
                $perangkatAjar->tingkatTampil(),
            ),
            route('pemeriksaan-perangkat-ajar.show', [
                'pegawai' => $perangkatAjar->pegawai_id,
                'tahun_pelajaran_id' => $perangkatAjar->tahun_pelajaran_id,
                'semester' => $perangkatAjar->semester,
            ], false),
            "perangkat-ajar-menunggu:{$perangkatAjar->id}:{$waktuVersi}",
        );
    }

    private function ambilTahunPelajaranId(?int $tahunId, $daftar): ?int
    {
        if ($tahunId && $daftar->contains('id', $tahunId)) {
            return $tahunId;
        }

        return $daftar->firstWhere('aktif', true)?->id ?? $daftar->first()?->id;
    }

    private function informasiBatasUnggahPdf(): array
    {
        $batasUploadPhp = $this->ukuranKonfigurasiKeByte(ini_get('upload_max_filesize'));
        $batasPostPhp = $this->ukuranKonfigurasiKeByte(ini_get('post_max_size'));
        $batasPostFile = $batasPostPhp ? max(1, $batasPostPhp - (512 * 1024)) : null;
        $batasEfektif = min(array_filter(
            [self::BATAS_PDF_BYTE, $batasUploadPhp, $batasPostFile],
            fn ($batas) => is_int($batas) && $batas > 0,
        ));

        return [
            'byte' => $batasEfektif,
            'label' => rtrim(rtrim(number_format($batasEfektif / 1024 / 1024, 1, ',', '.'), '0'), ',').' MB',
            'dibatasi_server' => $batasEfektif < self::BATAS_PDF_BYTE,
        ];
    }

    private function ukuranKonfigurasiKeByte(string|false $nilai): ?int
    {
        if ($nilai === false || trim($nilai) === '') {
            return null;
        }
        $nilai = strtolower(trim($nilai));
        $angka = (float) $nilai;
        if ($angka <= 0) {
            return null;
        }

        return match (substr($nilai, -1)) {
            'g' => (int) round($angka * 1024 * 1024 * 1024),
            'm' => (int) round($angka * 1024 * 1024),
            'k' => (int) round($angka * 1024),
            default => (int) round($angka),
        };
    }

    private function kunciDokumen(int $mapelId, int $tingkat, int $jenisId): string
    {
        return "{$mapelId}-{$tingkat}-{$jenisId}";
    }
}
