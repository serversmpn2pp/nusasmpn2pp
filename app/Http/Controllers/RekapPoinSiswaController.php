<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\Kelas;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Pembinaan\AksesLaporanPembinaanService;
use App\Services\Pembinaan\AksesRekapPoinSiswaService;
use App\Services\Pembinaan\MonitoringPoinSiswaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RekapPoinSiswaController extends Controller
{
    public function __construct(
        private AksesRekapPoinSiswaService $akses,
        private MonitoringPoinSiswaService $monitoring,
    ) {}

    public function index(Request $request)
    {
        $konteksGuruWali = $request->routeIs('rekap-poin-siswa-wali.*');
        $tahunPelajaranId = $this->inputId($request, 'tahun_pelajaran_id')
            ?? TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = $this->inputId($request, 'kelas_id');
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $statusPerhatian = $this->statusPerhatian($request);
        $pengguna = $request->user();
        $aturanSanksi = $this->monitoring->aturanAktif();

        $cakupanSiswa = Siswa::query()
            ->where('aktif', true)
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

        $this->akses->terapkanCakupan(
            $cakupanSiswa,
            $pengguna,
            $tahunPelajaranId,
            $konteksGuruWali ? 'guru_wali' : null,
        );
        $siswaCakupanIds = (clone $cakupanSiswa)->pluck('siswa.id');
        $ringkasan = $this->monitoring->ringkasan($siswaCakupanIds, $tahunPelajaranId, $aturanSanksi);
        $ringkasanKelas = $this->monitoring->ringkasanKelas($siswaCakupanIds, $tahunPelajaranId);

        $query = (clone $cakupanSiswa)
            ->with([
                'anggotaKelas' => fn ($query) => $query
                    ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
                    ->where('status_keanggotaan', 'aktif')
                    ->with('kelas:id,nama'),
                'penugasanGuruWaliSiswa' => fn ($query) => $query
                    ->where('aktif', true)
                    ->with('guruWali:id,nama_lengkap'),
            ])
            ->withSum([
                'transaksiPoinSiswa as total_poin' => fn ($query) => $query
                    ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId)),
            ], 'poin')
            ->withCount([
                'laporanPembinaanSiswa as laporan_menunggu_count' => fn ($query) => $query
                    ->where('jenis_laporan', 'pelanggaran')
                    ->whereNotIn('status_verifikasi', AksesLaporanPembinaanService::STATUS_FINAL)
                    ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId)),
                'sanksiPoinSiswa as sanksi_aktif_count' => fn ($query) => $query
                    ->whereNotIn('status', SanksiPoinSiswa::STATUS_FINAL)
                    ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId)),
            ]);

        $this->terapkanStatusPerhatian($query, $statusPerhatian, $tahunPelajaranId, $siswaCakupanIds, $aturanSanksi);

        $daftarSiswa = $query->orderBy('nama_lengkap')->paginate(15)->withQueryString();
        $daftarSiswa->getCollection()->transform(function (Siswa $siswa) use ($aturanSanksi) {
            $siswa->setAttribute('indikator_monitoring', $this->monitoring->indikator(
                max(0, (int) $siswa->total_poin),
                (int) $siswa->laporan_menunggu_count,
                (int) $siswa->sanksi_aktif_count,
                $aturanSanksi,
            ));

            return $siswa;
        });

        $daftarTahunPelajaran = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
        $daftarKelas = $this->daftarKelas($pengguna, $tahunPelajaranId, $konteksGuruWali);

        return view('rekap-poin-siswa.index', compact(
            'daftarSiswa',
            'daftarTahunPelajaran',
            'daftarKelas',
            'ringkasan',
            'ringkasanKelas',
            'tahunPelajaranId',
            'kelasId',
            'kataKunci',
            'statusPerhatian',
            'konteksGuruWali',
        ));
    }

    public function show(Request $request, Siswa $siswa)
    {
        $konteksGuruWali = $request->routeIs('rekap-poin-siswa-wali.*');
        $tahunPelajaranId = $this->inputId($request, 'tahun_pelajaran_id')
            ?? TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $tahunPelajaran = $tahunPelajaranId ? TahunPelajaran::find($tahunPelajaranId) : null;
        abort_unless($this->akses->bolehLihat(
            $request->user(),
            $siswa,
            $tahunPelajaranId,
            $konteksGuruWali ? 'guru_wali' : null,
        ), 403);

        $aturanSanksi = $this->monitoring->aturanAktif();
        $anggotaKelas = $siswa->anggotaKelas()
            ->with('kelas:id,nama,tingkat,wali_kelas_id')
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->first();
        $penugasanGuruWali = $siswa->penugasanGuruWaliSiswa()
            ->with('guruWali:id,nama_lengkap')
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();

        $transaksiPoin = $siswa->transaksiPoinSiswa()
            ->with([
                'laporanPembinaanSiswa:id,nomor_laporan',
                'penguranganPoinSiswa:id,jenis_kegiatan',
            ])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->latest('tercatat_pada')
            ->get();
        $totalPoin = max(0, (int) $transaksiPoin->sum('poin'));

        $laporanPembinaan = $siswa->laporanPembinaanSiswa()
            ->with([
                'kategoriPembinaanSiswa:id,nama',
                'kelas:id,nama',
                'butirPelanggaranLaporan:id,laporan_pembinaan_siswa_id,kode_pelanggaran,nama_pelanggaran,poin',
            ])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->latest('tanggal_kejadian')
            ->latest('id')
            ->get();
        $laporanMenunggu = $laporanPembinaan
            ->where('jenis_laporan', 'pelanggaran')
            ->whereNotIn('status_verifikasi', AksesLaporanPembinaanService::STATUS_FINAL);
        $poinDalamProses = (int) $laporanMenunggu->sum('total_poin');

        $daftarSanksi = $siswa->sanksiPoinSiswa()
            ->with(['aturanSanksiPoin:id,batas_poin,nama', 'petugasPegawai:id,nama_lengkap'])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->latest('terpicu_pada')
            ->get();
        $jumlahSanksiAktif = $daftarSanksi->whereNotIn('status', SanksiPoinSiswa::STATUS_FINAL)->count();
        $peringatanAktif = $siswa->peringatanDiniSiswa()
            ->with('sanksiPoinSiswa.aturanSanksiPoin:id,nama,batas_poin')
            ->where('status', 'aktif')
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->orderByRaw("CASE WHEN tingkat = 'penting' THEN 0 ELSE 1 END")
            ->latest('terakhir_terdeteksi_pada')
            ->get();
        $daftarPendampingan = $siswa->pendampinganSiswa()
            ->with([
                'petugasPegawai:id,nama_lengkap',
                'peringatanDiniSiswa:id,jenis,judul',
            ])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->orderByRaw("CASE WHEN status = 'dalam_proses' THEN 0 ELSE 1 END")
            ->latest('tanggal_tindak_lanjut')
            ->latest('id')
            ->get();
        $pendampinganAktif = $daftarPendampingan->firstWhere('status', 'dalam_proses');

        $penguranganPoin = $siswa->penguranganPoinSiswa()
            ->with('disetujuiOlehPegawai:id,nama_lengkap')
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->latest('tanggal_kegiatan')
            ->get();

        $queryKeterlambatan = AbsensiSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->where('menit_terlambat', '>', 0)
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId));
        $rekapKeterlambatan = [
            'jumlah' => (clone $queryKeterlambatan)->count(),
            'total_menit' => (int) (clone $queryKeterlambatan)->sum('menit_terlambat'),
        ];
        $daftarKeterlambatan = $queryKeterlambatan
            ->with('kelas:id,nama')
            ->latest('tanggal')
            ->limit(12)
            ->get();

        $indikator = $this->monitoring->indikator(
            $totalPoin,
            $laporanMenunggu->count(),
            $jumlahSanksiAktif,
            $aturanSanksi,
        );
        $perkembanganBulanan = $this->monitoring->perkembanganBulanan($siswa->id, $tahunPelajaran);
        $maksSaldoBulanan = max(1, (int) $perkembanganBulanan->max('saldo'));
        $daftarTahunPelajaran = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();

        return view('rekap-poin-siswa.show', compact(
            'siswa',
            'tahunPelajaran',
            'tahunPelajaranId',
            'daftarTahunPelajaran',
            'anggotaKelas',
            'penugasanGuruWali',
            'totalPoin',
            'transaksiPoin',
            'laporanPembinaan',
            'laporanMenunggu',
            'poinDalamProses',
            'daftarSanksi',
            'jumlahSanksiAktif',
            'peringatanAktif',
            'daftarPendampingan',
            'pendampinganAktif',
            'penguranganPoin',
            'rekapKeterlambatan',
            'daftarKeterlambatan',
            'indikator',
            'perkembanganBulanan',
            'maksSaldoBulanan',
            'konteksGuruWali',
        ));
    }

    private function daftarKelas($pengguna, ?int $tahunPelajaranId, bool $konteksGuruWali = false)
    {
        $query = Kelas::query()
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId));

        if ($konteksGuruWali) {
            $siswaWaliIds = $pengguna->siswaWaliIds();

            return $query
                ->when(
                    $siswaWaliIds === [],
                    fn ($query) => $query->whereRaw('1 = 0'),
                    fn ($query) => $query->whereHas('anggotaKelas', fn ($query) => $query
                        ->whereIn('siswa_id', $siswaWaliIds)
                        ->where('status_keanggotaan', 'aktif')),
                )
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get();
        }

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

    private function terapkanStatusPerhatian(
        Builder $query,
        string $status,
        ?int $tahunPelajaranId,
        $siswaCakupanIds,
        $aturanSanksi,
    ): void {
        if ($status === 'berpoin') {
            $query->whereIn('siswa.id', $this->subquerySiswaBerpoin($tahunPelajaranId));
        } elseif ($status === 'menunggu_verifikasi') {
            $query->whereHas('laporanPembinaanSiswa', fn ($query) => $query
                ->where('jenis_laporan', 'pelanggaran')
                ->whereNotIn('status_verifikasi', AksesLaporanPembinaanService::STATUS_FINAL)
                ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId)));
        } elseif ($status === 'sanksi_aktif') {
            $query->whereHas('sanksiPoinSiswa', fn ($query) => $query
                ->whereNotIn('status', SanksiPoinSiswa::STATUS_FINAL)
                ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId)));
        } elseif ($status === 'mendekati_sanksi') {
            $saldo = $this->monitoring->saldoPoinPerSiswa($siswaCakupanIds, $tahunPelajaranId);
            $ids = $saldo->filter(fn ($poin) => $this->monitoring->indikator(
                (int) $poin,
                0,
                0,
                $aturanSanksi,
            )['kode'] === 'mendekati_sanksi')->keys();
            $query->whereIn('siswa.id', $ids);
        }
    }

    private function subquerySiswaBerpoin(?int $tahunPelajaranId)
    {
        return TransaksiPoinSiswa::query()
            ->select('siswa_id')
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->groupBy('siswa_id')
            ->havingRaw('SUM(poin) > 0');
    }

    private function statusPerhatian(Request $request): string
    {
        $status = (string) $request->input('status_perhatian', '');

        return in_array($status, [
            'berpoin',
            'mendekati_sanksi',
            'menunggu_verifikasi',
            'sanksi_aktif',
        ], true) ? $status : '';
    }

    private function inputId(Request $request, string $field): ?int
    {
        $value = $request->input($field);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
