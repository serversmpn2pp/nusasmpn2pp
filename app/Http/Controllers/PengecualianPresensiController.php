<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
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
        $anggotaPilihan = AnggotaKelas::with(['siswa:id,nama_lengkap,nis,nisn', 'kelas:id,nama'])->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn ($q) => $q->where('aktif', true))->whereHas('kelas', fn ($q) => $q->where('aktif', true))->orderBy('kelas_id')->orderBy('nomor_absen')->get();
        $riwayat = PengecualianPresensiSiswa::with(['tahunPelajaran', 'kelas', 'pembuat', 'pembatal', 'anggotaKelas.siswa', 'anggotaKelas.kelas'])
            ->orderByDesc('aktif')->orderByDesc('id')->paginate(15);

        return response()->view('pengecualian-presensi.index', compact('tahun', 'kelas', 'riwayat', 'anggotaPilihan'))->header('Cache-Control', 'private, no-store');
    }

    public function pratinjau(Request $request)
    {
        $this->layanan->pastikanAkses($request->user());
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'cakupan' => ['nullable', Rule::in(['sekolah', 'kelas', 'siswa'])],
            'anggota_kelas_ids' => ['nullable', 'array', 'max:200'],
            'anggota_kelas_ids.*' => ['required', 'integer', 'distinct'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'jenis' => ['required', Rule::in(array_keys(PengecualianPresensiSiswa::jenisWeb()))],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        if (($data['cakupan'] ?? null) === 'kelas' && empty($data['kelas_id'])) {
            throw ValidationException::withMessages(['kelas_id' => 'Pilih kelas untuk cakupan kelas.']);
        }
        if (($data['cakupan'] ?? null) === 'sekolah') {
            $data['kelas_id'] = null;
        }

        $hasil = $this->layanan->pratinjau($request->user(), $data);
        $request->session()->flashInput($data);

        return response()->view('pengecualian-presensi.pratinjau', $hasil)
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

        return redirect()->route('pengecualian-presensi.index')->with('berhasil', 'Penetapan presensi diterapkan sesuai tanggal dan peserta. Periksa ulang rekap rapor yang telah disimpan.');
    }

    public function batalkan(Request $request, PengecualianPresensiSiswa $pengecualian)
    {
        $this->layanan->pastikanAkses($request->user());
        $data = $request->validate(['alasan_pembatalan' => ['required', 'string', 'min:10', 'max:1000'], 'konfirmasi' => ['accepted']]);
        $this->layanan->batalkan($request->user(), $pengecualian, $data['alasan_pembatalan']);

        return redirect()->route('pengecualian-presensi.index')->with('berhasil', 'Pengecualian dibatalkan. Perhitungan alfa otomatis kembali berlaku; riwayat tetap tersimpan.');
    }
}
