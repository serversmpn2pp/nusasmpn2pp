<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PengaturanAbsensiPegawai;
use App\Services\Mobile\PengaturanPresensiPegawaiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengaturanPresensiPegawaiController extends Controller
{
    public function index(Request $request, PengaturanPresensiPegawaiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'hari' => ['nullable', Rule::in(['semua', ...array_keys(PengaturanAbsensiPegawai::DAFTAR_HARI)])],
            'cakupan' => ['nullable', Rule::in(['semua_cakupan', ...array_keys(PengaturanAbsensiPegawai::DAFTAR_CAKUPAN)])],
            'status' => ['nullable', Rule::in(['semua_status', 'aktif', 'nonaktif'])],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($request->user(), $filter),
        ]);
    }

    public function store(Request $request, PengaturanPresensiPegawaiMobileService $service): JsonResponse
    {
        $pengaturan = $service->tambah($request->validate($this->aturanValidasi()));

        return $this->tanpaCache([
            'pesan' => 'Pengaturan presensi pegawai berhasil ditambahkan.',
            'data' => ['id' => (int) $pengaturan->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        PengaturanAbsensiPegawai $pengaturanAbsensiPegawai,
        PengaturanPresensiPegawaiMobileService $service,
    ): JsonResponse {
        $service->ubah(
            $pengaturanAbsensiPegawai,
            $request->validate($this->aturanValidasi()),
        );

        return $this->tanpaCache([
            'pesan' => 'Pengaturan presensi pegawai berhasil diperbarui.',
        ]);
    }

    private function aturanValidasi(): array
    {
        return [
            'nama_jadwal' => ['required', 'string', 'max:120'],
            'cakupan' => ['required', Rule::in(array_keys(PengaturanAbsensiPegawai::DAFTAR_CAKUPAN))],
            'jenis_pegawai' => ['nullable', 'required_if:cakupan,jenis_pegawai', 'string', 'max:100'],
            'pegawai_id' => ['nullable', 'required_if:cakupan,pegawai', 'integer', Rule::exists('pegawai', 'id')],
            'hari' => ['required', Rule::in(array_keys(PengaturanAbsensiPegawai::DAFTAR_HARI))],
            'jam_scan_masuk_mulai' => ['required', 'date_format:H:i'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_scan_masuk_selesai' => ['required', 'date_format:H:i'],
            'jam_scan_pulang_mulai' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i'],
            'jam_scan_pulang_selesai' => ['required', 'date_format:H:i'],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
