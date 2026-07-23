<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\PeringatanDiniSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\AksesRekapPoinSiswaService;
use App\Services\Pembinaan\ProsesPeringatanDiniSiswaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PeringatanDiniSiswaController extends Controller
{
    public function __construct(
        private AksesRekapPoinSiswaService $akses,
        private ProsesPeringatanDiniSiswaService $proses,
    ) {}

    public function index(Request $request)
    {
        $tahunPelajaranId = $this->inputId($request, 'tahun_pelajaran_id')
            ?? TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = $this->inputId($request, 'kelas_id');
        $jenis = $this->nilaiPilihan($request, 'jenis', array_keys(PeringatanDiniSiswa::DAFTAR_JENIS));
        $tingkat = $this->nilaiPilihan($request, 'tingkat', array_keys(PeringatanDiniSiswa::DAFTAR_TINGKAT));
        $status = $this->nilaiPilihan($request, 'status', array_keys(PeringatanDiniSiswa::DAFTAR_STATUS), 'aktif');
        $kataKunci = trim((string) $request->input('kata_kunci', ''));

        $cakupanSiswa = Siswa::query()
            ->when($tahunPelajaranId, fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('status_keanggotaan', 'aktif')))
            ->when($kelasId, fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')
                ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))))
            ->when($kataKunci !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($kataKunci) {
                $query->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                    ->orWhere('nisn', 'ilike', '%'.$kataKunci.'%')
                    ->orWhere('nis', 'ilike', '%'.$kataKunci.'%');
            }));
        $this->akses->terapkanCakupan($cakupanSiswa, $request->user(), $tahunPelajaranId);
        $siswaIds = $cakupanSiswa->pluck('siswa.id');

        $cakupanPeringatan = PeringatanDiniSiswa::query()
            ->whereIn('siswa_id', $siswaIds)
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId));
        $peringatanAktif = (clone $cakupanPeringatan)->where('status', 'aktif');
        $ringkasan = [
            'total_aktif' => (clone $peringatanAktif)->count(),
            'penting' => (clone $peringatanAktif)->where('tingkat', 'penting')->count(),
            'mendekati_sanksi' => (clone $peringatanAktif)->where('jenis', 'mendekati_sanksi')->count(),
            'pola_berulang' => (clone $peringatanAktif)->whereIn('jenis', ['pelanggaran_berulang', 'sering_terlambat'])->count(),
            'sanksi_aktif' => (clone $peringatanAktif)->where('jenis', 'sanksi_belum_selesai')->count(),
        ];

        $daftarPeringatan = (clone $cakupanPeringatan)
            ->with([
                'siswa' => fn ($query) => $query->with([
                    'anggotaKelas' => fn ($query) => $query
                        ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
                        ->where('status_keanggotaan', 'aktif')
                        ->with('kelas:id,nama'),
                    'penugasanGuruWaliSiswa' => fn ($query) => $query
                        ->where('aktif', true)
                        ->with('guruWali:id,nama_lengkap'),
                    'pendampinganSiswa' => fn ($query) => $query
                        ->where('status', 'dalam_proses')
                        ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
                        ->latest('id'),
                ]),
                'sanksiPoinSiswa.aturanSanksiPoin:id,nama,batas_poin',
            ])
            ->when($jenis, fn ($query) => $query->where('jenis', $jenis))
            ->when($tingkat, fn ($query) => $query->where('tingkat', $tingkat))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN tingkat = 'penting' THEN 0 ELSE 1 END")
            ->latest('terakhir_terdeteksi_pada')
            ->paginate(15)
            ->withQueryString();

        $daftarTahunPelajaran = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
        $daftarKelas = $this->daftarKelas($request, $tahunPelajaranId);

        return view('peringatan-dini-siswa.index', compact(
            'daftarPeringatan',
            'daftarTahunPelajaran',
            'daftarKelas',
            'ringkasan',
            'tahunPelajaranId',
            'kelasId',
            'jenis',
            'tingkat',
            'status',
            'kataKunci',
        ));
    }

    public function proses(Request $request)
    {
        $tahunPelajaranId = $this->inputId($request, 'tahun_pelajaran_id')
            ?? TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $hasil = $this->proses->proses($tahunPelajaranId);

        return redirect()->route('peringatan-dini-siswa.index', array_filter([
            'tahun_pelajaran_id' => $tahunPelajaranId,
            'status' => 'aktif',
        ]))->with('berhasil', sprintf(
            'Peringatan diperbarui: %d baru, %d diperbarui, %d selesai, dan %d notifikasi dibuat.',
            $hasil['peringatan_baru'],
            $hasil['peringatan_diperbarui'],
            $hasil['peringatan_diselesaikan'],
            $hasil['notifikasi_terkirim'],
        ));
    }

    private function daftarKelas(Request $request, ?int $tahunPelajaranId)
    {
        $query = Kelas::query()
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId));
        $pengguna = $request->user();

        if (! $this->akses->aksesLuas($pengguna)) {
            $kelasWaliIds = $pengguna->kelasWaliIds();
            $siswaWaliIds = $pengguna->siswaWaliIds();

            if ($kelasWaliIds === [] && $siswaWaliIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($query) use ($kelasWaliIds, $siswaWaliIds) {
                    if ($kelasWaliIds !== []) {
                        $query->whereIn('id', $kelasWaliIds);
                    }

                    if ($siswaWaliIds !== []) {
                        $metode = $kelasWaliIds !== [] ? 'orWhereHas' : 'whereHas';
                        $query->{$metode}('anggotaKelas', fn ($query) => $query
                            ->whereIn('siswa_id', $siswaWaliIds)
                            ->where('status_keanggotaan', 'aktif'));
                    }
                });
            }
        }

        return $query->orderBy('tingkat')->orderBy('nama')->get();
    }

    private function inputId(Request $request, string $field): ?int
    {
        $value = $request->input($field);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function nilaiPilihan(Request $request, string $field, array $pilihan, string $bawaan = ''): string
    {
        $nilai = (string) $request->input($field, $bawaan);

        return in_array($nilai, $pilihan, true) ? $nilai : $bawaan;
    }
}
