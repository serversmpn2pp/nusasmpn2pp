<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\Kelas;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PiketKehadiranSiswaController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'belum_scan', 'hadir', 'sakit', 'izin', 'alfa'])],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);
        $tahunPelajaranAktif = $this->tahunPelajaranAktif();
        $this->pastikanSedangPiket($request, $tahunPelajaranAktif);

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

    public function update(Request $request, AnggotaKelas $anggotaKelas)
    {
        $data = $request->validate([
            'status_kehadiran' => ['required', Rule::in(['sakit', 'izin'])],
            'catatan' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $tahunPelajaranAktif = $this->tahunPelajaranAktif();
        $this->pastikanSedangPiket($request, $tahunPelajaranAktif);

        abort_unless(
            (int) $anggotaKelas->tahun_pelajaran_id === (int) $tahunPelajaranAktif->id
                && $anggotaKelas->status_keanggotaan === 'aktif',
            404,
        );

        DB::transaction(function () use ($request, $data, $anggotaKelas, $tahunPelajaranAktif) {
            $tanggal = now()->toDateString();
            $absensi = AbsensiSiswa::query()
                ->whereDate('tanggal', $tanggal)
                ->where('siswa_id', $anggotaKelas->siswa_id)
                ->lockForUpdate()
                ->first();

            if ($absensi?->jam_masuk || $absensi?->status_kehadiran === 'hadir') {
                throw ValidationException::withMessages([
                    'status_kehadiran' => 'Siswa sudah melakukan scan masuk sehingga tidak dapat dicatat sakit atau izin.',
                ]);
            }

            if ($absensi && $absensi->sumber !== 'guru_piket') {
                throw ValidationException::withMessages([
                    'status_kehadiran' => 'Kehadiran siswa sudah dicatat oleh petugas lain. Gunakan fitur koreksi presensi yang berwenang untuk mengubahnya.',
                ]);
            }

            $statusSebelum = $absensi?->status_kehadiran;
            $absensi ??= new AbsensiSiswa([
                'tanggal' => $tanggal,
                'siswa_id' => $anggotaKelas->siswa_id,
            ]);
            $absensi->fill([
                'tahun_pelajaran_id' => $tahunPelajaranAktif->id,
                'kelas_id' => $anggotaKelas->kelas_id,
                'anggota_kelas_id' => $anggotaKelas->id,
                'jam_masuk' => null,
                'status_masuk' => null,
                'menit_terlambat' => 0,
                'jam_pulang' => null,
                'status_pulang' => null,
                'menit_pulang_cepat' => 0,
                'status_kehadiran' => $data['status_kehadiran'],
                'sumber' => 'guru_piket',
                'catatan' => trim($data['catatan']),
            ])->save();

            RiwayatPerubahanAbsensiSiswa::create([
                'absensi_siswa_id' => $absensi->id,
                'siswa_id' => $anggotaKelas->siswa_id,
                'tanggal' => $tanggal,
                'status_sebelum' => $statusSebelum,
                'status_sesudah' => $data['status_kehadiran'],
                'sumber' => 'guru_piket',
                'catatan' => trim($data['catatan']),
                'dibuat_oleh_pengguna_id' => $request->user()?->id,
            ]);
        });

        return back()->with('berhasil', 'Kehadiran siswa berhasil dicatat oleh guru piket.');
    }

    private function tahunPelajaranAktif(): TahunPelajaran
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        abort_unless($tahunPelajaran, 422, 'Belum ada tahun pelajaran aktif.');

        return $tahunPelajaran;
    }

    private function pastikanSedangPiket(Request $request, TahunPelajaran $tahunPelajaran): void
    {
        $pengguna = $request->user();
        $kodeHari = array_keys(JadwalPiketGuru::DAFTAR_HARI)[now()->dayOfWeekIso - 1] ?? null;
        $guruMapelAktif = $pengguna?->pegawai_id && GuruMataPelajaran::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('jenis_penugasan', 'pengampu')
            ->where('aktif', true)
            ->exists();
        $sedangPiket = $guruMapelAktif && $kodeHari && JadwalPiketGuru::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('hari', $kodeHari)
            ->where('aktif', true)
            ->exists();

        abort_unless($sedangPiket, 403, 'Halaman pencatatan hanya dapat dibuka oleh guru yang sedang bertugas piket hari ini.');
    }
}
