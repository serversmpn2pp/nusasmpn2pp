<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\TransaksiPoinSiswa;
use App\Services\Pembinaan\PresentasiProgressKasusService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PembinaanPoinAnakController extends Controller
{
    public function __construct(private PresentasiProgressKasusService $presentasiProgress) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(['laporan', 'poin'])],
        ]);
        [$orangTua, $siswa] = $this->orangTuaDanSiswa($request->user());
        $queryAnggotaKelas = $siswa?->anggotaKelas()
            ->with(['kelas:id,nama,tingkat', 'tahunPelajaran:id,nama,aktif,tanggal_mulai'])
            ->where('status_keanggotaan', 'aktif');
        $anggotaKelas = $queryAnggotaKelas
            ? (clone $queryAnggotaKelas)
                ->whereHas('tahunPelajaran', fn ($query) => $query->where('aktif', true))
                ->latest('id')
                ->first()
            : null;
        $anggotaKelas ??= $queryAnggotaKelas?->latest('tahun_pelajaran_id')->latest('id')->first();
        $tahunPelajaran = $anggotaKelas?->tahunPelajaran;

        $queryLaporan = LaporanPembinaanSiswa::query()
            ->with(['kelas:id,nama', 'tahunPelajaran:id,nama'])
            ->where('siswa_id', $siswa?->id ?: 0)
            ->when($tahunPelajaran, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaran->id));
        $queryPoin = TransaksiPoinSiswa::query()
            ->with(['laporanPembinaanSiswa:id,nomor_laporan', 'penguranganPoinSiswa:id,jenis_kegiatan'])
            ->where('siswa_id', $siswa?->id ?: 0)
            ->when($tahunPelajaran, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaran->id));
        $statusDiproses = [
            'diajukan',
            'pemeriksaan_bk',
            'perlu_klarifikasi',
            'dikembalikan_bk',
            'menunggu_pengesahan_wakil',
            'menunggu_persetujuan',
            'disetujui_sebagian',
            'perlu_musyawarah',
        ];
        $ringkasan = [
            'laporan' => (clone $queryLaporan)->count(),
            'diproses' => (clone $queryLaporan)
                ->where(function ($query) use ($statusDiproses) {
                    $query->whereIn('status_verifikasi', $statusDiproses)
                        ->orWhere(function ($query) {
                            $query->where('status_verifikasi', 'tidak_perlu')
                                ->whereNotIn('status', ['selesai', 'dibatalkan']);
                        });
                })
                ->count(),
            'poin_pelanggaran' => (int) (clone $queryPoin)->where('jenis', 'pelanggaran')->sum('poin'),
            'pengurangan' => abs((int) (clone $queryPoin)->where('jenis', 'pengurangan')->sum('poin')),
            'saldo' => (int) (clone $queryPoin)->sum('poin'),
        ];
        $laporan = (clone $queryLaporan)
            ->latest('tanggal_kejadian')
            ->latest('id')
            ->paginate(10, ['*'], 'laporan');
        $presentasiStatus = $laporan->getCollection()
            ->mapWithKeys(fn (LaporanPembinaanSiswa $item) => [
                $item->id => $this->presentasiProgress->status($item),
            ]);
        $riwayatPoin = (clone $queryPoin)
            ->latest('tercatat_pada')
            ->latest('id')
            ->paginate(12, ['*'], 'poin');

        return view('pembinaan-poin-anak.index', [
            'orangTua' => $orangTua,
            'siswa' => $siswa,
            'anggotaKelas' => $anggotaKelas,
            'tahunPelajaran' => $tahunPelajaran,
            'tab' => $data['tab'] ?? 'laporan',
            'ringkasan' => $ringkasan,
            'laporan' => $laporan,
            'presentasiStatus' => $presentasiStatus,
            'riwayatPoin' => $riwayatPoin,
        ]);
    }

    public function show(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        [, $siswa] = $this->orangTuaDanSiswa($request->user());
        abort_unless($siswa && (int) $laporanPembinaanSiswa->siswa_id === (int) $siswa->id, 404);

        $laporanPembinaanSiswa->load([
            'tahunPelajaran:id,nama',
            'kelas:id,nama',
            'kategoriPembinaanSiswa:id,nama',
            'butirPelanggaranLaporan' => fn ($query) => $query->orderBy('id'),
            'riwayatProsesPembinaanSiswa' => fn ($query) => $query
                ->oldest('terjadi_pada')
                ->oldest('id'),
            'tindakLanjutPembinaanSiswa' => fn ($query) => $query
                ->oldest('tanggal_tindak_lanjut')
                ->oldest('waktu_tindak_lanjut')
                ->oldest('id'),
        ]);

        return view('progress-kasus-siswa.show', [
            'siswa' => $siswa,
            'laporan' => $laporanPembinaanSiswa,
            'presentasiStatus' => $this->presentasiProgress->status($laporanPembinaanSiswa),
            'linimasa' => $this->presentasiProgress->linimasaPublik($laporanPembinaanSiswa),
            'judulHalaman' => 'Detail Pembinaan Anak',
            'urlKembali' => route('pembinaan-poin-anak.index'),
            'teksPrivasi' => 'Rincian pemeriksaan internal dikelola oleh sekolah dan tidak ditampilkan pada akun orang tua.',
        ]);
    }

    private function orangTuaDanSiswa(?Pengguna $pengguna): array
    {
        abort_unless($pengguna?->akunOrangTua() || $pengguna?->memilikiPeran('orang_tua'), 403);

        $orangTua = $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
        $siswa = $orangTua?->siswa
            ->firstWhere('id', $orangTua->siswa_acuan_username_id)
            ?: $orangTua?->siswa->first();

        return [$orangTua, $siswa];
    }
}
