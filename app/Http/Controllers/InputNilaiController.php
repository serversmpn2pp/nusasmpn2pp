<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\PublikasiNilaiSiswa;
use App\Services\Nilai\PublikasiNilaiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InputNilaiController extends Controller
{
    public function __construct(private PublikasiNilaiService $publikasiNilai) {}

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

        $komponenDipilih = $this->ambilKomponenDipilih($request, $request->input('komponen_nilai_id'));
        $penilaianPredikat = $komponenDipilih
            ->guruMataPelajaran?->mataPelajaran?->menggunakanPredikat() ?? false;
        $aturan = [
            'komponen_nilai_id' => ['required', 'exists:komponen_nilai,id'],
            'nilai' => ['nullable', 'array'],
            'predikat' => ['nullable', 'array'],
            'catatan' => ['nullable', 'array'],
            'catatan.*' => ['nullable', 'string', 'max:255'],
        ];

        if ($penilaianPredikat) {
            $aturan['predikat.*'] = ['nullable', Rule::in(MataPelajaran::PREDIKAT_NILAI)];
        } else {
            $aturan['nilai.*'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        $data = $request->validate($aturan, [
            'nilai.*.numeric' => 'Nilai harus berupa angka.',
            'nilai.*.min' => 'Nilai minimal 0.',
            'nilai.*.max' => 'Nilai maksimal 100.',
            'predikat.*.in' => 'Predikat harus SB, B, C, atau K.',
        ]);

        $kelasId = $komponenDipilih->guruMataPelajaran?->kelas_id;
        $anggotaKelas = $kelasId ? $this->ambilAnggotaKelas($kelasId) : collect();
        $siswaIds = $anggotaKelas->pluck('siswa_id')->map(fn ($id) => (int) $id);
        $idsDikirim = collect(array_keys($data['nilai'] ?? []))
            ->merge(array_keys($data['predikat'] ?? []))
            ->merge(array_keys($data['catatan'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($idsDikirim->diff($siswaIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'komponen_nilai_id' => 'Ada data siswa yang tidak sesuai dengan kelas komponen nilai ini.',
            ]);
        }

        DB::transaction(function () use ($komponenDipilih, $siswaIds, $data, $penilaianPredikat) {
            foreach ($siswaIds as $siswaId) {
                $nilaiMentah = $data['nilai'][$siswaId] ?? null;
                $predikatMentah = $data['predikat'][$siswaId] ?? null;
                $catatan = trim((string) ($data['catatan'][$siswaId] ?? ''));
                $nilai = $penilaianPredikat || $nilaiMentah === null || $nilaiMentah === ''
                    ? null
                    : round((float) $nilaiMentah, 2);
                $predikat = ! $penilaianPredikat || blank($predikatMentah)
                    ? null
                    : mb_strtoupper(trim((string) $predikatMentah));

                if ($nilai === null && $predikat === null && $catatan === '') {
                    NilaiSiswa::query()
                        ->where('komponen_nilai_id', $komponenDipilih->id)
                        ->where('siswa_id', $siswaId)
                        ->delete();

                    continue;
                }

                NilaiSiswa::updateOrCreate(
                    [
                        'komponen_nilai_id' => $komponenDipilih->id,
                        'siswa_id' => $siswaId,
                    ],
                    [
                        'nilai' => $nilai,
                        'predikat' => $predikat,
                        'catatan' => $catatan ?: null,
                    ]
                );
            }
        });

        $publikasiDibatalkan = $this->publikasiNilai->tandaiDraf(
            (int) $komponenDipilih->guru_mata_pelajaran_id,
            $komponenDipilih->semester,
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
