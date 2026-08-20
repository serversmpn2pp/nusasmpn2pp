<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JenisPerangkatAjar;
use App\Models\PerangkatAjar;
use App\Models\RiwayatFilePerangkatAjar;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\PerangkatAjar\PenugasanPerangkatAjarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PerangkatAjarSayaController extends Controller
{
    private const BATAS_PDF_KILOBYTE = 10240;

    private const BATAS_PDF_BYTE = 10 * 1024 * 1024;

    public function __construct(
        private NotifikasiPenggunaService $notifikasiPenggunaService,
        private PenugasanPerangkatAjarService $penugasanPerangkatAjarService,
    ) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'semester' => ['nullable', Rule::in(['1', '2'])],
        ]);

        $pegawai = $request->user()?->pegawai;
        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $tahunPelajaran);
        $semester = (int) ($data['semester'] ?? 1);

        $penugasanPerTingkat = $this->penugasanPerangkatAjarService
            ->untukGuru($pegawai?->id, $tahunPelajaranId);
        $mataPelajaran = $penugasanPerTingkat
            ->pluck('mata_pelajaran')
            ->unique('id')
            ->values();
        $jenisPerangkatAjar = JenisPerangkatAjar::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
        $semuaPerangkatAjar = PerangkatAjar::query()
            ->with(['mataPelajaran', 'jenisPerangkatAjar'])
            ->when($pegawai, fn ($query) => $query->where('pegawai_id', $pegawai->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->where('semester', $semester)
            ->whereIn('mata_pelajaran_id', $mataPelajaran->pluck('id'))
            ->whereIn('jenis_perangkat_ajar_id', $jenisPerangkatAjar->pluck('id'))
            ->get();
        $kunciPenugasan = $penugasanPerTingkat->pluck('kunci');
        $perangkatTanpaTingkat = $semuaPerangkatAjar
            ->whereNull('tingkat')
            ->values();
        $perangkatAjar = $semuaPerangkatAjar
            ->filter(fn (PerangkatAjar $item) => (
                $item->tingkat
                && $kunciPenugasan->contains(
                    $this->penugasanPerangkatAjarService->kunci($item->mata_pelajaran_id, $item->tingkat)
                )
            ))
            ->keyBy(fn (PerangkatAjar $item) => $this->kunciPerangkat(
                $item->mata_pelajaran_id,
                $item->tingkat,
                $item->jenis_perangkat_ajar_id,
            ));

        $jenisWajibIds = $jenisPerangkatAjar->where('wajib', true)->pluck('id');
        $jumlahWajib = $penugasanPerTingkat->count() * $jenisWajibIds->count();
        $jumlahTerunggah = $perangkatAjar
            ->whereIn('mata_pelajaran_id', $mataPelajaran->pluck('id'))
            ->whereIn('jenis_perangkat_ajar_id', $jenisWajibIds)
            ->count();

        return view('perangkat-ajar-saya.index', [
            'pegawai' => $pegawai,
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'semester' => $semester,
            'mataPelajaran' => $mataPelajaran,
            'penugasanPerTingkat' => $penugasanPerTingkat,
            'jenisPerangkatAjar' => $jenisPerangkatAjar,
            'perangkatAjar' => $perangkatAjar,
            'perangkatTanpaTingkat' => $perangkatTanpaTingkat,
            'jumlahWajib' => $jumlahWajib,
            'jumlahTerunggah' => $jumlahTerunggah,
            'jumlahMenunggu' => $perangkatAjar->where('status', 'menunggu_pemeriksaan')->count(),
            'jumlahPerluPerbaikan' => $perangkatAjar->where('status', 'perlu_perbaikan')->count(),
        ]);
    }

    public function create(Request $request)
    {
        $pegawai = $this->pegawaiAktif($request);
        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            $request->integer('tahun_pelajaran_id') ?: null,
            $tahunPelajaran,
        );
        $penugasanPerTingkat = $this->penugasanPerangkatAjarService
            ->untukGuru($pegawai->id, $tahunPelajaranId);
        $mataPelajaran = $penugasanPerTingkat
            ->pluck('mata_pelajaran')
            ->unique('id')
            ->values();
        $tingkatPerMataPelajaran = $penugasanPerTingkat
            ->groupBy('mata_pelajaran_id')
            ->map(fn ($items) => $items->pluck('tingkat')->values());

        return view('perangkat-ajar-saya.create', [
            'pegawai' => $pegawai,
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'semester' => in_array((int) $request->input('semester'), [1, 2], true) ? (int) $request->input('semester') : 1,
            'mataPelajaran' => $mataPelajaran,
            'mataPelajaranId' => $request->integer('mata_pelajaran_id') ?: null,
            'tingkat' => in_array($request->integer('tingkat'), [7, 8, 9], true)
                ? $request->integer('tingkat')
                : null,
            'tingkatPerMataPelajaran' => $tingkatPerMataPelajaran,
            'jenisPerangkatAjar' => JenisPerangkatAjar::query()
                ->where('aktif', true)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
            'jenisPerangkatAjarId' => $request->integer('jenis_perangkat_ajar_id') ?: null,
            'batasUnggahPdf' => $this->informasiBatasUnggahPdf(),
        ]);
    }

    public function store(Request $request)
    {
        $pegawai = $this->pegawaiAktif($request);
        $data = $request->validate($this->aturanValidasiUnggah(), $this->pesanValidasiPdf());
        unset($data['file_pdf']);
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
            ->first();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'jenis_perangkat_ajar_id' => 'Dokumen ini sudah pernah diunggah. Gunakan tombol Revisi pada dokumen yang sudah ada.',
            ]);
        }

        $file = $request->file('file_pdf');
        $lokasiFile = $this->simpanFile(
            $file,
            $pegawai->id,
            (int) $data['tahun_pelajaran_id'],
            (int) $data['semester'],
            (int) $data['tingkat'],
        );
        $waktuUnggah = now();

        try {
            $perangkatAjar = DB::transaction(function () use ($request, $pegawai, $data, $file, $lokasiFile, $waktuUnggah) {
                $perangkatAjar = PerangkatAjar::create(array_merge(
                    $data,
                    $this->dataFile($file, $lokasiFile),
                    [
                        'pegawai_id' => $pegawai->id,
                        'status' => 'menunggu_pemeriksaan',
                        'diunggah_pada' => $waktuUnggah,
                    ],
                ));

                $this->simpanRiwayatFile($request, $perangkatAjar, $file, $lokasiFile, $waktuUnggah);

                return $perangkatAjar;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($lokasiFile);

            throw $exception;
        }

        $this->kirimNotifikasiMenungguPemeriksaan($request, $perangkatAjar);

        return redirect()
            ->route('perangkat-ajar-saya.show', $perangkatAjar)
            ->with('berhasil', 'Perangkat ajar berhasil diunggah dan menunggu pemeriksaan.');
    }

    public function show(Request $request, PerangkatAjar $perangkatAjar)
    {
        $this->pastikanBolehMelihat($request, $perangkatAjar);
        $perangkatAjar->load([
            'pegawai',
            'tahunPelajaran',
            'mataPelajaran',
            'jenisPerangkatAjar',
            'pemeriksa',
            'riwayatFile.pengunggah',
        ]);

        return view('perangkat-ajar-saya.show', compact('perangkatAjar'));
    }

    public function edit(Request $request, PerangkatAjar $perangkatAjar)
    {
        $this->pastikanPemilik($request, $perangkatAjar);
        $perangkatAjar->load(['tahunPelajaran', 'mataPelajaran', 'jenisPerangkatAjar']);
        $daftarTingkat = $this->penugasanPerangkatAjarService
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

        return view('perangkat-ajar-saya.edit', [
            'perangkatAjar' => $perangkatAjar,
            'daftarTingkat' => $daftarTingkat,
            'batasUnggahPdf' => $this->informasiBatasUnggahPdf(),
        ]);
    }

    public function update(Request $request, PerangkatAjar $perangkatAjar)
    {
        $this->pastikanPemilik($request, $perangkatAjar);
        $data = $request->validate(
            [
                'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
                'judul' => ['required', 'string', 'max:180'],
                'catatan_guru' => ['nullable', 'string'],
                'file_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.self::BATAS_PDF_KILOBYTE],
            ],
            $this->pesanValidasiPdf(),
        );
        $this->pastikanPenugasanGuru(
            $perangkatAjar->pegawai_id,
            $perangkatAjar->tahun_pelajaran_id,
            $perangkatAjar->mata_pelajaran_id,
            (int) $data['tingkat'],
        );
        $dokumenSerupa = PerangkatAjar::query()
            ->whereKeyNot($perangkatAjar->id)
            ->where('pegawai_id', $perangkatAjar->pegawai_id)
            ->where('tahun_pelajaran_id', $perangkatAjar->tahun_pelajaran_id)
            ->where('semester', $perangkatAjar->semester)
            ->where('mata_pelajaran_id', $perangkatAjar->mata_pelajaran_id)
            ->where('tingkat', $data['tingkat'])
            ->where('jenis_perangkat_ajar_id', $perangkatAjar->jenis_perangkat_ajar_id)
            ->exists();

        if ($dokumenSerupa) {
            throw ValidationException::withMessages([
                'tingkat' => 'Perangkat ajar untuk tingkat ini sudah tersedia. Gunakan dokumen yang sudah ada.',
            ]);
        }

        $tingkatBerubah = (int) $perangkatAjar->tingkat !== (int) $data['tingkat'];
        $file = $request->file('file_pdf');
        unset($data['file_pdf']);

        if (! $file) {
            if ($tingkatBerubah) {
                $data = array_merge($data, [
                    'status' => 'menunggu_pemeriksaan',
                    'pemeriksa_pegawai_id' => null,
                    'catatan_pemeriksa' => null,
                    'diperiksa_pada' => null,
                ]);
            }
            $perangkatAjar->update($data);

            if ($tingkatBerubah) {
                $this->kirimNotifikasiMenungguPemeriksaan($request, $perangkatAjar->fresh());
            }

            return redirect()
                ->route('perangkat-ajar-saya.show', $perangkatAjar)
                ->with('berhasil', $tingkatBerubah
                    ? 'Tingkat perangkat ajar berhasil diperbarui dan dokumen kembali menunggu pemeriksaan.'
                    : 'Informasi perangkat ajar berhasil diperbarui.');
        }

        $lokasiFile = $this->simpanFile(
            $file,
            $perangkatAjar->pegawai_id,
            $perangkatAjar->tahun_pelajaran_id,
            $perangkatAjar->semester,
            (int) $data['tingkat'],
        );
        $waktuUnggah = now();

        try {
            DB::transaction(function () use ($request, $perangkatAjar, $data, $file, $lokasiFile, $waktuUnggah) {
                $perangkatAjar->update(array_merge(
                    $data,
                    $this->dataFile($file, $lokasiFile),
                    [
                        'status' => 'menunggu_pemeriksaan',
                        'pemeriksa_pegawai_id' => null,
                        'catatan_pemeriksa' => null,
                        'diperiksa_pada' => null,
                        'diunggah_pada' => $waktuUnggah,
                    ],
                ));

                $this->simpanRiwayatFile($request, $perangkatAjar, $file, $lokasiFile, $waktuUnggah);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($lokasiFile);

            throw $exception;
        }

        $this->kirimNotifikasiMenungguPemeriksaan($request, $perangkatAjar);

        return redirect()
            ->route('perangkat-ajar-saya.show', $perangkatAjar)
            ->with('berhasil', 'Revisi PDF berhasil diunggah dan kembali menunggu pemeriksaan.');
    }

    public function download(Request $request, PerangkatAjar $perangkatAjar)
    {
        $this->pastikanBolehMelihat($request, $perangkatAjar);

        abort_unless(Storage::disk('local')->exists($perangkatAjar->lokasi_file), 404);

        return Storage::disk('local')->download($perangkatAjar->lokasi_file, $perangkatAjar->nama_file_asli);
    }

    public function downloadRiwayat(Request $request, RiwayatFilePerangkatAjar $riwayatFilePerangkatAjar)
    {
        $riwayatFilePerangkatAjar->loadMissing('perangkatAjar');
        $this->pastikanBolehMelihat($request, $riwayatFilePerangkatAjar->perangkatAjar);

        abort_unless(Storage::disk('local')->exists($riwayatFilePerangkatAjar->lokasi_file), 404);

        return Storage::disk('local')->download(
            $riwayatFilePerangkatAjar->lokasi_file,
            $riwayatFilePerangkatAjar->nama_file_asli,
        );
    }

    private function aturanValidasiUnggah(): array
    {
        return [
            'tahun_pelajaran_id' => ['required', 'exists:tahun_pelajaran,id'],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'jenis_perangkat_ajar_id' => ['required', 'exists:jenis_perangkat_ajar,id'],
            'judul' => ['required', 'string', 'max:180'],
            'catatan_guru' => ['nullable', 'string'],
            'file_pdf' => ['required', 'file', 'mimes:pdf', 'max:'.self::BATAS_PDF_KILOBYTE],
        ];
    }

    private function pesanValidasiPdf(): array
    {
        return [
            'file_pdf.required' => 'File PDF wajib dipilih.',
            'file_pdf.file' => 'Berkas yang dipilih tidak dapat dibaca sebagai file.',
            'file_pdf.mimes' => 'Perangkat ajar harus berupa file PDF.',
            'file_pdf.max' => 'Ukuran PDF melebihi batas 10 MB. Pilih file PDF yang lebih kecil.',
            'file_pdf.uploaded' => 'PDF gagal diunggah. Pastikan ukurannya tidak melebihi batas yang ditampilkan dan kapasitas unggah PHP server sudah mencukupi.',
        ];
    }

    private function informasiBatasUnggahPdf(): array
    {
        $batasUploadPhp = $this->ukuranKonfigurasiKeByte(ini_get('upload_max_filesize'));
        $batasPostPhp = $this->ukuranKonfigurasiKeByte(ini_get('post_max_size'));
        $batasPostFile = $batasPostPhp
            ? max(1, $batasPostPhp - (512 * 1024))
            : null;
        $daftarBatas = array_filter(
            [self::BATAS_PDF_BYTE, $batasUploadPhp, $batasPostFile],
            fn ($batas) => is_int($batas) && $batas > 0,
        );
        $batasEfektif = min($daftarBatas);

        return [
            'byte' => $batasEfektif,
            'label' => $this->formatMegabyte($batasEfektif).' MB',
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

    private function formatMegabyte(int $byte): string
    {
        $nilai = number_format($byte / 1024 / 1024, 1, ',', '.');

        return rtrim(rtrim($nilai, '0'), ',');
    }

    private function kirimNotifikasiMenungguPemeriksaan(Request $request, PerangkatAjar $perangkatAjar): void
    {
        $perangkatAjar->loadMissing(['pegawai', 'mataPelajaran', 'jenisPerangkatAjar']);
        $penerima = $this->notifikasiPenggunaService->penggunaDenganPeran(
            ['administrator', 'wakil_pimpinan_kurikulum'],
            $request->user()?->id,
        );
        $waktuVersi = $perangkatAjar->diunggah_pada?->format('Uv') ?? $perangkatAjar->updated_at?->format('Uv');

        $this->notifikasiPenggunaService->kirimKeBanyak(
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

    private function pegawaiAktif(Request $request)
    {
        $pegawai = $request->user()?->pegawai;

        abort_unless($pegawai, 403, 'Akun belum terhubung dengan data pegawai.');

        return $pegawai;
    }

    private function pastikanPemilik(Request $request, PerangkatAjar $perangkatAjar): void
    {
        abort_unless(
            $request->user()?->pegawai_id
                && (int) $request->user()->pegawai_id === (int) $perangkatAjar->pegawai_id,
            403,
        );
    }

    private function pastikanBolehMelihat(Request $request, PerangkatAjar $perangkatAjar): void
    {
        $pengguna = $request->user();
        $pemilik = $pengguna?->pegawai_id
            && (int) $pengguna->pegawai_id === (int) $perangkatAjar->pegawai_id;

        abort_unless(
            $pemilik || $pengguna?->memilikiIzin(['perangkat_ajar.lihat', 'perangkat_ajar.periksa']),
            403,
        );
    }

    private function pastikanPenugasanGuru(
        int $pegawaiId,
        int $tahunPelajaranId,
        int $mataPelajaranId,
        int $tingkat,
    ): void {
        $ditugaskan = GuruMataPelajaran::query()
            ->where('pegawai_id', $pegawaiId)
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('mata_pelajaran_id', $mataPelajaranId)
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

    private function pastikanJenisAktif(int $jenisPerangkatAjarId): void
    {
        if (! JenisPerangkatAjar::whereKey($jenisPerangkatAjarId)->where('aktif', true)->exists()) {
            throw ValidationException::withMessages([
                'jenis_perangkat_ajar_id' => 'Jenis perangkat ajar ini tidak aktif.',
            ]);
        }
    }

    private function simpanFile(
        $file,
        int $pegawaiId,
        int $tahunPelajaranId,
        int $semester,
        int $tingkat,
    ): string {
        return $file->store(
            "perangkat-ajar/{$pegawaiId}/{$tahunPelajaranId}/semester-{$semester}/tingkat-{$tingkat}",
            'local',
        );
    }

    private function dataFile($file, string $lokasiFile): array
    {
        return [
            'lokasi_file' => $lokasiFile,
            'nama_file_asli' => $file->getClientOriginalName(),
            'tipe_file' => $file->getMimeType() ?: 'application/pdf',
            'ukuran_file' => $file->getSize(),
        ];
    }

    private function simpanRiwayatFile(
        Request $request,
        PerangkatAjar $perangkatAjar,
        $file,
        string $lokasiFile,
        $waktuUnggah,
    ): void {
        $perangkatAjar->riwayatFile()->create(array_merge(
            $this->dataFile($file, $lokasiFile),
            [
                'diunggah_oleh_pengguna_id' => $request->user()?->id,
                'catatan' => filled($request->input('catatan_guru')) ? trim($request->input('catatan_guru')) : null,
                'diunggah_pada' => $waktuUnggah,
            ],
        ));
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $tahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        return $tahunPelajaran->firstWhere('aktif', true)?->id ?? $tahunPelajaran->first()?->id;
    }

    private function kunciPerangkat(int $mataPelajaranId, int $tingkat, int $jenisPerangkatAjarId): string
    {
        return $mataPelajaranId.'-'.$tingkat.'-'.$jenisPerangkatAjarId;
    }
}
