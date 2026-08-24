<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Services\Mobile\PegawaiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PegawaiController extends Controller
{
    public function index(Request $request, PegawaiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(Request $request, Pegawai $pegawai, PegawaiMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->detail($request->user(), $pegawai)]);
    }

    public function store(Request $request, PegawaiMobileService $service): JsonResponse
    {
        $pegawai = $service->tambah($request->validate($this->aturanValidasi()));

        return $this->tanpaCache([
            'pesan' => 'Data pegawai berhasil ditambahkan.',
            'data' => ['id' => (int) $pegawai->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        Pegawai $pegawai,
        PegawaiMobileService $service,
    ): JsonResponse {
        $usernameBerubah = $service->ubah(
            $pegawai,
            $request->validate($this->aturanValidasi($pegawai)),
        );

        return $this->tanpaCache([
            'pesan' => 'Data pegawai berhasil diperbarui.'.(
                $usernameBerubah ? ' Username login ikut disesuaikan dengan NIP baru.' : ''
            ),
        ]);
    }

    private function aturanValidasi(?Pegawai $pegawai = null): array
    {
        return [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nip')->ignore($pegawai)],
            'nuptk' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nuptk')->ignore($pegawai)],
            'nik' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nik')->ignore($pegawai)],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'alamat' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('pegawai', 'email')->ignore($pegawai)],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'status_kepegawaian' => ['nullable', 'string', 'max:100'],
            'golongan' => ['nullable', 'string', 'max:50'],
            'tanggal_mulai_kerja' => ['nullable', 'date'],
            'tanggal_mulai_bertugas' => ['nullable', 'date'],
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'jabatan_utama' => ['nullable', 'string', 'max:100'],
            'sumber_gaji' => ['nullable', 'string', 'max:100'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:100'],
            'jurusan_pendidikan' => ['nullable', 'string', 'max:150'],
            'tahun_lulus' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'keterangan' => ['nullable', 'string'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
