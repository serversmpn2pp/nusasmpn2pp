<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PenempatanSiswaController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();
        $cakupanWaliKelas = $pengguna?->membatasiCakupanWaliKelas() ?? false;
        $kelasWaliIds = $cakupanWaliKelas ? $pengguna->kelasWaliIds() : [];
        $bisaKelolaKelas = $pengguna?->memilikiIzin('kelas.kelola') ?? false;

        $tahunPelajaran = TahunPelajaran::query()
            ->withCount([
                'kelas' => function ($query) use ($cakupanWaliKelas, $kelasWaliIds) {
                    if ($cakupanWaliKelas) {
                        $query->whereIn('id', $kelasWaliIds);
                    }
                },
            ])
            ->when($cakupanWaliKelas, function ($query) use ($kelasWaliIds) {
                $query->whereHas('kelas', fn ($query) => $query->whereIn('id', $kelasWaliIds));
            })
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();

        $tahunPelajaranId = $request->integer('tahun_pelajaran_id')
            ?: $tahunPelajaran->firstWhere('aktif', true)?->id
            ?: $tahunPelajaran->first()?->id;

        $kelas = collect();
        $kelasDipilih = null;

        if ($tahunPelajaranId) {
            $kelas = Kelas::query()
                ->with(['tahunPelajaran', 'waliKelas'])
                ->withCount('anggotaKelas')
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->when($cakupanWaliKelas, fn ($query) => $query->whereIn('id', $kelasWaliIds))
                ->orderByDesc('aktif')
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get();

            $kelasId = $request->integer('kelas_id')
                ?: $kelas->firstWhere('aktif', true)?->id
                ?: $kelas->first()?->id;

            $kelasDipilih = $kelasId ? $kelas->firstWhere('id', $kelasId) : null;
        }

        $kataKunciSiswa = $request->input('kata_kunci_siswa');
        $anggotaKelas = collect();
        $siswaTersedia = collect();
        $jumlahSiswaAktif = Siswa::query()
            ->where('aktif', true)
            ->when($cakupanWaliKelas, function ($query) use ($kelasWaliIds) {
                $query->whereHas('anggotaKelas', fn ($query) => $query->whereIn('kelas_id', $kelasWaliIds));
            })
            ->count();
        $jumlahDitempatkanTahunIni = $tahunPelajaranId
            ? AnggotaKelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->when($cakupanWaliKelas, fn ($query) => $query->whereIn('kelas_id', $kelasWaliIds))
                ->distinct('siswa_id')
                ->count('siswa_id')
            : 0;

        if ($kelasDipilih) {
            $anggotaKelas = AnggotaKelas::query()
                ->with('siswa')
                ->where('kelas_id', $kelasDipilih->id)
                ->orderBy('nomor_absen')
                ->orderBy('id')
                ->get();

            $siswaSudahDitempatkan = AnggotaKelas::query()
                ->where('tahun_pelajaran_id', $kelasDipilih->tahun_pelajaran_id)
                ->pluck('siswa_id');

            if ($bisaKelolaKelas && ! $cakupanWaliKelas) {
                $siswaTersedia = Siswa::query()
                    ->where('aktif', true)
                    ->whereNotIn('id', $siswaSudahDitempatkan)
                    ->when($kataKunciSiswa, function ($query, $kataKunciSiswa) {
                        $query->where(function ($query) use ($kataKunciSiswa) {
                            $query->where('nama_lengkap', 'ilike', '%' . $kataKunciSiswa . '%')
                                ->orWhere('nis', 'ilike', '%' . $kataKunciSiswa . '%')
                                ->orWhere('nisn', 'ilike', '%' . $kataKunciSiswa . '%');
                        });
                    })
                    ->orderBy('nama_lengkap')
                    ->limit(150)
                    ->get(['id', 'nama_lengkap', 'nis', 'nisn', 'jenis_kelamin']);
            }
        }

        $kapasitas = $kelasDipilih?->kapasitas;
        $jumlahAnggotaKelas = $anggotaKelas->count();
        $sisaKursi = $kapasitas ? max($kapasitas - $jumlahAnggotaKelas, 0) : null;

        return view('penempatan-siswa.index', [
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'kelas' => $kelas,
            'kelasDipilih' => $kelasDipilih,
            'kataKunciSiswa' => $kataKunciSiswa,
            'anggotaKelas' => $anggotaKelas,
            'siswaTersedia' => $siswaTersedia,
            'jumlahSiswaAktif' => $jumlahSiswaAktif,
            'jumlahDitempatkanTahunIni' => $jumlahDitempatkanTahunIni,
            'jumlahBelumDitempatkanTahunIni' => max($jumlahSiswaAktif - $jumlahDitempatkanTahunIni, 0),
            'jumlahAnggotaKelas' => $jumlahAnggotaKelas,
            'sisaKursi' => $sisaKursi,
            'cakupanWaliKelas' => $cakupanWaliKelas,
        ]);
    }

    public function storeMassal(Request $request)
    {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', Rule::exists('kelas', 'id')],
            'siswa_ids' => ['required', 'array', 'min:1'],
            'siswa_ids.*' => ['integer', Rule::exists('siswa', 'id')->where('aktif', true)],
            'tanggal_masuk' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
        ], [
            'siswa_ids.required' => 'Pilih minimal satu siswa untuk dimasukkan ke kelas.',
            'siswa_ids.min' => 'Pilih minimal satu siswa untuk dimasukkan ke kelas.',
        ]);

        $kelas = Kelas::with('tahunPelajaran')->findOrFail($data['kelas_id']);
        abort_unless($request->user()?->dapatMengaksesKelasSebagaiWali($kelas->id) ?? false, 403);

        $siswaIds = collect($data['siswa_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $sudahDitempatkan = AnggotaKelas::query()
            ->with('kelas')
            ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id)
            ->whereIn('siswa_id', $siswaIds)
            ->get();

        if ($sudahDitempatkan->isNotEmpty()) {
            $daftarKelas = $sudahDitempatkan
                ->map(fn (AnggotaKelas $anggota) => $anggota->kelas?->nama ?: 'kelas lain')
                ->unique()
                ->join(', ');

            return back()
                ->withErrors(['siswa_ids' => 'Ada siswa yang sudah ditempatkan pada tahun pelajaran ini di ' . $daftarKelas . '.'])
                ->withInput();
        }

        $jumlahAnggotaSaatIni = $kelas->anggotaKelas()->count();

        if ($kelas->kapasitas && ($jumlahAnggotaSaatIni + $siswaIds->count()) > $kelas->kapasitas) {
            return back()
                ->withErrors(['siswa_ids' => 'Jumlah siswa yang dipilih melebihi sisa kapasitas kelas.'])
                ->withInput();
        }

        DB::transaction(function () use ($kelas, $siswaIds, $data) {
            foreach ($siswaIds as $siswaId) {
                AnggotaKelas::create([
                    'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
                    'kelas_id' => $kelas->id,
                    'siswa_id' => $siswaId,
                    'nomor_absen' => null,
                    'status_keanggotaan' => 'aktif',
                    'tanggal_masuk' => $data['tanggal_masuk'] ?? $kelas->tahunPelajaran?->tanggal_mulai,
                    'keterangan' => $data['keterangan'] ?? 'Penempatan siswa',
                ]);
            }
        });

        return redirect()
            ->route('penempatan-siswa.index', [
                'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
                'kelas_id' => $kelas->id,
            ])
            ->with('berhasil', $siswaIds->count() . ' siswa berhasil dimasukkan ke ' . $kelas->nama . '.');
    }
}
