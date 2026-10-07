<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\PengecualianPresensiSiswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\PengecualianPresensiSiswaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengecualianPresensiController extends Controller
{
    public function __construct(private readonly PengecualianPresensiSiswaService $layanan) {}

    public function index(Request $request)
    {
        $this->layanan->pastikanAkses($request->user());
        $tahun = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
        $kelas = Kelas::with('tahunPelajaran')->orderBy('tahun_pelajaran_id')->orderBy('tingkat')->orderBy('nama')->get();
        $riwayat = PengecualianPresensiSiswa::with(['tahunPelajaran', 'kelas', 'pembuat', 'pembatal'])
            ->orderByDesc('aktif')->orderByDesc('id')->paginate(15);

        return response()->view('pengecualian-presensi.index', compact('tahun', 'kelas', 'riwayat'))->header('Cache-Control', 'private, no-store');
    }

    public function pratinjau(Request $request)
    {
        $this->layanan->pastikanAkses($request->user());
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'jenis' => ['required', Rule::in(array_keys(PengecualianPresensiSiswa::JENIS))],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        return response()->view('pengecualian-presensi.pratinjau', $this->layanan->pratinjau($request->user(), $data))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $this->layanan->pastikanAkses($request->user());
        try {
            $data = $request->validate(['token' => ['required', 'string'], 'konfirmasi' => ['accepted']]);
            $this->layanan->terapkan($request->user(), $data['token']);
        } catch (ValidationException $e) {
            return redirect()->route('pengecualian-presensi.index')->withErrors($e->errors());
        }

        return redirect()->route('pengecualian-presensi.index')->with('berhasil', 'Pengecualian diterapkan. Alfa otomatis pada cakupan ini tidak dihitung. Periksa ulang rekap rapor yang telah disimpan.');
    }

    public function batalkan(Request $request, PengecualianPresensiSiswa $pengecualian)
    {
        $this->layanan->pastikanAkses($request->user());
        $data = $request->validate(['alasan_pembatalan' => ['required', 'string', 'min:10', 'max:1000'], 'konfirmasi' => ['accepted']]);
        $this->layanan->batalkan($request->user(), $pengecualian, $data['alasan_pembatalan']);

        return redirect()->route('pengecualian-presensi.index')->with('berhasil', 'Pengecualian dibatalkan. Perhitungan alfa otomatis kembali berlaku; riwayat tetap tersimpan.');
    }
}
