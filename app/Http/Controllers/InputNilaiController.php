<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\KomponenNilai;
use App\Models\NilaiSiswa;
use App\Models\PublikasiNilaiSiswa;
use App\Services\Nilai\InputNilaiService;
use Illuminate\Http\Request;

class InputNilaiController extends Controller
{
    public function __construct(private InputNilaiService $inputNilai) {}

    public function index(Request $request)
    {
        $komponenNilaiId = old('komponen_nilai_id', $request->input('komponen_nilai_id'));
        $daftarKomponenNilai = $this->ambilDaftarKomponenNilai($request);
        $komponenDipilih = null;
        $anggotaKelas = collect();
        $nilaiTersimpan = collect();
        $penilaianPredikat = false;
        $publikasiNilai = null;
        $jumlahKomponenPublikasi = 0;
        $jumlahNilaiPublikasi = 0;
        $targetNilaiPublikasi = 0;

        if ($komponenNilaiId) {
            $komponenDipilih = $this->ambilKomponenDipilih($request, $komponenNilaiId);
            $penilaianPredikat = $komponenDipilih
                ->guruMataPelajaran?->mataPelajaran?->menggunakanPredikat() ?? false;
            $kelasId = $komponenDipilih->guruMataPelajaran?->kelas_id;

            if ($kelasId) {
                $anggotaKelas = $this->ambilAnggotaKelas($kelasId);
                $siswaIds = $anggotaKelas->pluck('siswa_id');
                $nilaiTersimpan = NilaiSiswa::query()
                    ->where('komponen_nilai_id', $komponenDipilih->id)
                    ->whereIn('siswa_id', $siswaIds)
                    ->get()
                    ->keyBy('siswa_id');
            }

            $guruMataPelajaranId = $komponenDipilih->guru_mata_pelajaran_id;
            $semester = $komponenDipilih->semester;
            $publikasiNilai = PublikasiNilaiSiswa::query()
                ->where('guru_mata_pelajaran_id', $guruMataPelajaranId)
                ->where('semester', $semester)
                ->first();
            $komponenSemesterIds = KomponenNilai::query()
                ->where('guru_mata_pelajaran_id', $guruMataPelajaranId)
                ->where('semester', $semester)
                ->where('aktif', true)
                ->pluck('id');
            $jumlahKomponenPublikasi = $komponenSemesterIds->count();
            $jumlahNilaiPublikasi = NilaiSiswa::query()
                ->whereIn('komponen_nilai_id', $komponenSemesterIds)
                ->whereIn('siswa_id', $anggotaKelas->pluck('siswa_id'))
                ->where(function ($query) {
                    $query->whereNotNull('nilai')->orWhereNotNull('predikat');
                })
                ->count();
            $targetNilaiPublikasi = $jumlahKomponenPublikasi * $anggotaKelas->count();
        }

        $jumlahSiswa = $anggotaKelas->count();
        $jumlahTerisi = $nilaiTersimpan
            ->filter(fn (NilaiSiswa $nilaiSiswa) => $penilaianPredikat
                ? filled($nilaiSiswa->predikat)
                : $nilaiSiswa->nilai !== null)
            ->count();
        $rataRata = $penilaianPredikat
            ? null
            : $nilaiTersimpan
                ->filter(fn (NilaiSiswa $nilaiSiswa) => $nilaiSiswa->nilai !== null)
                ->avg('nilai');

        return view('input-nilai.index', compact(
            'daftarKomponenNilai',
            'komponenNilaiId',
            'komponenDipilih',
            'anggotaKelas',
            'nilaiTersimpan',
            'jumlahSiswa',
            'jumlahTerisi',
            'rataRata',
            'penilaianPredikat',
            'publikasiNilai',
            'jumlahKomponenPublikasi',
            'jumlahNilaiPublikasi',
            'targetNilaiPublikasi',
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'komponen_nilai_id' => ['required', 'exists:komponen_nilai,id'],
        ]);

        $komponenDipilih = $this->inputNilai->ambilKomponenDalamCakupan(
            $request->user(),
            $request->input('komponen_nilai_id'),
        );
        $data = $request->validate(
            $this->inputNilai->aturanValidasi($komponenDipilih),
            $this->inputNilai->pesanValidasi(),
        );
        $publikasiDibatalkan = $this->inputNilai->simpan(
            $request->user(),
            $komponenDipilih,
            $data,
        );

        return redirect()
            ->route('input-nilai.index', ['komponen_nilai_id' => $komponenDipilih->id])
            ->with(
                'berhasil',
                $publikasiDibatalkan
                    ? 'Nilai siswa berhasil disimpan. Karena ada perubahan, nilai kembali menjadi draf dan perlu dipublikasikan ulang.'
                    : 'Nilai siswa berhasil disimpan sebagai draf.',
            );
    }

    private function ambilDaftarKomponenNilai(Request $request)
    {
        return $this->queryKomponenDalamCakupan($request)
            ->with([
                'guruMataPelajaran.tahunPelajaran',
                'guruMataPelajaran.kelas',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->where('aktif', true)
            ->whereHas('guruMataPelajaran', function ($query) {
                $query->where('aktif', true);
            })
            ->orderBy('semester')
            ->orderBy('jenis_komponen')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function ambilKomponenDipilih(Request $request, int|string $komponenNilaiId): KomponenNilai
    {
        return $this->queryKomponenDalamCakupan($request)
            ->with([
                'guruMataPelajaran.tahunPelajaran',
                'guruMataPelajaran.kelas',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->where('aktif', true)
            ->whereKey($komponenNilaiId)
            ->firstOrFail();
    }

    private function queryKomponenDalamCakupan(Request $request)
    {
        $query = KomponenNilai::query();
        $pengguna = $request->user();

        if (
            $pengguna
            && ! $pengguna->administrator()
            && $pengguna->memilikiPeran('guru_mapel')
            && ! $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kurikulum'])
        ) {
            $query->whereHas('guruMataPelajaran', function ($query) use ($pengguna) {
                $query->where('pegawai_id', $pengguna->pegawai_id ?: 0);
            });
        }

        return $query;
    }

    private function ambilAnggotaKelas(int $kelasId)
    {
        return AnggotaKelas::query()
            ->with('siswa')
            ->where('kelas_id', $kelasId)
            ->where('status_keanggotaan', 'aktif')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->get();
    }
}
