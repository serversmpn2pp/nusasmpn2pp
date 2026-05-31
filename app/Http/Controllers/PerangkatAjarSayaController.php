<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JenisPerangkatAjar;
use App\Models\MataPelajaran;
use App\Models\PerangkatAjar;
use App\Models\RiwayatFilePerangkatAjar;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PerangkatAjarSayaController extends Controller
{
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

        $mataPelajaran = $this->mataPelajaranGuru($pegawai?->id, $tahunPelajaranId);
        $jenisPerangkatAjar = JenisPerangkatAjar::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
        $perangkatAjar = PerangkatAjar::query()
            ->with(['mataPelajaran', 'jenisPerangkatAjar'])
            ->when($pegawai, fn ($query) => $query->where('pegawai_id', $pegawai->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->where('semester', $semester)
            ->whereIn('mata_pelajaran_id', $mataPelajaran->pluck('id'))
            ->whereIn('jenis_perangkat_ajar_id', $jenisPerangkatAjar->pluck('id'))
            ->get()
            ->keyBy(fn (PerangkatAjar $item) => $this->kunciPerangkat($item->mata_pelajaran_id, $item->jenis_perangkat_ajar_id));

        $jenisWajibIds = $jenisPerangkatAjar->where('wajib', true)->pluck('id');
        $jumlahWajib = $mataPelajaran->count() * $jenisWajibIds->count();
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
            'jenisPerangkatAjar' => $jenisPerangkatAjar,
            'perangkatAjar' => $perangkatAjar,
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

        return view('perangkat-ajar-saya.create', [
            'pegawai' => $pegawai,
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'semester' => in_array((int) $request->input('semester'), [1, 2], true) ? (int) $request->input('semester') : 1,
            'mataPelajaran' => $this->mataPelajaranGuru($pegawai->id, $tahunPelajaranId),
            'mataPelajaranId' => $request->integer('mata_pelajaran_id') ?: null,
            'jenisPerangkatAjar' => JenisPerangkatAjar::query()
                ->where('aktif', true)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
            'jenisPerangkatAjarId' => $request->integer('jenis_perangkat_ajar_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $pegawai = $this->pegawaiAktif($request);
        $data = $request->validate($this->aturanValidasiUnggah());
        unset($data['file_pdf']);
        $this->pastikanPenugasanGuru($pegawai->id, (int) $data['tahun_pelajaran_id'], (int) $data['mata_pelajaran_id']);
        $this->pastikanJenisAktif((int) $data['jenis_perangkat_ajar_id']);

        $sudahAda = PerangkatAjar::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('semester', $data['semester'])
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('jenis_perangkat_ajar_id', $data['jenis_perangkat_ajar_id'])
            ->first();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'jenis_perangkat_ajar_id' => 'Dokumen ini sudah pernah diunggah. Gunakan tombol Revisi pada dokumen yang sudah ada.',
            ]);
        }

        $file = $request->file('file_pdf');
        $lokasiFile = $this->simpanFile($file, $pegawai->id, (int) $data['tahun_pelajaran_id'], (int) $data['semester']);
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

        return view('perangkat-ajar-saya.edit', compact('perangkatAjar'));
    }

    public function update(Request $request, PerangkatAjar $perangkatAjar)
    {
        $this->pastikanPemilik($request, $perangkatAjar);
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:180'],
            'catatan_guru' => ['nullable', 'string'],
            'file_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        $file = $request->file('file_pdf');
        unset($data['file_pdf']);

        if (! $file) {
            $perangkatAjar->update($data);

            return redirect()
                ->route('perangkat-ajar-saya.show', $perangkatAjar)
                ->with('berhasil', 'Informasi perangkat ajar berhasil diperbarui.');
        }

        $lokasiFile = $this->simpanFile(
            $file,
            $perangkatAjar->pegawai_id,
            $perangkatAjar->tahun_pelajaran_id,
            $perangkatAjar->semester,
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
            'jenis_perangkat_ajar_id' => ['required', 'exists:jenis_perangkat_ajar,id'],
            'judul' => ['required', 'string', 'max:180'],
            'catatan_guru' => ['nullable', 'string'],
            'file_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
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

    private function pastikanPenugasanGuru(int $pegawaiId, int $tahunPelajaranId, int $mataPelajaranId): void
    {
        $ditugaskan = GuruMataPelajaran::query()
            ->where('pegawai_id', $pegawaiId)
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('mata_pelajaran_id', $mataPelajaranId)
            ->where('aktif', true)
            ->exists();

        if (! $ditugaskan) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Pilih mata pelajaran yang ditugaskan kepada akun guru ini.',
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

    private function mataPelajaranGuru(?int $pegawaiId, ?int $tahunPelajaranId)
    {
        if (! $pegawaiId || ! $tahunPelajaranId) {
            return collect();
        }

        return MataPelajaran::query()
            ->whereHas('guruMataPelajaran', function ($query) use ($pegawaiId, $tahunPelajaranId) {
                $query->where('pegawai_id', $pegawaiId)
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('aktif', true);
            })
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function simpanFile($file, int $pegawaiId, int $tahunPelajaranId, int $semester): string
    {
        return $file->store("perangkat-ajar/{$pegawaiId}/{$tahunPelajaranId}/semester-{$semester}", 'local');
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

    private function kunciPerangkat(int $mataPelajaranId, int $jenisPerangkatAjarId): string
    {
        return $mataPelajaranId . '-' . $jenisPerangkatAjarId;
    }
}
