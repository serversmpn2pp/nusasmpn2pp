<?php

namespace App\Http\Controllers;

use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalKegiatanIbadah;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\LaporanPembinaanSiswa;
use App\Models\LogScanAbsensi;
use App\Models\LogScanAbsensiPegawai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\PresensiKegiatanIbadah;
use App\Models\PublikasiNilaiSiswa;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class BerandaController extends Controller
{
    public function index()
    {
        $pengguna = auth()->user();

        $hariIni = now();
        $tanggalHariIni = $hariIni->toDateString();
        $tahunPelajaranAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        $awalBulan = $hariIni->copy()->startOfMonth();
        $akhirBulan = $hariIni->copy()->endOfMonth();

        if ($pengguna?->akunSiswa() || $pengguna?->memilikiPeran('siswa')) {
            return view('beranda.siswa', $this->dataDashboardSiswa(
                pengguna: $pengguna,
                hariIni: $hariIni,
                tahunPelajaranAktif: $tahunPelajaranAktif,
                awalBulan: $awalBulan,
                akhirBulan: $akhirBulan,
            ));
        }

        if ($pengguna?->akunOrangTua() || $pengguna?->memilikiPeran('orang_tua')) {
            $orangTua = $pengguna->orangTuaWali()
                ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
                ->first();

            return view('beranda.orang-tua', [
                'orangTua' => $orangTua,
                'daftarAnak' => $orangTua?->siswa ?? collect(),
                'tahunPelajaranAktif' => $tahunPelajaranAktif,
            ]);
        }

        if (! $pengguna?->administrator()) {
            return view('beranda.index', $this->dataDashboardPegawai(
                pengguna: $pengguna,
                hariIni: $hariIni,
                tahunPelajaranAktif: $tahunPelajaranAktif,
                awalBulan: $awalBulan,
                akhirBulan: $akhirBulan,
            ));
        }

        $jumlahSiswaAktif = Siswa::query()->where('aktif', true)->count();
        $jumlahPegawaiAktif = Pegawai::query()->where('aktif', true)->count();
        $jumlahSiswaDipantau = $tahunPelajaranAktif
            ? AnggotaKelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
                ->where('status_keanggotaan', 'aktif')
                ->distinct('siswa_id')
                ->count('siswa_id')
            : $jumlahSiswaAktif;

        if ($jumlahSiswaDipantau === 0) {
            $jumlahSiswaDipantau = $jumlahSiswaAktif;
        }

        $ringkasanUtama = [
            'siswa_aktif' => $jumlahSiswaAktif,
            'pegawai_aktif' => $jumlahPegawaiAktif,
            'kelas_aktif' => Kelas::query()->where('aktif', true)->count(),
            'mata_pelajaran_aktif' => MataPelajaran::query()->where('aktif', true)->count(),
        ];

        $absensiHariIni = AbsensiSiswa::query()->whereDate('tanggal', $tanggalHariIni);
        $jumlahAbsensiSiswaTercatat = (clone $absensiHariIni)->distinct('siswa_id')->count('siswa_id');
        $ringkasanAbsensi = [
            'siswa_dipantau' => $jumlahSiswaDipantau,
            'hadir' => (clone $absensiHariIni)->where('status_kehadiran', 'hadir')->count(),
            'izin' => (clone $absensiHariIni)->where('status_kehadiran', 'izin')->count(),
            'sakit' => (clone $absensiHariIni)->where('status_kehadiran', 'sakit')->count(),
            'alfa' => (clone $absensiHariIni)->where('status_kehadiran', 'alfa')->count(),
            'terlambat' => (clone $absensiHariIni)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensiHariIni)->where('menit_pulang_cepat', '>', 0)->count(),
            'belum_scan' => max($jumlahSiswaDipantau - $jumlahAbsensiSiswaTercatat, 0),
            'scan_berhasil' => LogScanAbsensi::query()
                ->whereDate('tanggal', $tanggalHariIni)
                ->where('berhasil', true)
                ->count(),
        ];

        $absensiPegawaiHariIni = AbsensiPegawai::query()->whereDate('tanggal', $tanggalHariIni);
        $jumlahAbsensiPegawaiTercatat = (clone $absensiPegawaiHariIni)->distinct('pegawai_id')->count('pegawai_id');
        $ringkasanAbsensiPegawai = [
            'pegawai_dipantau' => $jumlahPegawaiAktif,
            'hadir' => (clone $absensiPegawaiHariIni)->where('status_kehadiran', 'hadir')->count(),
            'izin_sakit_dinas' => (clone $absensiPegawaiHariIni)
                ->whereIn('status_kehadiran', ['izin', 'sakit', 'dinas_luar', 'cuti'])
                ->count(),
            'alfa' => (clone $absensiPegawaiHariIni)->where('status_kehadiran', 'alfa')->count(),
            'terlambat' => (clone $absensiPegawaiHariIni)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensiPegawaiHariIni)->where('menit_pulang_cepat', '>', 0)->count(),
            'belum_scan' => max($jumlahPegawaiAktif - $jumlahAbsensiPegawaiTercatat, 0),
            'scan_berhasil' => LogScanAbsensiPegawai::query()
                ->whereDate('tanggal', $tanggalHariIni)
                ->where('berhasil', true)
                ->count(),
        ];

        $guruMapelAktif = GuruMataPelajaran::query()
            ->where('aktif', true)
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
        $komponenNilaiAktif = KomponenNilai::query()
            ->where('aktif', true)
            ->when($tahunPelajaranAktif, function ($query) use ($tahunPelajaranAktif) {
                $query->whereHas('guruMataPelajaran', fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
            });
        $nilaiSiswa = NilaiSiswa::query()
            ->when($tahunPelajaranAktif, function ($query) use ($tahunPelajaranAktif) {
                $query->whereHas('komponenNilai.guruMataPelajaran', fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
            });
        $ringkasanAkademik = [
            'guru_mapel_aktif' => (clone $guruMapelAktif)->count(),
            'komponen_nilai_aktif' => (clone $komponenNilaiAktif)->count(),
            'komponen_belum_terisi' => (clone $komponenNilaiAktif)->whereDoesntHave('nilaiSiswa')->count(),
            'nilai_masuk' => (clone $nilaiSiswa)->count(),
            'kelas_terisi' => Kelas::query()->whereHas('anggotaKelas')->count(),
        ];

        $ringkasanPembinaan = [
            'baru' => LaporanPembinaanSiswa::query()->where('status', 'baru')->count(),
            'diproses' => LaporanPembinaanSiswa::query()->where('status', 'diproses')->count(),
            'perlu_tindak_lanjut' => LaporanPembinaanSiswa::query()->where('status', 'perlu_tindak_lanjut')->count(),
            'selesai_bulan_ini' => LaporanPembinaanSiswa::query()
                ->where('status', 'selesai')
                ->whereBetween('updated_at', [$awalBulan, $akhirBulan])
                ->count(),
            'menunggu_bk' => LaporanPembinaanSiswa::query()
                ->whereIn('status_verifikasi', ['diajukan', 'pemeriksaan_bk', 'perlu_klarifikasi', 'dikembalikan_bk'])
                ->count(),
            'menunggu_wakil' => LaporanPembinaanSiswa::query()
                ->where('status_verifikasi', 'menunggu_pengesahan_wakil')
                ->count(),
            'pembinaan_ditetapkan' => LaporanPembinaanSiswa::query()
                ->where('status_verifikasi', 'ditetapkan_pembinaan')
                ->count(),
            'poin_aktif' => (int) TransaksiPoinSiswa::query()
                ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
                ->sum('poin'),
            'sanksi_menunggu' => SanksiPoinSiswa::query()
                ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
                ->whereIn('status', ['menunggu', 'diproses'])
                ->count(),
        ];

        $logScanTerakhir = LogScanAbsensi::query()
            ->with('siswa:id,nama_lengkap,nisn')
            ->latest('waktu_scan')
            ->limit(5)
            ->get();

        $logScanPegawaiTerakhir = LogScanAbsensiPegawai::query()
            ->with('pegawai:id,nama_lengkap,nip')
            ->latest('waktu_scan')
            ->limit(5)
            ->get();

        $siswaTerlambatHariIni = AbsensiSiswa::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'kelas:id,nama'])
            ->whereDate('tanggal', $tanggalHariIni)
            ->where('menit_terlambat', '>', 0)
            ->orderByDesc('menit_terlambat')
            ->limit(5)
            ->get();

        $siswaAlfaHariIni = AbsensiSiswa::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'kelas:id,nama'])
            ->whereDate('tanggal', $tanggalHariIni)
            ->where('status_kehadiran', 'alfa')
            ->latest('id')
            ->limit(5)
            ->get();

        $siswaBelumScanHariIni = AnggotaKelas::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'kelas:id,nama'])
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
            ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
            ->whereDoesntHave('siswa.absensiSiswa', fn ($query) => $query->whereDate('tanggal', $tanggalHariIni))
            ->orderBy('kelas_id')
            ->orderBy('nomor_absen')
            ->limit(5)
            ->get();

        $pegawaiTerlambatHariIni = AbsensiPegawai::query()
            ->with('pegawai:id,nama_lengkap,nip')
            ->whereDate('tanggal', $tanggalHariIni)
            ->where('menit_terlambat', '>', 0)
            ->orderByDesc('menit_terlambat')
            ->limit(5)
            ->get();

        $pegawaiBelumScanHariIni = Pegawai::query()
            ->where('aktif', true)
            ->whereDoesntHave('absensiPegawai', fn ($query) => $query->whereDate('tanggal', $tanggalHariIni))
            ->orderBy('nama_lengkap')
            ->limit(5)
            ->get(['id', 'nama_lengkap', 'nip']);

        $laporanPembinaanPerluPerhatian = LaporanPembinaanSiswa::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'kategoriPembinaanSiswa:id,nama'])
            ->where(function ($query) {
                $query->whereIn('status', ['baru', 'perlu_tindak_lanjut'])
                    ->orWhereIn('status_verifikasi', ['diajukan', 'pemeriksaan_bk', 'perlu_klarifikasi', 'dikembalikan_bk', 'menunggu_pengesahan_wakil', 'menunggu_persetujuan', 'disetujui_sebagian', 'perlu_musyawarah']);
            })
            ->orderByRaw("case when status_verifikasi = 'perlu_klarifikasi' then 0 when status = 'perlu_tindak_lanjut' then 1 else 2 end")
            ->orderByDesc('tanggal_kejadian')
            ->limit(5)
            ->get();

        return view('beranda.index', compact(
            'hariIni',
            'tahunPelajaranAktif',
            'ringkasanUtama',
            'ringkasanAbsensi',
            'ringkasanAbsensiPegawai',
            'ringkasanAkademik',
            'ringkasanPembinaan',
            'logScanTerakhir',
            'logScanPegawaiTerakhir',
            'siswaTerlambatHariIni',
            'siswaAlfaHariIni',
            'siswaBelumScanHariIni',
            'pegawaiTerlambatHariIni',
            'pegawaiBelumScanHariIni',
            'laporanPembinaanPerluPerhatian',
        ));
    }

    private function dataDashboardSiswa(
        Pengguna $pengguna,
        $hariIni,
        ?TahunPelajaran $tahunPelajaranAktif,
        $awalBulan,
        $akhirBulan,
    ): array {
        $siswa = $pengguna->siswa()->first();
        $anggotaKelas = null;

        if ($siswa) {
            $queryAnggota = AnggotaKelas::query()
                ->with(['kelas.tahunPelajaran', 'kelas.waliKelas', 'tahunPelajaran'])
                ->where('siswa_id', $siswa->id)
                ->where('status_keanggotaan', 'aktif');

            if ($tahunPelajaranAktif) {
                $anggotaKelas = (clone $queryAnggota)
                    ->where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
                    ->first();
            }

            $anggotaKelas ??= $queryAnggota
                ->latest('tahun_pelajaran_id')
                ->latest('id')
                ->first();
        }

        $kelas = $anggotaKelas?->kelas;
        $tahunDashboard = $tahunPelajaranAktif ?: $anggotaKelas?->tahunPelajaran;
        $kodeHari = [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$hariIni->dayOfWeekIso];

        $jadwalHariIni = JadwalPelajaran::query()
            ->with([
                'jamPelajaran',
                'mataPelajaran',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->where('kelas_id', $kelas?->id ?: 0)
            ->where('tahun_pelajaran_id', $tahunDashboard?->id ?: 0)
            ->where('hari', $kodeHari)
            ->where('aktif', true)
            ->whereHas('jamPelajaran', fn ($query) => $query->where('aktif', true))
            ->orderBy(
                JamPelajaran::select('nomor_jam')
                    ->whereColumn('jam_pelajaran.id', 'jadwal_pelajaran.jam_pelajaran_id')
                    ->limit(1),
            )
            ->get();

        $absensiBulan = AbsensiSiswa::query()
            ->where('siswa_id', $siswa?->id ?: 0)
            ->whereBetween('tanggal', [$awalBulan->toDateString(), $akhirBulan->toDateString()])
            ->when($tahunDashboard, fn ($query) => $query->where('tahun_pelajaran_id', $tahunDashboard->id));
        $jumlahStatusBulan = (clone $absensiBulan)
            ->selectRaw('status_kehadiran, count(*) as jumlah')
            ->groupBy('status_kehadiran')
            ->pluck('jumlah', 'status_kehadiran');
        $absensiHariIni = (clone $absensiBulan)
            ->whereDate('tanggal', $hariIni->toDateString())
            ->first();
        $ringkasanKehadiran = [
            'hadir' => (int) ($jumlahStatusBulan['hadir'] ?? 0),
            'sakit' => (int) ($jumlahStatusBulan['sakit'] ?? 0),
            'izin' => (int) ($jumlahStatusBulan['izin'] ?? 0),
            'alfa' => (int) ($jumlahStatusBulan['alfa'] ?? 0),
            'terlambat' => (clone $absensiBulan)->where('menit_terlambat', '>', 0)->count(),
            'menit_terlambat' => (int) (clone $absensiBulan)->sum('menit_terlambat'),
            'pulang_cepat' => (clone $absensiBulan)->where('menit_pulang_cepat', '>', 0)->count(),
            'total_catatan' => (int) $jumlahStatusBulan->sum(),
        ];

        $ringkasanIbadahSaya = $this->ringkasanIbadahSiswa(
            siswa: $siswa,
            anggotaKelas: $anggotaKelas,
            tahunPelajaran: $tahunDashboard,
            hariIni: $hariIni,
            awalBulan: $awalBulan,
            akhirBulan: $akhirBulan,
            kodeHari: $kodeHari,
        );

        $transaksiPoin = TransaksiPoinSiswa::query()
            ->where('siswa_id', $siswa?->id ?: 0)
            ->when($tahunDashboard, fn ($query) => $query->where('tahun_pelajaran_id', $tahunDashboard->id));
        $ringkasanPoin = [
            'total' => (int) (clone $transaksiPoin)->sum('poin'),
            'pelanggaran' => (clone $transaksiPoin)->where('jenis', 'pelanggaran')->count(),
            'pengurangan' => abs((int) (clone $transaksiPoin)->where('jenis', 'pengurangan')->sum('poin')),
        ];
        $riwayatPoinTerbaru = (clone $transaksiPoin)
            ->latest('tercatat_pada')
            ->latest('id')
            ->limit(4)
            ->get();

        $guruWali = PenugasanGuruWaliSiswa::query()
            ->with('guruWali')
            ->where('siswa_id', $siswa?->id ?: 0)
            ->where('aktif', true)
            ->where(function ($query) use ($hariIni) {
                $query->whereNull('tanggal_mulai')
                    ->orWhereDate('tanggal_mulai', '<=', $hariIni->toDateString());
            })
            ->where(function ($query) use ($hariIni) {
                $query->whereNull('tanggal_selesai')
                    ->orWhereDate('tanggal_selesai', '>=', $hariIni->toDateString());
            })
            ->latest('tanggal_mulai')
            ->latest('id')
            ->first()
            ?->guruWali;
        $notifikasiDashboard = $pengguna->notifikasiPengguna()
            ->latest()
            ->limit(4)
            ->get();
        $urlFotoSiswa = $siswa?->foto
            ? asset('storage/'.$siswa->foto)
            : asset('images/kartu-pelajar/default-user.png');
        $jumlahNilaiDipublikasikan = PublikasiNilaiSiswa::query()
            ->where('dipublikasikan', true)
            ->whereHas('guruMataPelajaran', fn ($query) => $query
                ->where('kelas_id', $kelas?->id ?: 0)
                ->when($tahunDashboard, fn ($query) => $query->where('tahun_pelajaran_id', $tahunDashboard->id)))
            ->count();

        return [
            'hariIni' => $hariIni,
            'labelBulan' => $hariIni->copy()->locale('id')->translatedFormat('F Y'),
            'tahunPelajaranAktif' => $tahunDashboard,
            'siswaLogin' => $siswa,
            'anggotaKelasAktif' => $anggotaKelas,
            'kelasAktif' => $kelas,
            'waliKelas' => $kelas?->waliKelas,
            'guruWali' => $guruWali,
            'kodeHari' => $kodeHari,
            'jadwalHariIni' => $jadwalHariIni,
            'absensiHariIni' => $absensiHariIni,
            'ringkasanKehadiran' => $ringkasanKehadiran,
            'ringkasanIbadahSaya' => $ringkasanIbadahSaya,
            'ringkasanPoin' => $ringkasanPoin,
            'riwayatPoinTerbaru' => $riwayatPoinTerbaru,
            'notifikasiDashboard' => $notifikasiDashboard,
            'urlFotoSiswa' => $urlFotoSiswa,
            'jumlahNilaiDipublikasikan' => $jumlahNilaiDipublikasikan,
        ];
    }

    private function ringkasanIbadahSiswa(
        ?Siswa $siswa,
        ?AnggotaKelas $anggotaKelas,
        ?TahunPelajaran $tahunPelajaran,
        $hariIni,
        $awalBulan,
        $akhirBulan,
        string $kodeHari,
    ): Collection {
        if (! $siswa || ! $anggotaKelas || ! $tahunPelajaran) {
            return collect();
        }

        $jadwal = JadwalKegiatanIbadah::query()
            ->with('kegiatanIbadah:id,nama,kode,aktif')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('aktif', true)
            ->whereHas('kegiatanIbadah', fn ($query) => $query->where('aktif', true))
            ->orderBy('urutan_hari')
            ->get();
        $jadwalPerKegiatan = $jadwal->groupBy('kegiatan_ibadah_id');

        if ($jadwalPerKegiatan->isEmpty()) {
            return collect();
        }

        $mulai = $awalBulan->copy()
            ->max($tahunPelajaran->tanggal_mulai->copy()->startOfDay());
        $selesai = $akhirBulan->copy()
            ->min($tahunPelajaran->tanggal_selesai->copy()->endOfDay())
            ->min($hariIni->copy()->endOfDay());

        if ($anggotaKelas->tanggal_masuk) {
            $mulai = $mulai->max($anggotaKelas->tanggal_masuk->copy()->startOfDay());
        }

        if ($anggotaKelas->tanggal_keluar) {
            $selesai = $selesai->min($anggotaKelas->tanggal_keluar->copy()->endOfDay());
        }

        $periodeTersedia = $mulai->lte($selesai);
        $presensi = $periodeTersedia
            ? PresensiKegiatanIbadah::query()
                ->where('siswa_id', $siswa->id)
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->whereIn('kegiatan_ibadah_id', $jadwalPerKegiatan->keys())
                ->whereDate('tanggal', '>=', $mulai->toDateString())
                ->whereDate('tanggal', '<=', $selesai->toDateString())
                ->orderBy('tanggal')
                ->get()
                ->groupBy('kegiatan_ibadah_id')
            : collect();

        return $jadwalPerKegiatan->map(function ($jadwalKegiatan, $kegiatanId) use ($presensi, $mulai, $selesai, $hariIni, $kodeHari, $periodeTersedia) {
            $kegiatan = $jadwalKegiatan->first()?->kegiatanIbadah;
            $hariTerjadwal = $jadwalKegiatan->pluck('hari')->flip();
            $presensiKegiatan = $presensi->get($kegiatanId, collect());
            $tanggalTarget = collect();

            if ($periodeTersedia) {
                foreach (CarbonPeriod::create($mulai, $selesai) as $tanggal) {
                    $kodeHariTanggal = [
                        1 => 'senin',
                        2 => 'selasa',
                        3 => 'rabu',
                        4 => 'kamis',
                        5 => 'jumat',
                        6 => 'sabtu',
                        7 => 'minggu',
                    ][$tanggal->dayOfWeekIso];

                    if ($hariTerjadwal->has($kodeHariTanggal)) {
                        $tanggalTarget->push($tanggal->toDateString());
                    }
                }
            }

            $presensiKegiatan->each(fn ($item) => $tanggalTarget->push($item->tanggal->toDateString()));
            $tanggalTarget = $tanggalTarget->unique();
            $tanggalTercatat = $presensiKegiatan->pluck('tanggal')
                ->map(fn ($tanggal) => $tanggal->toDateString())
                ->unique();
            $target = $tanggalTarget->count();
            $tercatat = $tanggalTercatat->count();
            $presensiHariIni = $presensiKegiatan->first(fn ($item) => $item->tanggal->isSameDay($hariIni));
            $dijadwalkanHariIni = $periodeTersedia && $hariTerjadwal->has($kodeHari);

            return [
                'kegiatan' => $kegiatan,
                'dijadwalkan_hari_ini' => $dijadwalkanHariIni,
                'presensi_hari_ini' => $presensiHariIni,
                'status_hari_ini' => $presensiHariIni
                    ? 'Sudah tercatat'
                    : ($dijadwalkanHariIni ? 'Belum tercatat' : 'Tidak dijadwalkan'),
                'target' => $target,
                'tercatat' => $tercatat,
                'belum' => max($target - $tercatat, 0),
                'persentase' => $target > 0 ? round(($tercatat / $target) * 100, 1) : 0,
            ];
        })->sortBy(fn ($item) => $item['kegiatan']?->nama)->values();
    }

    private function dataDashboardPegawai(
        Pengguna $pengguna,
        $hariIni,
        ?TahunPelajaran $tahunPelajaranAktif,
        $awalBulan,
        $akhirBulan,
    ): array {
        $pegawaiLogin = $pengguna->pegawai()->first();
        $labelBulan = $hariIni->copy()->locale('id')->translatedFormat('F Y');
        $awalBulanTanggal = $awalBulan->toDateString();
        $akhirBulanTanggal = $akhirBulan->toDateString();

        $absensiPegawaiBulan = AbsensiPegawai::query()
            ->where('pegawai_id', $pengguna->pegawai_id ?: 0)
            ->whereBetween('tanggal', [$awalBulanTanggal, $akhirBulanTanggal]);
        $jumlahStatusPegawaiBulan = (clone $absensiPegawaiBulan)
            ->selectRaw('status_kehadiran, count(*) as jumlah')
            ->groupBy('status_kehadiran')
            ->pluck('jumlah', 'status_kehadiran');

        $rekapAbsensiPegawaiBulan = collect(AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN)
            ->map(fn (string $label, string $status) => [
                'kode' => $status,
                'label' => $label,
                'jumlah' => (int) ($jumlahStatusPegawaiBulan[$status] ?? 0),
                'warna' => $this->warnaStatusAbsensiPegawai($status),
            ])
            ->values();

        $ringkasanAbsensiPegawaiPribadi = [
            'total_catatan' => (int) $jumlahStatusPegawaiBulan->sum(),
            'hadir' => (int) ($jumlahStatusPegawaiBulan['hadir'] ?? 0),
            'sakit' => (int) ($jumlahStatusPegawaiBulan['sakit'] ?? 0),
            'izin' => (int) ($jumlahStatusPegawaiBulan['izin'] ?? 0),
            'dinas_luar' => (int) ($jumlahStatusPegawaiBulan['dinas_luar'] ?? 0),
            'cuti' => (int) ($jumlahStatusPegawaiBulan['cuti'] ?? 0),
            'alfa' => (int) ($jumlahStatusPegawaiBulan['alfa'] ?? 0),
            'terlambat' => (clone $absensiPegawaiBulan)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensiPegawaiBulan)->where('menit_pulang_cepat', '>', 0)->count(),
            'belum_pulang' => (clone $absensiPegawaiBulan)
                ->where('status_kehadiran', 'hadir')
                ->whereNotNull('jam_masuk')
                ->whereNull('jam_pulang')
                ->count(),
        ];

        $kelasWali = Kelas::query()
            ->withCount([
                'anggotaKelas as jumlah_siswa_aktif' => function ($query) use ($tahunPelajaranAktif) {
                    $query->where('status_keanggotaan', 'aktif')
                        ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
                },
            ])
            ->where('wali_kelas_id', $pengguna->pegawai_id ?: 0)
            ->where('aktif', true)
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat']);

        $kelasWaliIds = $kelasWali->pluck('id')->map(fn ($id) => (int) $id)->all();
        $siswaGuruWaliIds = $pengguna->siswaWaliIds();
        $jumlahSiswaGuruWali = count($siswaGuruWaliIds);
        $memilikiPerwalian = $kelasWaliIds !== [] || $siswaGuruWaliIds !== [];

        $absensiSiswaWaliBulan = AbsensiSiswa::query()
            ->whereIn('kelas_id', $kelasWaliIds)
            ->whereBetween('tanggal', [$awalBulanTanggal, $akhirBulanTanggal])
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
        $jumlahStatusSiswaWaliBulan = (clone $absensiSiswaWaliBulan)
            ->selectRaw('status_kehadiran, count(*) as jumlah')
            ->groupBy('status_kehadiran')
            ->pluck('jumlah', 'status_kehadiran');

        $labelStatusSiswa = [
            'hadir' => 'Hadir',
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            'alfa' => 'Alfa',
        ];

        $rekapAbsensiSiswaWaliBulan = collect($labelStatusSiswa)
            ->map(fn (string $label, string $status) => [
                'kode' => $status,
                'label' => $label,
                'jumlah' => (int) ($jumlahStatusSiswaWaliBulan[$status] ?? 0),
                'warna' => $this->warnaStatusAbsensiSiswa($status),
            ])
            ->values();

        $jumlahSiswaWali = AnggotaKelas::query()
            ->whereIn('kelas_id', $kelasWaliIds)
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
            ->distinct('siswa_id')
            ->count('siswa_id');

        $ringkasanAbsensiSiswaWali = [
            'jumlah_siswa' => $jumlahSiswaWali,
            'total_catatan' => (int) $jumlahStatusSiswaWaliBulan->sum(),
            'hadir' => (int) ($jumlahStatusSiswaWaliBulan['hadir'] ?? 0),
            'sakit' => (int) ($jumlahStatusSiswaWaliBulan['sakit'] ?? 0),
            'izin' => (int) ($jumlahStatusSiswaWaliBulan['izin'] ?? 0),
            'alfa' => (int) ($jumlahStatusSiswaWaliBulan['alfa'] ?? 0),
            'terlambat' => (clone $absensiSiswaWaliBulan)->where('menit_terlambat', '>', 0)->count(),
            'pulang_cepat' => (clone $absensiSiswaWaliBulan)->where('menit_pulang_cepat', '>', 0)->count(),
        ];

        $laporanPembinaanWaliBulan = LaporanPembinaanSiswa::query()
            ->where(function ($query) use ($kelasWaliIds, $siswaGuruWaliIds) {
                if ($kelasWaliIds === [] && $siswaGuruWaliIds === []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->when($kelasWaliIds !== [], fn ($query) => $query->whereIn('kelas_id', $kelasWaliIds))
                    ->when($siswaGuruWaliIds !== [], fn ($query) => $query->orWhereIn('siswa_id', $siswaGuruWaliIds));
            })
            ->whereBetween('tanggal_kejadian', [$awalBulanTanggal, $akhirBulanTanggal])
            ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id));
        $jumlahStatusPembinaanWaliBulan = (clone $laporanPembinaanWaliBulan)
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $rekapPembinaanWaliBulan = collect(LaporanPembinaanSiswa::DAFTAR_STATUS)
            ->except('dibatalkan')
            ->map(fn (string $label, string $status) => [
                'kode' => $status,
                'label' => $label,
                'jumlah' => (int) ($jumlahStatusPembinaanWaliBulan[$status] ?? 0),
                'warna' => $this->warnaStatusPembinaan($status),
            ])
            ->values();

        $ringkasanPembinaanWali = [
            'total_laporan' => (int) $jumlahStatusPembinaanWaliBulan->sum(),
            'siswa_terlapor' => (clone $laporanPembinaanWaliBulan)->distinct('siswa_id')->count('siswa_id'),
            'baru' => (int) ($jumlahStatusPembinaanWaliBulan['baru'] ?? 0),
            'diproses' => (int) ($jumlahStatusPembinaanWaliBulan['diproses'] ?? 0),
            'perlu_tindak_lanjut' => (int) ($jumlahStatusPembinaanWaliBulan['perlu_tindak_lanjut'] ?? 0),
            'selesai' => (int) ($jumlahStatusPembinaanWaliBulan['selesai'] ?? 0),
            'menunggu_bk' => (clone $laporanPembinaanWaliBulan)
                ->whereIn('status_verifikasi', ['diajukan', 'pemeriksaan_bk', 'perlu_klarifikasi', 'dikembalikan_bk', 'menunggu_persetujuan', 'disetujui_sebagian', 'perlu_musyawarah'])
                ->count(),
            'menunggu_wakil' => (clone $laporanPembinaanWaliBulan)
                ->where('status_verifikasi', 'menunggu_pengesahan_wakil')
                ->count(),
            'poin_aktif' => (int) TransaksiPoinSiswa::query()
                ->when($tahunPelajaranAktif, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id))
                ->where(function ($query) use ($kelasWaliIds, $siswaGuruWaliIds) {
                    if ($kelasWaliIds === [] && $siswaGuruWaliIds === []) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->whereHas('siswa', function ($query) use ($kelasWaliIds, $siswaGuruWaliIds) {
                        $query->where(function ($query) use ($kelasWaliIds, $siswaGuruWaliIds) {
                            $query->when($kelasWaliIds !== [], fn ($query) => $query->whereHas('anggotaKelas', fn ($query) => $query->whereIn('kelas_id', $kelasWaliIds)))
                                ->when($siswaGuruWaliIds !== [], fn ($query) => $query->orWhereIn('id', $siswaGuruWaliIds));
                        });
                    });
                })
                ->sum('poin'),
        ];

        $laporanPembinaanWali = (clone $laporanPembinaanWaliBulan)
            ->with(['siswa:id,nama_lengkap,nisn', 'kelas:id,nama', 'kategoriPembinaanSiswa:id,nama'])
            ->orderByDesc('tanggal_kejadian')
            ->latest('id')
            ->limit(5)
            ->get();

        $kodeHariPiket = array_keys(JadwalPiketGuru::DAFTAR_HARI)[$hariIni->dayOfWeekIso - 1] ?? null;
        $jadwalPiketSaya = JadwalPiketGuru::query()
            ->where('pegawai_id', $pengguna->pegawai_id ?: 0)
            ->when(
                $tahunPelajaranAktif,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->get();
        $jadwalPiketHariIni = $jadwalPiketSaya->firstWhere('hari', $kodeHariPiket);

        return [
            'hariIni' => $hariIni,
            'tahunPelajaranAktif' => $tahunPelajaranAktif,
            'labelBulan' => $labelBulan,
            'pegawaiLogin' => $pegawaiLogin,
            'rekapAbsensiPegawaiBulan' => $rekapAbsensiPegawaiBulan,
            'ringkasanAbsensiPegawaiPribadi' => $ringkasanAbsensiPegawaiPribadi,
            'maksGrafikPegawai' => max((int) $rekapAbsensiPegawaiBulan->max('jumlah'), 1),
            'kelasWali' => $kelasWali,
            'jumlahSiswaGuruWali' => $jumlahSiswaGuruWali,
            'memilikiPerwalian' => $memilikiPerwalian,
            'rekapAbsensiSiswaWaliBulan' => $rekapAbsensiSiswaWaliBulan,
            'ringkasanAbsensiSiswaWali' => $ringkasanAbsensiSiswaWali,
            'maksGrafikSiswaWali' => max((int) $rekapAbsensiSiswaWaliBulan->max('jumlah'), 1),
            'rekapPembinaanWaliBulan' => $rekapPembinaanWaliBulan,
            'ringkasanPembinaanWali' => $ringkasanPembinaanWali,
            'laporanPembinaanWali' => $laporanPembinaanWali,
            'maksGrafikPembinaanWali' => max((int) $rekapPembinaanWaliBulan->max('jumlah'), 1),
            'jadwalPiketSaya' => $jadwalPiketSaya,
            'jadwalPiketHariIni' => $jadwalPiketHariIni,
        ];
    }

    private function warnaStatusAbsensiPegawai(string $status): string
    {
        return match ($status) {
            'hadir' => '#15477A',
            'sakit' => '#F1C40F',
            'izin' => '#2B83C6',
            'dinas_luar' => '#16A34A',
            'cuti' => '#7C3AED',
            'alfa' => '#DC2626',
            default => '#64748B',
        };
    }

    private function warnaStatusAbsensiSiswa(string $status): string
    {
        return match ($status) {
            'hadir' => '#15477A',
            'sakit' => '#F1C40F',
            'izin' => '#2B83C6',
            'alfa' => '#DC2626',
            default => '#64748B',
        };
    }

    private function warnaStatusPembinaan(string $status): string
    {
        return match ($status) {
            'baru' => '#F1C40F',
            'diproses' => '#2B83C6',
            'perlu_tindak_lanjut' => '#DC2626',
            'selesai' => '#16A34A',
            default => '#64748B',
        };
    }
}
