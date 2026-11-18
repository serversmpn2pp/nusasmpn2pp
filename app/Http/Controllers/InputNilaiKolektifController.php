<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\NilaiSiswa;
use App\Models\TahunPelajaran;
use App\Services\Nilai\InputNilaiKolektifService;
use App\Services\Nilai\InputNilaiService;
use App\Services\Nilai\RekapNilaiRaporService;
use App\Support\PenulisExcelNilaiRapor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InputNilaiKolektifController extends Controller
{
    public function __construct(
        private readonly InputNilaiService $inputNilai,
        private readonly InputNilaiKolektifService $kolektif,
    ) {}

    public function index(Request $request)
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'min:1'],
            'kelas_id' => ['nullable', 'integer', 'min:1'],
            'guru_mata_pelajaran_id' => ['nullable', 'integer', 'min:1'],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'komponen' => ['nullable', 'array', 'list', 'max:'.InputNilaiKolektifService::MAKS_KOMPONEN],
            'komponen.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $cakupan = $this->inputNilai->queryGuruMataPelajaranDalamCakupan($request->user())->where('aktif', true);
        $daftarTahun = TahunPelajaran::query()->whereIn('id', (clone $cakupan)->select('tahun_pelajaran_id'))
            ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $tahunId = $daftarTahun->firstWhere('id', $filter['tahun_pelajaran_id'] ?? null)?->id ?? $daftarTahun->first()?->id;
        $semester = $filter['semester'] ?? (now()->month >= 7 ? 'ganjil' : 'genap');
        $cakupan->where('tahun_pelajaran_id', $tahunId);
        $daftarKelas = Kelas::query()->whereIn('id', (clone $cakupan)->select('kelas_id'))
            ->orderBy('tingkat')->orderBy('nama')->orderBy('id')->get();
        $kelasId = $daftarKelas->firstWhere('id', $filter['kelas_id'] ?? null)?->id;
        $daftarPenugasan = $kelasId
            ? $cakupan->where('kelas_id', $kelasId)->with(['kelas', 'mataPelajaran', 'pegawai'])->get()->sortBy('mataPelajaran.nama')
            : collect();
        $penugasan = $daftarPenugasan->firstWhere('id', $filter['guru_mata_pelajaran_id'] ?? null);

        $daftarKomponen = $penugasan
            ? $penugasan->komponenNilai()->where('aktif', true)->where('semester', $semester)
                ->withExists('kelasUjianCbt')->orderBy('jenis_komponen')->orderBy('urutan')->orderBy('nama')->orderBy('id')->get()
            : collect();
        if ($penugasan) {
            $daftarKomponen->each(fn ($item) => $item->setRelation('guruMataPelajaran', $penugasan));
        }
        $alasanTerkunci = $daftarKomponen->mapWithKeys(fn ($item) => [$item->id => $this->kolektif->alasanTerkunci($item)]);
        $komponenDipilih = $daftarKomponen->whereIn('id', $filter['komponen'] ?? [])
            ->reject(fn ($item) => $alasanTerkunci->get($item->id))->values();

        $anggota = $komponenDipilih->isEmpty() ? collect() : $this->inputNilai->ambilAnggotaKelas($kelasId)
            ->where('tahun_pelajaran_id', $tahunId)->unique('siswa_id');
        $nilaiSiswa = $anggota->isEmpty() ? collect() : NilaiSiswa::query()
            ->whereIn('komponen_nilai_id', $komponenDipilih->modelKeys())->whereIn('siswa_id', $anggota->pluck('siswa_id'))
            ->get()->groupBy('siswa_id')->map(fn ($nilai) => $nilai->keyBy('komponen_nilai_id'));
        $predikat = $penugasan?->mataPelajaran?->menggunakanPredikat() ?? false;
        $dipublikasikan = $penugasan?->publikasiNilaiSiswa()->where('semester', $semester)->where('dipublikasikan', true)->exists() ?? false;

        return view('input-nilai.kolektif', compact(
            'daftarTahun', 'tahunId', 'semester', 'daftarKelas', 'kelasId', 'daftarPenugasan', 'penugasan',
            'daftarKomponen', 'alasanTerkunci', 'komponenDipilih', 'anggota', 'nilaiSiswa', 'predikat', 'dipublikasikan',
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'guru_mata_pelajaran_id' => ['required', 'integer', 'min:1'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'perubahan' => ['required', 'array', 'list', 'min:1', 'max:5000'],
            'perubahan.*' => ['required', 'array:komponen_nilai_id,siswa_id,nilai,nilai_awal'],
            'perubahan.*.komponen_nilai_id' => ['required', 'integer', 'min:1'],
            'perubahan.*.siswa_id' => ['required', 'integer', 'min:1'],
            'perubahan.*.nilai' => ['present', 'nullable', 'string', 'max:16'],
            'perubahan.*.nilai_awal' => ['present', 'nullable', 'string', 'max:16'],
        ]);

        $hasil = $this->kolektif->simpan(
            $request->user(),
            (int) $data['guru_mata_pelajaran_id'],
            $data['semester'],
            $data['perubahan'],
        );
        $pesan = $hasil['jumlah'] === 0 ? 'Tidak ada nilai yang berubah.' : "{$hasil['jumlah']} nilai disimpan sebagai draf.";
        if ($hasil['kembali_draf']) {
            $pesan .= ' Nilai mapel ini perlu dipublikasikan ulang.';
        }

        return response()->json([...$hasil, 'message' => $pesan]);
    }

    public function export(Request $request, RekapNilaiRaporService $rekap, PenulisExcelNilaiRapor $penulis)
    {
        $data = $request->validate([
            'guru_mata_pelajaran_id' => ['required', 'integer', 'min:1'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
        ]);
        $penugasan = $this->inputNilai->queryGuruMataPelajaranDalamCakupan($request->user())
            ->where('aktif', true)->with(['kelas', 'mataPelajaran', 'tahunPelajaran'])
            ->findOrFail($data['guru_mata_pelajaran_id']);
        $laporan = $rekap->untukPenugasan($penugasan, $data['semester']);
        abort_if($laporan['komponenNilai']->isEmpty(), 404, 'Belum ada komponen nilai aktif yang dapat diekspor.');

        $nama = str(implode(' ', [
            'Nilai', $penugasan->mataPelajaran?->nama, $penugasan->kelas?->nama, $data['semester'], $penugasan->tahunPelajaran?->nama,
        ]))->slug()->limit(140, '')->toString().'.xlsx';
        $berkas = $penulis->buat($laporan);

        return response()->streamDownload(static function () use ($berkas) {
            echo $berkas;
        }, $nama, [
            'Content-Type' => PenulisExcelNilaiRapor::MIME,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
