<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Services\Piket\CatatKehadiranSiswaPiketService;
use App\Services\Piket\GuruPiketHariIniService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PiketKehadiranSiswaController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'belum_scan', 'hadir', 'sakit', 'izin', 'alfa'])],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);
        $piket = app(GuruPiketHariIniService::class);
        $tahunPelajaranAktif = $piket->tahunPelajaranAktif();
        $piket->pastikanSedangPiket($request->user(), $tahunPelajaranAktif);

        $tanggal = now()->toDateString();
        $kelas = Kelas::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'nama', 'tingkat']);
        $kelasId = isset($data['kelas_id']) && $kelas->contains('id', (int) $data['kelas_id'])
            ? (int) $data['kelas_id']
            : null;
        $status = $data['status'] ?? 'semua';
        $cari = trim((string) ($data['cari'] ?? ''));

        $query = AnggotaKelas::query()
            ->with(['kelas:id,nama', 'siswa:id,nama_lengkap,nis,nisn,foto'])
            ->where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->when($cari !== '', function ($query) use ($cari) {
                $kataKunci = '%'.mb_strtolower($cari).'%';
                $query->whereHas('siswa', fn ($query) => $query
                    ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$kataKunci])
                    ->orWhereRaw('LOWER(nis) LIKE ?', [$kataKunci])
                    ->orWhereRaw('LOWER(nisn) LIKE ?', [$kataKunci]));
            })
            ->when($status === 'belum_scan', fn ($query) => $query->whereDoesntHave(
                'siswa.absensiSiswa',
                fn ($query) => $query->whereDate('tanggal', $tanggal),
            ))
            ->when(in_array($status, ['hadir', 'sakit', 'izin', 'alfa'], true), fn ($query) => $query->whereHas(
                'siswa.absensiSiswa',
                fn ($query) => $query->whereDate('tanggal', $tanggal)->where('status_kehadiran', $status),
            ))
            ->orderBy('kelas_id')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id');

        $anggotaKelas = $query->paginate(40)->withQueryString();
        $absensiPerSiswa = AbsensiSiswa::query()
            ->whereDate('tanggal', $tanggal)
            ->whereIn('siswa_id', $anggotaKelas->getCollection()->pluck('siswa_id'))
            ->get()
            ->keyBy('siswa_id');
        $cakupanAnggota = AnggotaKelas::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId));
        $jumlahSiswa = (clone $cakupanAnggota)->count();
        $absensiCakupan = AbsensiSiswa::query()
            ->whereDate('tanggal', $tanggal)
            ->where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId));
        $jumlahTercatat = (clone $absensiCakupan)->distinct('siswa_id')->count('siswa_id');

        return view('piket-kehadiran-siswa.index', [
            'tahunPelajaranAktif' => $tahunPelajaranAktif,
            'tanggal' => $tanggal,
            'kelas' => $kelas,
            'kelasId' => $kelasId,
            'status' => $status,
            'cari' => $cari,
            'anggotaKelas' => $anggotaKelas,
            'absensiPerSiswa' => $absensiPerSiswa,
            'ringkasan' => [
                'total' => $jumlahSiswa,
                'hadir' => (clone $absensiCakupan)->where('status_kehadiran', 'hadir')->count(),
                'sakit' => (clone $absensiCakupan)->where('status_kehadiran', 'sakit')->count(),
                'izin' => (clone $absensiCakupan)->where('status_kehadiran', 'izin')->count(),
                'belum_scan' => max($jumlahSiswa - $jumlahTercatat, 0),
            ],
        ]);
    }

    public function update(
        Request $request,
        AnggotaKelas $anggotaKelas,
        CatatKehadiranSiswaPiketService $service,
    ) {
        $data = $request->validate([
            'status_kehadiran' => ['required', Rule::in(['sakit', 'izin'])],
            'catatan' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $service->catat($request->user(), $anggotaKelas, $data['status_kehadiran'], $data['catatan']);

        return back()->with('berhasil', 'Kehadiran siswa berhasil dicatat oleh guru piket.');
    }
}
