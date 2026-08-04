<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SiswaWaliSayaController extends Controller
{
    public function index(Request $request)
    {
        $pegawaiId = $this->pegawaiId($request);
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();
        $tahunPelajaranId = $tahunPelajaran?->id;
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $tingkat = $this->tingkat($request);
        $kelasId = $this->inputId($request, 'kelas_id');

        $cakupanSiswa = Siswa::query()
            ->where('aktif', true)
            ->whereHas('penugasanGuruWaliSiswa', fn (Builder $query) => $query
                ->where('guru_wali_pegawai_id', $pegawaiId)
                ->where('aktif', true));

        $daftarKelas = Kelas::query()
            ->select(['id', 'nama', 'tingkat', 'tahun_pelajaran_id'])
            ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('status_keanggotaan', 'aktif')
                ->whereHas('siswa.penugasanGuruWaliSiswa', fn (Builder $query) => $query
                    ->where('guru_wali_pegawai_id', $pegawaiId)
                    ->where('aktif', true)))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();

        if ($kelasId && ! $daftarKelas->contains('id', $kelasId)) {
            $kelasId = null;
        }

        $saldoPoin = TransaksiPoinSiswa::query()
            ->whereIn('siswa_id', (clone $cakupanSiswa)->select('siswa.id'))
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->selectRaw('siswa_id, SUM(poin) AS total_poin')
            ->groupBy('siswa_id')
            ->pluck('total_poin', 'siswa_id');

        $ringkasan = [
            'siswa' => (clone $cakupanSiswa)->count(),
            'kelas' => $daftarKelas->count(),
            'laki_laki' => (clone $cakupanSiswa)->where('jenis_kelamin', 'L')->count(),
            'perempuan' => (clone $cakupanSiswa)->where('jenis_kelamin', 'P')->count(),
            'memiliki_poin' => $saldoPoin->filter(fn ($poin) => (int) $poin > 0)->count(),
        ];

        $daftarSiswa = (clone $cakupanSiswa)
            ->select([
                'id',
                'nama_lengkap',
                'nis',
                'nisn',
                'foto',
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'nama_ayah',
                'nama_ibu',
                'aktif',
            ])
            ->with([
                'anggotaKelas' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
                    ->with([
                        'kelas:id,nama,tingkat,tahun_pelajaran_id',
                        'tahunPelajaran:id,nama,aktif',
                    ])
                    ->orderByDesc('tahun_pelajaran_id'),
                'penugasanGuruWaliSiswa' => fn ($query) => $query
                    ->where('guru_wali_pegawai_id', $pegawaiId)
                    ->where('aktif', true)
                    ->latest('tanggal_mulai'),
            ])
            ->withSum([
                'transaksiPoinSiswa as total_poin' => fn ($query) => $query
                    ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId)),
            ], 'poin')
            ->withCount([
                'laporanPembinaanSiswa as jumlah_laporan' => fn ($query) => $query
                    ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId)),
            ])
            ->when($kataKunci !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($kataKunci) {
                $query->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                    ->orWhere('nis', 'ilike', '%'.$kataKunci.'%')
                    ->orWhere('nisn', 'ilike', '%'.$kataKunci.'%');
            }))
            ->when($kelasId, fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')
                ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))))
            ->when($tingkat, fn (Builder $query) => $query->whereHas('anggotaKelas.kelas', fn (Builder $query) => $query
                ->where('tingkat', $tingkat)
                ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))))
            ->orderBy('nama_lengkap')
            ->paginate(15)
            ->withQueryString();

        return view('siswa-wali-saya.index', compact(
            'daftarSiswa',
            'daftarKelas',
            'ringkasan',
            'tahunPelajaran',
            'kataKunci',
            'tingkat',
            'kelasId',
        ));
    }

    public function show(Request $request, Siswa $siswa)
    {
        $pegawaiId = $this->pegawaiId($request);
        $penugasan = PenugasanGuruWaliSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->where('guru_wali_pegawai_id', $pegawaiId)
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();

        abort_unless($penugasan, 403);

        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();
        $tahunPelajaranId = $tahunPelajaran?->id;
        $anggotaKelas = $siswa->anggotaKelas()
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->with(['kelas:id,nama,tingkat,tahun_pelajaran_id', 'tahunPelajaran:id,nama,aktif'])
            ->latest('tahun_pelajaran_id')
            ->first();
        $totalPoin = max(0, (int) $siswa->transaksiPoinSiswa()
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->sum('poin'));
        $jumlahLaporan = $siswa->laporanPembinaanSiswa()
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->count();
        $laporanTerbaru = $siswa->laporanPembinaanSiswa()
            ->with(['kategoriPembinaanSiswa:id,nama', 'kelas:id,nama'])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->latest('tanggal_kejadian')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('siswa-wali-saya.show', compact(
            'siswa',
            'penugasan',
            'tahunPelajaran',
            'anggotaKelas',
            'totalPoin',
            'jumlahLaporan',
            'laporanTerbaru',
        ));
    }

    private function pegawaiId(Request $request): int
    {
        $pegawaiId = (int) ($request->user()?->pegawai_id ?? 0);
        abort_unless($pegawaiId > 0, 403);

        return $pegawaiId;
    }

    private function inputId(Request $request, string $field): ?int
    {
        $value = $request->input($field);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function tingkat(Request $request): ?int
    {
        $tingkat = $this->inputId($request, 'tingkat');

        return in_array($tingkat, [7, 8, 9], true) ? $tingkat : null;
    }
}
