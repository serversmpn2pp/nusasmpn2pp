<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PengaturanAbsensi;
use App\Models\TahunPelajaran;
use App\Services\Absensi\KoreksiPresensiSiswaService;
use App\Services\Pembinaan\ProsesPoinKeterlambatanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapAbsensiHarianController extends Controller
{
    public function __construct(
        private ProsesPoinKeterlambatanService $prosesPoinKeterlambatan,
        private KoreksiPresensiSiswaService $koreksiPresensi,
    ) {}

    public function index(Request $request)
    {
        $pengguna = $request->user();
        $cakupanWaliKelas = $pengguna?->membatasiCakupanWaliKelas() ?? false;
        $kelasWaliIds = $cakupanWaliKelas ? $pengguna->kelasWaliIds() : [];

        $data = $request->validate([
            'tanggal' => ['nullable', 'date'],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);

        $tanggal = Carbon::parse($data['tanggal'] ?? now())->toDateString();
        $koreksiHariIniTerbatas = $this->koreksiHariIniTerbatas($request);
        $this->pastikanTanggalKoreksiDiizinkan($request, $tanggal);
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->when($cakupanWaliKelas, function ($query) use ($kelasWaliIds) {
                $query->whereHas('kelas', fn ($query) => $query->whereIn('id', $kelasWaliIds));
            })
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get();

        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $daftarTahunPelajaran);
        $daftarKelas = $tahunPelajaranId
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('aktif', true)
                ->when($cakupanWaliKelas, fn ($query) => $query->whereIn('id', $kelasWaliIds))
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();

        $kelasId = $this->ambilKelasId($data['kelas_id'] ?? null, $daftarKelas, $cakupanWaliKelas);
        $rekapAbsensi = $tahunPelajaranId
            ? $this->ambilRekapAbsensi($tanggal, $tahunPelajaranId, $kelasId, $cakupanWaliKelas ? $kelasWaliIds : null)
            : collect();
        $ringkasan = $this->hitungRingkasan($rekapAbsensi);
        $labelCakupan = $this->labelCakupan($kelasId, $daftarKelas, $cakupanWaliKelas);
        $bolehSalinRangkumanWhatsapp = $cakupanWaliKelas || filled($kelasId);
        $rangkumanWhatsapp = $bolehSalinRangkumanWhatsapp
            ? $this->buatRangkumanWhatsapp($tanggal, $labelCakupan, $ringkasan, $rekapAbsensi)
            : '';

        return view('rekap-absensi-harian.index', [
            'tanggal' => $tanggal,
            'tahunPelajaranId' => $tahunPelajaranId,
            'kelasId' => $kelasId,
            'daftarTahunPelajaran' => $daftarTahunPelajaran,
            'daftarKelas' => $daftarKelas,
            'rekapAbsensi' => $rekapAbsensi,
            'ringkasan' => $ringkasan,
            'cakupanWaliKelas' => $cakupanWaliKelas,
            'labelCakupan' => $labelCakupan,
            'rangkumanWhatsapp' => $rangkumanWhatsapp,
            'bolehSalinRangkumanWhatsapp' => $bolehSalinRangkumanWhatsapp,
            'koreksiHariIniTerbatas' => $koreksiHariIniTerbatas,
        ]);
    }

    public function editKoreksi(Request $request, AnggotaKelas $anggotaKelas)
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        $tanggal = Carbon::parse($data['tanggal'] ?? now())->toDateString();
        $this->pastikanTanggalKoreksiDiizinkan($request, $tanggal);
        $anggotaKelas->load(['tahunPelajaran', 'kelas', 'siswa']);
        $this->pastikanBolehAksesAnggotaKelas($request, $anggotaKelas);

        $absensi = $this->ambilAbsensi($tanggal, $anggotaKelas);
        $this->pastikanCatatanScanTidakDiubah($request, $absensi);
        $pengaturanAbsensi = $this->ambilPengaturanAbsensi($tanggal);
        $koreksiHariIniTerbatas = $this->koreksiHariIniTerbatas($request);

        return view('rekap-absensi-harian.koreksi', compact(
            'tanggal',
            'anggotaKelas',
            'absensi',
            'pengaturanAbsensi',
            'koreksiHariIniTerbatas',
        ));
    }

    public function updateKoreksi(Request $request, AnggotaKelas $anggotaKelas)
    {
        $koreksiHariIniTerbatas = $this->koreksiHariIniTerbatas($request);
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'status_kehadiran' => ['required', Rule::in(['hadir', 'izin', 'sakit', 'alfa'])],
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'catatan' => [$koreksiHariIniTerbatas ? 'required' : 'nullable', 'string', 'max:2000'],
        ], [
            'catatan.required' => 'Catatan koreksi wajib diisi oleh Guru PL.',
        ]);

        $tanggal = Carbon::parse($data['tanggal'])->toDateString();
        $this->koreksiPresensi->koreksi($request->user(), $anggotaKelas, $data);

        return redirect()
            ->route('rekap-absensi-harian.index', [
                'tanggal' => $tanggal,
                'tahun_pelajaran_id' => $anggotaKelas->tahun_pelajaran_id,
                'kelas_id' => $anggotaKelas->kelas_id,
            ])
            ->with('berhasil', 'Koreksi presensi berhasil disimpan.');
    }

    public function prosesPoinKeterlambatan(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);

        $hasil = $this->prosesPoinKeterlambatan->prosesTanggal(
            $data['tanggal'],
            (int) $data['tahun_pelajaran_id'],
            filled($data['kelas_id'] ?? null) ? (int) $data['kelas_id'] : null,
            $request->user()?->id,
            true,
        );

        $pesan = sprintf(
            'Sinkronisasi selesai: %d laporan baru, %d diperbarui, %d dibatalkan, dan %d tanpa perubahan.',
            $hasil['dibuat'],
            $hasil['diperbarui'],
            $hasil['dibatalkan'],
            $hasil['diabaikan'],
        );

        return redirect()->route('rekap-absensi-harian.index', [
            'tanggal' => $data['tanggal'],
            'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
            'kelas_id' => $data['kelas_id'] ?? null,
        ])->with('berhasil', $pesan);
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $daftarTahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $daftarTahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        $tahunAktif = $daftarTahunPelajaran->firstWhere('aktif', true);

        return $tahunAktif?->id ?? $daftarTahunPelajaran->first()?->id;
    }

    private function ambilKelasId(?int $kelasId, $daftarKelas, bool $cakupanWaliKelas): ?int
    {
        if ($kelasId && $daftarKelas->contains('id', $kelasId)) {
            return $kelasId;
        }

        if ($cakupanWaliKelas && $daftarKelas->count() === 1) {
            return (int) $daftarKelas->first()->id;
        }

        return null;
    }

    private function ambilRekapAbsensi(string $tanggal, int $tahunPelajaranId, ?int $kelasId, ?array $kelasIdsTerjangkau = null)
    {
        $anggotaKelas = AnggotaKelas::query()
            ->with(['kelas', 'siswa'])
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('status_keanggotaan', 'aktif')
            ->when(is_array($kelasIdsTerjangkau), fn ($query) => $query->whereIn('kelas_id', $kelasIdsTerjangkau))
            ->whereHas('siswa', function ($query) {
                $query->where('aktif', true);
            })
            ->when($kelasId, function ($query) use ($kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->orderBy('kelas_id')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->get();

        $absensi = AbsensiSiswa::query()
            ->whereDate('tanggal', $tanggal)
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->when(is_array($kelasIdsTerjangkau), fn ($query) => $query->whereIn('kelas_id', $kelasIdsTerjangkau))
            ->when($kelasId, function ($query) use ($kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->get();

        $absensiPerAnggota = $absensi->whereNotNull('anggota_kelas_id')->keyBy('anggota_kelas_id');
        $absensiPerSiswa = $absensi->keyBy('siswa_id');
        $laporanPerAbsensi = LaporanPembinaanSiswa::query()
            ->where('sumber_laporan', 'absensi_otomatis')
            ->whereIn('absensi_siswa_id', $absensi->pluck('id'))
            ->latest('id')
            ->get()
            ->groupBy('absensi_siswa_id')
            ->map(fn ($items) => $items->first(fn ($item) => $item->status_verifikasi !== 'dibatalkan') ?? $items->first());

        return $anggotaKelas->map(function (AnggotaKelas $anggota) use ($absensiPerAnggota, $absensiPerSiswa, $laporanPerAbsensi) {
            $absen = $absensiPerAnggota->get($anggota->id) ?? $absensiPerSiswa->get($anggota->siswa_id);
            $statusKehadiran = $absen?->status_kehadiran ?? 'alfa';

            return [
                'anggota_kelas' => $anggota,
                'absensi' => $absen,
                'laporan_keterlambatan' => $absen ? $laporanPerAbsensi->get($absen->id) : null,
                'status_kehadiran' => $statusKehadiran,
                'status_sumber' => $absen ? 'catatan' : 'inferensi',
                'terlambat' => (int) ($absen?->menit_terlambat ?? 0),
                'pulang_cepat' => (int) ($absen?->menit_pulang_cepat ?? 0),
                'belum_pulang' => $statusKehadiran === 'hadir' && $absen?->jam_masuk && ! $absen?->jam_pulang,
            ];
        });
    }

    private function ambilAbsensi(string $tanggal, AnggotaKelas $anggotaKelas): ?AbsensiSiswa
    {
        return AbsensiSiswa::query()
            ->whereDate('tanggal', $tanggal)
            ->where('siswa_id', $anggotaKelas->siswa_id)
            ->first();
    }

    private function pastikanBolehAksesAnggotaKelas(Request $request, AnggotaKelas $anggotaKelas): void
    {
        abort_unless(
            $request->user()?->dapatMengaksesKelasSebagaiWali($anggotaKelas->kelas_id) ?? false,
            403,
        );
    }

    private function koreksiHariIniTerbatas(Request $request): bool
    {
        return ($request->user()?->memilikiIzin('absensi.koreksi_hari_ini') ?? false)
            && ! ($request->user()?->memilikiIzin('absensi.koreksi') ?? false);
    }

    private function pastikanTanggalKoreksiDiizinkan(Request $request, string $tanggal): void
    {
        abort_if(
            $this->koreksiHariIniTerbatas($request) && $tanggal !== now()->toDateString(),
            403,
            'Guru PL hanya dapat mengoreksi presensi siswa pada hari berjalan.',
        );
    }

    private function pastikanCatatanScanTidakDiubah(Request $request, ?AbsensiSiswa $absensi): void
    {
        abort_if(
            $this->koreksiHariIniTerbatas($request) && $absensi?->sumber === 'scan',
            403,
            'Catatan hasil scan hanya dapat dikoreksi oleh petugas dengan kewenangan penuh.',
        );
    }

    private function ambilPengaturanAbsensi(string $tanggal): ?PengaturanAbsensi
    {
        return PengaturanAbsensi::query()
            ->where('hari', $this->hariDariTanggal(Carbon::parse($tanggal)->isoWeekday()))
            ->where('aktif', true)
            ->first();
    }

    private function hariDariTanggal(int $isoWeekday): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$isoWeekday];
    }

    private function hitungRingkasan($rekapAbsensi): array
    {
        return [
            'total' => $rekapAbsensi->count(),
            'hadir' => $rekapAbsensi->where('status_kehadiran', 'hadir')->count(),
            'izin' => $rekapAbsensi->where('status_kehadiran', 'izin')->count(),
            'sakit' => $rekapAbsensi->where('status_kehadiran', 'sakit')->count(),
            'alfa' => $rekapAbsensi->where('status_kehadiran', 'alfa')->count(),
            'terlambat' => $rekapAbsensi->where('terlambat', '>', 0)->count(),
            'pulang_cepat' => $rekapAbsensi->where('pulang_cepat', '>', 0)->count(),
            'belum_pulang' => $rekapAbsensi->where('belum_pulang', true)->count(),
        ];
    }

    private function labelCakupan(?int $kelasId, $daftarKelas, bool $cakupanWaliKelas): string
    {
        if ($kelasId) {
            return 'Kelas '.($daftarKelas->firstWhere('id', $kelasId)?->nama ?? '-');
        }

        return $cakupanWaliKelas ? 'Semua kelas wali' : 'Semua kelas';
    }

    private function buatRangkumanWhatsapp(string $tanggal, string $labelCakupan, array $ringkasan, $rekapAbsensi): string
    {
        $tanggalLabel = Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y');
        $hadirTepatWaktu = $rekapAbsensi->filter(fn (array $item) => $item['status_kehadiran'] === 'hadir' && (int) $item['terlambat'] === 0);
        $terlambat = $rekapAbsensi->filter(fn (array $item) => $item['status_kehadiran'] === 'hadir' && (int) $item['terlambat'] > 0);
        $izin = $rekapAbsensi->where('status_kehadiran', 'izin');
        $sakit = $rekapAbsensi->where('status_kehadiran', 'sakit');
        $alfa = $rekapAbsensi->filter(fn (array $item) => $item['status_kehadiran'] === 'alfa' && $item['status_sumber'] !== 'inferensi');
        $belumScan = $rekapAbsensi->filter(fn (array $item) => $item['status_kehadiran'] === 'alfa' && $item['status_sumber'] === 'inferensi');

        $baris = [
            '*REKAP KEHADIRAN SISWA*',
            'SMP Negeri 2 Padang Panjang',
            'Tanggal: '.$tanggalLabel,
            'Cakupan: '.$labelCakupan,
            '',
            'Total siswa: '.$ringkasan['total'],
            'Hadir tepat waktu: '.$hadirTepatWaktu->count(),
            'Terlambat: '.$terlambat->count(),
            'Sakit: '.$sakit->count(),
            'Izin: '.$izin->count(),
            'Alfa: '.$alfa->count(),
            'Belum scan: '.$belumScan->count(),
        ];

        $this->tambahkanBagianWhatsapp($baris, 'Terlambat', $terlambat, fn (array $item) => $this->barisSiswaWhatsapp($item, $this->formatJamRangkuman($item['absensi']?->jam_masuk).' - terlambat '.$item['terlambat'].' menit'));
        $this->tambahkanBagianWhatsapp($baris, 'Sakit', $sakit, fn (array $item) => $this->barisSiswaWhatsapp($item, $item['absensi']?->catatan ?: 'Sakit'));
        $this->tambahkanBagianWhatsapp($baris, 'Izin', $izin, fn (array $item) => $this->barisSiswaWhatsapp($item, $item['absensi']?->catatan ?: 'Izin'));
        $this->tambahkanBagianWhatsapp($baris, 'Alfa', $alfa, fn (array $item) => $this->barisSiswaWhatsapp($item, $item['absensi']?->catatan ?: 'Alfa'));
        $this->tambahkanBagianWhatsapp($baris, 'Belum Scan', $belumScan, fn (array $item) => $this->barisSiswaWhatsapp($item, 'Belum ada catatan scan/manual'));

        $baris[] = '';
        $baris[] = 'Catatan: Siswa yang hadir tepat waktu tidak ditampilkan agar pesan lebih ringkas. Jika ada keterangan sakit/izin yang belum tercatat, silakan menghubungi wali kelas.';
        $baris[] = 'NUSA - SMP Negeri 2 Padang Panjang';

        return implode("\n", $baris);
    }

    private function tambahkanBagianWhatsapp(array &$baris, string $judul, $items, callable $pembuatBaris): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $baris[] = '';
        $baris[] = '*'.$judul.'*';

        foreach ($items->values() as $index => $item) {
            $baris[] = ($index + 1).'. '.$pembuatBaris($item);
        }
    }

    private function barisSiswaWhatsapp(array $item, string $keterangan): string
    {
        $anggota = $item['anggota_kelas'];
        $nama = $anggota->siswa?->nama_lengkap ?: '-';
        $kelas = $anggota->kelas?->nama ? ' ('.$anggota->kelas->nama.')' : '';

        return $nama.$kelas.' - '.$keterangan;
    }

    private function formatJamRangkuman(?string $jam): string
    {
        return $jam ? substr($jam, 0, 5) : '-';
    }
}
