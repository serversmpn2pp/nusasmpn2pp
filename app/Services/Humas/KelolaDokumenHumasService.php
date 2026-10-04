<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\KerjaSamaHumas;
use App\Models\MitraHumas;
use App\Models\RiwayatDokumenHumas;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class KelolaDokumenHumasService
{
    public const BATAS_BERKAS_KB = 20480;

    public const EKSTENSI_BERKAS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp'];

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

        if ($dokumenHumas->lokasi_file !== $lokasiFile) {
            Storage::disk('local')->delete($lokasiFile);
        }

        return $dokumenHumas->refresh();
    }

    public function update(Request $request, DokumenHumas $dokumenHumas, ?string $sidik = null)
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
            DB::transaction(function () use ($request, $dokumenHumas, $data, $file, $catatanRevisi, $lokasiFile, $waktuUnggah, $sidik) {
                $dokumenHumas = DokumenHumas::query()->lockForUpdate()->findOrFail($dokumenHumas->id);
                $this->periksaSidik($dokumenHumas, $sidik);
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

        return $dokumenHumas->refresh();
    }

    public function ubahStatus(Request $request, DokumenHumas $dokumenHumas, ?string $sidik = null): DokumenHumas
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(DokumenHumas::STATUS))]]);
        DB::transaction(function () use ($request, $dokumenHumas, $sidik, $data) {
            $dokumen = DokumenHumas::lockForUpdate()->findOrFail($dokumenHumas->id);
            $this->periksaSidik($dokumen, $sidik);
            $dokumen->update($data + ['diubah_oleh_pengguna_id' => $request->user()->id]);
        });

        return $dokumenHumas->refresh();
    }

    public function sidik(DokumenHumas $dokumen): string
    {
        $data = Arr::only($dokumen->getAttributes(), ['id', ...$dokumen->getFillable(), 'created_at', 'updated_at']);
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function periksaSidik(DokumenHumas $dokumen, ?string $sidik): void
    {
        if ($sidik !== null && ! hash_equals($this->sidik($dokumen), $sidik)) {
            throw ValidationException::withMessages(['sidik' => 'Dokumen telah berubah. Muat ulang sebelum menyimpan revisi atau status.']);
        }
    }

    public function unduh(DokumenHumas|RiwayatDokumenHumas $berkas): StreamedResponse
    {
        $root = realpath(Storage::disk('local')->path('dokumen-humas'));
        $path = realpath(Storage::disk('local')->path($berkas->lokasi_file));
        abort_unless($root && $path && is_file($path) && is_readable($path), 404);
        $prefix = $root.DIRECTORY_SEPARATOR;
        $diDalamArsip = PHP_OS_FAMILY === 'Windows'
            ? str_starts_with(strtolower($path), strtolower($prefix)) : str_starts_with($path, $prefix);
        abort_unless($diDalamArsip, 404);

        return Storage::disk('local')->download($berkas->lokasi_file, $berkas->nama_file_asli, [
            'Content-Type' => $berkas->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
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
            'berlaku_mulai' => ['nullable', 'date_format:Y-m-d'],
            'berlaku_sampai' => ['nullable', 'date_format:Y-m-d'],
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
