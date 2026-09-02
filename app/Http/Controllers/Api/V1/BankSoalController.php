<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SoalCbt;
use App\Services\Mobile\BankSoalMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class BankSoalController extends Controller
{
    public function index(Request $request, BankSoalMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'mata_pelajaran_id' => ['nullable', 'integer', Rule::exists('mata_pelajaran', 'id')],
            'tingkat' => ['nullable', Rule::in(['semua', 7, 8, 9, '7', '8', '9'])],
            'jenis_soal' => ['nullable', Rule::in(['semua', ...array_keys(SoalCbt::DAFTAR_JENIS)])],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(SoalCbt::DAFTAR_STATUS)])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(Request $request, SoalCbt $soalCbt, BankSoalMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->rincian($request->user(), $soalCbt)]);
    }

    public function store(Request $request, BankSoalMobileService $service): JsonResponse
    {
        $this->gabungkanPayload($request);
        $soal = $service->tambah($request->user(), $request->validate($this->aturanValidasi()), $request->file('gambar_soal'));

        return $this->tanpaCache([
            'pesan' => $soal->status === 'siap'
                ? 'Soal berhasil disimpan dan siap digunakan.'
                : 'Soal berhasil disimpan sebagai draf.',
            'data' => $service->rincian($request->user(), $soal),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        SoalCbt $soalCbt,
        BankSoalMobileService $service,
    ): JsonResponse {
        $this->gabungkanPayload($request);
        $service->ubah(
            $request->user(),
            $soalCbt,
            $request->validate($this->aturanValidasi()),
            $request->file('gambar_soal'),
        );

        return $this->tanpaCache([
            'pesan' => $soalCbt->fresh()->status === 'siap'
                ? 'Soal diperbarui dan siap digunakan.'
                : 'Perubahan soal disimpan sebagai draf.',
            'data' => $service->rincian($request->user(), $soalCbt->fresh()),
        ]);
    }

    public function destroy(Request $request, SoalCbt $soalCbt, BankSoalMobileService $service): JsonResponse
    {
        $service->arsipkan($request->user(), $soalCbt);

        return $this->tanpaCache(['pesan' => 'Soal CBT berhasil diarsipkan.']);
    }

    private function aturanValidasi(): array
    {
        return [
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'mata_pelajaran_id' => ['required', 'integer', Rule::exists('mata_pelajaran', 'id')],
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'jenis_soal' => ['required', Rule::in(array_keys(SoalCbt::DAFTAR_JENIS))],
            'tingkat_kesulitan' => ['nullable', Rule::in(array_keys(SoalCbt::DAFTAR_KESULITAN))],
            'kategori' => ['nullable', Rule::in(array_keys(SoalCbt::DAFTAR_KATEGORI))],
            'topik' => ['nullable', 'string', 'max:160'],
            'materi' => ['nullable', 'string', 'max:180'],
            'tujuan_pembelajaran' => ['nullable', 'string', 'max:4000'],
            'stimulus' => ['nullable', 'string', 'max:10000'],
            'pertanyaan' => ['required', 'string', 'max:10000'],
            'skor_maksimal' => ['nullable', 'numeric', 'min:0.25', 'max:100'],
            'pembahasan' => ['nullable', 'string', 'max:10000'],
            'aksi' => ['required', Rule::in(['simpan_draf', 'simpan_siap'])],
            'opsi' => ['nullable', 'array', 'max:8'],
            'opsi.*' => ['nullable', 'string', 'max:800'],
            'kunci_pg' => ['nullable', 'string', 'max:5'],
            'kunci_pgk' => ['nullable', 'array', 'max:8'],
            'kunci_pgk.*' => ['nullable', 'string', 'max:5'],
            'pernyataan' => ['nullable', 'array', 'max:10'],
            'pernyataan.*.teks' => ['nullable', 'string', 'max:800'],
            'pernyataan.*.jawaban' => ['nullable', 'boolean'],
            'pasangan' => ['nullable', 'array', 'max:10'],
            'pasangan.*.kiri' => ['nullable', 'string', 'max:800'],
            'pasangan.*.kanan' => ['nullable', 'string', 'max:800'],
            'kunci_teks' => ['nullable', 'string', 'max:5000'],
            'rubrik_teks' => ['nullable', 'string', 'max:5000'],
            'gambar_soal' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'hapus_gambar_soal' => ['nullable', 'boolean'],
            'gambar_alt' => ['nullable', 'string', 'max:160'],
            'gambar_keterangan' => ['nullable', 'string', 'max:220'],
            'tabel' => ['nullable', 'array', 'max:10'],
            'tabel.*' => ['array', 'max:8'],
            'tabel.*.*' => ['nullable', 'string', 'max:500'],
            'tabel_judul' => ['nullable', 'string', 'max:160'],
            'rumus_latex' => [
                'nullable',
                'string',
                'max:1500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (str_contains((string) $value, '\\placeholder')) {
                        $fail('Lengkapi bagian rumus yang masih kosong.');
                    }
                },
            ],
            'rumus_keterangan' => ['nullable', 'string', 'max:220'],
        ];
    }

    private function gabungkanPayload(Request $request): void
    {
        if (! $request->filled('payload')) {
            return;
        }

        try {
            $payload = json_decode((string) $request->input('payload'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['payload' => 'Data soal tidak dapat dibaca.']);
        }

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['payload' => 'Data soal tidak valid.']);
        }

        $request->merge($payload);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
