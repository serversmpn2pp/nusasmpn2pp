<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\KerjaSamaHumas;
use App\Models\MitraHumas;
use App\Models\RiwayatDokumenHumas;
use App\Services\Humas\KelolaKemitraanHumasService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DokumenHumasController extends Controller
{
    private const BATAS_BERKAS_KB = 20480;

    private const EKSTENSI_BERKAS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'webp',
    ];

    public function index(Request $request)
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'kategori' => ['nullable', Rule::in(array_keys(DokumenHumas::KATEGORI))],
            'status' => ['nullable', Rule::in(array_keys(DokumenHumas::STATUS))],
        ]);

        $query = DokumenHumas::query()
            ->with(['pembuat:id,nama', 'pengubah:id,nama'])
            ->when($data['kata_kunci'] ?? null, function ($query, string $kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('judul', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('nomor_dokumen', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('deskripsi', 'ilike', '%'.$kataKunci.'%');
                });
            })
            ->when($data['kategori'] ?? null, fn ($query, string $kategori) => $query->where('kategori', $kategori))
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw('berlaku_sampai IS NULL')
            ->orderBy('berlaku_sampai')
            ->orderByDesc('updated_at');

        $dokumen = $query->paginate(20)->withQueryString();
        $aktif = DokumenHumas::query()->where('status', 'aktif');

        return view('dokumen-humas.index', [
            'dokumen' => $dokumen,
            'kategori' => DokumenHumas::KATEGORI,
            'status' => DokumenHumas::STATUS,
            'filter' => $data,
            'jumlahAktif' => (clone $aktif)->count(),
            'jumlahSegeraBerakhir' => (clone $aktif)
                ->whereNotNull('berlaku_sampai')
                ->whereBetween('berlaku_sampai', [today(), today()->addDays(30)])
                ->count(),
            'jumlahKedaluwarsa' => (clone $aktif)
                ->whereNotNull('berlaku_sampai')
                ->whereDate('berlaku_sampai', '<', today())
                ->count(),
        ]);
    }

    public function create(Request $request)
    {
        $data = $request->validate($this->aturanKonteks());
        $agenda = isset($data['agenda_humas_id']) ? AgendaHumas::findOrFail($data['agenda_humas_id']) : null;
        if ($agenda) {
            abort_unless($request->user()->memilikiIzin('agenda_humas.kelola'), 403);
        }

        $mou = ! empty($data['kerja_sama_humas_id']) ? KerjaSamaHumas::with('mitra')->findOrFail($data['kerja_sama_humas_id']) : null;
        if ($mou) {
            abort_unless($request->user()->memilikiIzin('kemitraan_humas.kelola'), 403);
            app(KelolaKemitraanHumasService::class)->pastikanMitraAktif($mou->mitra);
        }

        return view('dokumen-humas.form', [
            'agendaTerkait' => $agenda,
            'mouTerkait' => $mou,
            'tokenUnggahanMou' => (string) Str::uuid(),
            'dokumenHumas' => new DokumenHumas(['ingatkan_hari_sebelum' => 30, 'kategori' => $mou ? 'kemitraan' : null, 'judul' => $mou?->judul, 'nomor_dokumen' => $mou?->nomor]),
            'kategori' => DokumenHumas::KATEGORI,
            'batasBerkasMb' => self::BATAS_BERKAS_KB / 1024,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasi(true) + $this->aturanKonteks());
        if (! empty($data['kerja_sama_humas_id']) && empty($data['token_unggahan_mou'])) {
            throw ValidationException::withMessages(['token_unggahan_mou' => 'Muat ulang formulir unggahan MoU sebelum menyimpan.']);
        }
        $agendaId = $data['agenda_humas_id'] ?? null;
        $mouId = $data['kerja_sama_humas_id'] ?? null;
        $tokenMou = $data['token_unggahan_mou'] ?? null;
        if ($mouId) {
            abort_unless($request->user()->memilikiIzin('kemitraan_humas.kelola'), 403);
        }
        if ($agendaId) {
            abort_unless($request->user()->memilikiIzin('agenda_humas.kelola'), 403);
        }
        $this->validasiRentangTanggal($data);

        $file = $data['berkas'];
        unset($data['berkas'], $data['catatan_revisi'], $data['agenda_humas_id'], $data['kerja_sama_humas_id'], $data['token_unggahan_mou']);
        $lokasiFile = $this->simpanBerkas($file);
        $waktuUnggah = now();

        try {
            $dokumenHumas = DB::transaction(function () use ($request, $data, $file, $lokasiFile, $waktuUnggah, $agendaId, $mouId, $tokenMou) {
                $mou = null;
                if ($mouId) {
                    $awal = KerjaSamaHumas::findOrFail($mouId);
                    $mitra = MitraHumas::lockForUpdate()->findOrFail($awal->mitra_humas_id);
                    app(KelolaKemitraanHumasService::class)->pastikanMitraAktif($mitra);
                    $mou = $mitra->kerjaSama()->lockForUpdate()->findOrFail($mouId);
                    if ($mou->token_unggahan_mou === $tokenMou && $mou->dokumen_humas_id) {
                        $tersimpan = $mou->dokumen()->firstOrFail();
                        abort_unless($tersimpan->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                        return $tersimpan;
                    }
                    if ($mou->status === 'diakhiri' || $mou->dokumen_humas_id) {
                        throw ValidationException::withMessages(['kerja_sama_humas_id' => 'MoU diakhiri atau sudah memiliki berkas. Gunakan revisi pada dokumen yang telah terhubung.']);
                    }
                }
                $agenda = $agendaId ? AgendaHumas::lockForUpdate()->findOrFail($agendaId) : null;
                if ($agenda?->status === 'dibatalkan') {
                    throw ValidationException::withMessages(['agenda_humas_id' => 'Agenda dibatalkan. Jadwalkan kembali sebelum menambahkan dokumen.']);
                }
                $dokumenHumas = DokumenHumas::create(array_merge(
                    $data,
                    $this->dataBerkas($file, $lokasiFile),
                    [
                        'status' => 'aktif',
                        'dibuat_oleh_pengguna_id' => $request->user()->id,
                        'diubah_oleh_pengguna_id' => $request->user()->id,
                    ],
                ));

                $this->buatRiwayat($dokumenHumas, $request, $file, $lokasiFile, 1, 'Dokumen pertama kali diunggah.', $waktuUnggah);
                $agenda?->dokumen()->attach($dokumenHumas->id);
                if ($mou) {
                    $sebelum = $mou->attributesToArray();
                    $mou->update(['dokumen_humas_id' => $dokumenHumas->id, 'versi' => $mou->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id]);
                    $mou->forceFill(['token_unggahan_mou' => $tokenMou])->save();
                    app(KelolaKemitraanHumasService::class)->catat($mitra, $mou, 'Berkas MoU diunggah', $sebelum, $mou->attributesToArray(), $request->user());
                }

                return $dokumenHumas;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($lokasiFile);

            throw $exception;
        }

        if ($mouId) {
            if ($dokumenHumas->lokasi_file !== $lokasiFile) {
                Storage::disk('local')->delete($lokasiFile);
            }
            $mou = KerjaSamaHumas::findOrFail($mouId);
            $url = route('kemitraan-humas.mou.show', [$mou->mitra_humas_id, $mou]);
            $request->session()->flash('berhasil', 'Berkas MoU berhasil diunggah dan dihubungkan.');

            return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => 'Berkas MoU berhasil disimpan.']) : redirect($url);
        }
        if ($agendaId) {
            return redirect()->route('agenda-humas.show', [$agendaId, 'tab' => 'dokumen'])
                ->with('berhasil', 'Dokumen berhasil diunggah dan dihubungkan dengan agenda.');
        }

        return redirect()
            ->route('dokumen-humas.show', $dokumenHumas)
            ->with('berhasil', 'Dokumen Humas berhasil disimpan.');
    }

    public function show(Request $request, DokumenHumas $dokumenHumas)
    {
        $dokumenHumas->load([
            'pembuat:id,nama',
            'pengubah:id,nama',
            'riwayat.pengunggah:id,nama',
        ]);

        $mouTerhubung = $request->user()->memilikiIzin(['kemitraan_humas.lihat', 'kemitraan_humas.kelola'])
            ? KerjaSamaHumas::with('mitra')->where('dokumen_humas_id', $dokumenHumas->id)->get() : collect();

        return view('dokumen-humas.show', compact('dokumenHumas', 'mouTerhubung'));
    }

    public function edit(DokumenHumas $dokumenHumas)
    {
        return view('dokumen-humas.form', [
            'dokumenHumas' => $dokumenHumas,
            'kategori' => DokumenHumas::KATEGORI,
            'batasBerkasMb' => self::BATAS_BERKAS_KB / 1024,
        ]);
    }

    public function update(Request $request, DokumenHumas $dokumenHumas)
    {
        $data = $request->validate($this->aturanValidasi(false));
        $this->validasiRentangTanggal($data);

        $file = $data['berkas'] ?? null;
        $catatanRevisi = $data['catatan_revisi'] ?? null;
        unset($data['berkas'], $data['catatan_revisi']);
        $lokasiFile = $file ? $this->simpanBerkas($file) : null;
        $waktuUnggah = now();

        if ($file && blank($catatanRevisi)) {
            Storage::disk('local')->delete($lokasiFile);

            throw ValidationException::withMessages([
                'catatan_revisi' => 'Catatan revisi wajib diisi saat mengunggah berkas baru.',
            ]);
        }

        try {
            DB::transaction(function () use ($request, $dokumenHumas, $data, $file, $catatanRevisi, $lokasiFile, $waktuUnggah) {
                $dokumenHumas = DokumenHumas::query()->lockForUpdate()->findOrFail($dokumenHumas->id);
                $dokumenHumas->fill(array_merge($data, [
                    'diubah_oleh_pengguna_id' => $request->user()->id,
                ]));

                if ($file && $lokasiFile) {
                    $versi = ((int) $dokumenHumas->riwayat()->max('versi')) + 1;
                    $dokumenHumas->fill($this->dataBerkas($file, $lokasiFile));
                    $this->buatRiwayat(
                        $dokumenHumas,
                        $request,
                        $file,
                        $lokasiFile,
                        $versi,
                        $catatanRevisi,
                        $waktuUnggah,
                    );
                }

                $dokumenHumas->save();
            });
        } catch (Throwable $exception) {
            if ($lokasiFile) {
                Storage::disk('local')->delete($lokasiFile);
            }

            throw $exception;
        }

        return redirect()
            ->route('dokumen-humas.show', $dokumenHumas)
            ->with('berhasil', $file ? 'Informasi dan revisi dokumen berhasil disimpan.' : 'Informasi dokumen berhasil diperbarui.');
    }

    public function unduh(DokumenHumas $dokumenHumas): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($dokumenHumas->lokasi_file), 404);

        return Storage::disk('local')->download($dokumenHumas->lokasi_file, $dokumenHumas->nama_file_asli);
    }

    public function unduhRiwayat(DokumenHumas $dokumenHumas, RiwayatDokumenHumas $riwayatDokumenHumas): StreamedResponse
    {
        abort_unless($riwayatDokumenHumas->dokumen_humas_id === $dokumenHumas->id, 404);
        abort_unless(Storage::disk('local')->exists($riwayatDokumenHumas->lokasi_file), 404);

        return Storage::disk('local')->download(
            $riwayatDokumenHumas->lokasi_file,
            $riwayatDokumenHumas->nama_file_asli,
        );
    }

    public function ubahStatus(Request $request, DokumenHumas $dokumenHumas)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(DokumenHumas::STATUS))],
        ]);

        $dokumenHumas->update([
            'status' => $data['status'],
            'diubah_oleh_pengguna_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('dokumen-humas.show', $dokumenHumas)
            ->with('berhasil', $data['status'] === 'arsip' ? 'Dokumen dipindahkan ke arsip.' : 'Dokumen diaktifkan kembali.');
    }

    private function aturanKonteks(): array
    {
        return ['agenda_humas_id' => ['nullable', 'integer', 'exists:agenda_humas,id', 'prohibits:kerja_sama_humas_id'],
            'kerja_sama_humas_id' => ['nullable', 'integer', 'exists:kerja_sama_humas,id', 'prohibits:agenda_humas_id'],
            'token_unggahan_mou' => ['nullable', 'uuid']];
    }

    private function aturanValidasi(bool $berkasWajib): array
    {
        return [
            'kategori' => ['required', Rule::in(array_keys(DokumenHumas::KATEGORI))],
            'judul' => ['required', 'string', 'max:180'],
            'nomor_dokumen' => ['nullable', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'berlaku_mulai' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date'],
            'ingatkan_hari_sebelum' => ['required', 'integer', 'min:0', 'max:365'],
            'berkas' => [
                $berkasWajib ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', self::EKSTENSI_BERKAS),
                'max:'.self::BATAS_BERKAS_KB,
            ],
            'catatan_revisi' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function validasiRentangTanggal(array $data): void
    {
        if (($data['berlaku_mulai'] ?? null)
            && ($data['berlaku_sampai'] ?? null)
            && $data['berlaku_sampai'] < $data['berlaku_mulai']) {
            throw ValidationException::withMessages([
                'berlaku_sampai' => 'Tanggal berakhir harus sama atau setelah tanggal mulai berlaku.',
            ]);
        }
    }

    private function simpanBerkas(UploadedFile $file): string
    {
        $ekstensi = Str::lower($file->extension() ?: 'bin');
        $nama = Str::uuid().'.'.$ekstensi;
        $lokasi = $file->storeAs('dokumen-humas', $nama, 'local');

        if (! is_string($lokasi)) {
            throw ValidationException::withMessages([
                'berkas' => 'Berkas gagal disimpan. Silakan coba lagi.',
            ]);
        }

        return $lokasi;
    }

    private function dataBerkas(UploadedFile $file, string $lokasiFile): array
    {
        return [
            'lokasi_file' => $lokasiFile,
            'nama_file_asli' => $file->getClientOriginalName(),
            'tipe_file' => $file->getMimeType() ?: 'application/octet-stream',
            'ukuran_file' => $file->getSize(),
        ];
    }

    private function buatRiwayat(
        DokumenHumas $dokumenHumas,
        Request $request,
        UploadedFile $file,
        string $lokasiFile,
        int $versi,
        ?string $catatan,
        Carbon $waktuUnggah,
    ): void {
        $dokumenHumas->riwayat()->create(array_merge(
            $this->dataBerkas($file, $lokasiFile),
            [
                'versi' => $versi,
                'catatan' => $catatan,
                'diunggah_oleh_pengguna_id' => $request->user()->id,
                'diunggah_pada' => $waktuUnggah,
            ],
        ));
    }
}
