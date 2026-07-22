<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\SaksiLaporanPembinaanSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\AksesLaporanPembinaanService;
use App\Services\Pembinaan\CatatRiwayatPembinaanService;
use App\Services\Pembinaan\PengaturanBatasProsesPelanggaranService;
use App\Services\Pembinaan\ProsesPoinSiswaService;
use App\Services\Pembinaan\SimpanBuktiLaporanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LaporanPembinaanSiswaController extends Controller
{
    public function __construct(
        private NotifikasiPenggunaService $notifikasiPenggunaService,
        private ProsesPoinSiswaService $prosesPoinSiswaService,
        private AksesLaporanPembinaanService $aksesLaporan,
        private CatatRiwayatPembinaanService $riwayatPembinaan,
        private SimpanBuktiLaporanService $penyimpananBukti,
        private PengaturanBatasProsesPelanggaranService $pengaturanBatasProses,
    ) {}

    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = (string) $request->input('status', 'semua');
        $tingkat = (string) $request->input('tingkat', 'semua');
        $jenisLaporan = (string) $request->input('jenis_laporan', 'semua');
        $statusVerifikasi = (string) $request->input('status_verifikasi', 'semua');
        $kategoriDipilih = $this->inputId($request, 'kategori_pembinaan_siswa_id');
        $tahunPelajaranDipilih = $this->inputId($request, 'tahun_pelajaran_id');
        $kelasDipilih = $this->inputId($request, 'kelas_id');

        if (! array_key_exists($status, LaporanPembinaanSiswa::DAFTAR_STATUS) && $status !== 'semua') {
            $status = 'semua';
        }
        if (! array_key_exists($tingkat, LaporanPembinaanSiswa::DAFTAR_TINGKAT) && $tingkat !== 'semua') {
            $tingkat = 'semua';
        }
        if (! array_key_exists($jenisLaporan, LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN) && $jenisLaporan !== 'semua') {
            $jenisLaporan = 'semua';
        }
        if (! array_key_exists($statusVerifikasi, LaporanPembinaanSiswa::DAFTAR_STATUS_VERIFIKASI) && $statusVerifikasi !== 'semua') {
            $statusVerifikasi = 'semua';
        }

        $laporanPembinaanSiswa = $this->queryLaporanDalamCakupan($request)
            ->with(['siswa', 'kategoriPembinaanSiswa', 'tahunPelajaran', 'kelas', 'pelaporPegawai', 'butirPelanggaranLaporan'])
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->when($tingkat !== 'semua', fn ($query) => $query->where('tingkat', $tingkat))
            ->when($jenisLaporan !== 'semua', fn ($query) => $query->where('jenis_laporan', $jenisLaporan))
            ->when($statusVerifikasi !== 'semua', fn ($query) => $query->where('status_verifikasi', $statusVerifikasi))
            ->when($kategoriDipilih, fn ($query) => $query->where('kategori_pembinaan_siswa_id', $kategoriDipilih))
            ->when($tahunPelajaranDipilih, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranDipilih))
            ->when($kelasDipilih, fn ($query) => $query->where('kelas_id', $kelasDipilih))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nomor_laporan', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('tempat_kejadian', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('kronologi', 'ilike', '%'.$kataKunci.'%')
                        ->orWhereHas('siswa', fn ($query) => $query
                            ->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                            ->orWhere('nis', 'ilike', '%'.$kataKunci.'%')
                            ->orWhere('nisn', 'ilike', '%'.$kataKunci.'%'))
                        ->orWhereHas('butirPelanggaranLaporan', fn ($query) => $query
                            ->where('nama_pelanggaran', 'ilike', '%'.$kataKunci.'%')
                            ->orWhere('kode_pelanggaran', 'ilike', '%'.$kataKunci.'%'));
                });
            })
            ->orderByDesc('tanggal_kejadian')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $cakupan = $this->queryLaporanDalamCakupan($request);
        $ringkasan = [
            'total' => (clone $cakupan)->count(),
            'pembinaan' => (clone $cakupan)->where('jenis_laporan', 'pembinaan')->count(),
            'pelanggaran' => (clone $cakupan)->where('jenis_laporan', 'pelanggaran')->count(),
            'menunggu' => (clone $cakupan)->whereIn('status_verifikasi', ['diajukan', 'pemeriksaan_bk', 'perlu_klarifikasi', 'menunggu_persetujuan', 'disetujui_sebagian', 'perlu_musyawarah'])->count(),
            'disahkan' => (clone $cakupan)->where('status_verifikasi', 'disahkan')->count(),
        ];

        return view('laporan-pembinaan-siswa.index', array_merge(compact(
            'laporanPembinaanSiswa',
            'kataKunci',
            'status',
            'tingkat',
            'jenisLaporan',
            'statusVerifikasi',
            'kategoriDipilih',
            'tahunPelajaranDipilih',
            'kelasDipilih',
            'ringkasan',
        ), $this->pilihanFilter($request)));
    }

    public function create(Request $request)
    {
        $jenisAwal = array_key_exists((string) $request->input('jenis'), LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN)
            ? (string) $request->input('jenis')
            : 'pelanggaran';

        $laporanPembinaanSiswa = new LaporanPembinaanSiswa([
            'jenis_laporan' => $jenisAwal,
            'tanggal_kejadian' => now()->toDateString(),
            'tingkat' => 'ringan',
            'status' => 'baru',
            'status_verifikasi' => $jenisAwal === 'pelanggaran' ? 'diajukan' : 'tidak_perlu',
            'tahun_pelajaran_id' => TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id'),
            'pelapor_pegawai_id' => $request->user()?->pegawai_id,
        ]);

        return view('laporan-pembinaan-siswa.create', array_merge(
            compact('laporanPembinaanSiswa'),
            $this->pilihanForm($request),
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasi());

        $laporan = DB::transaction(function () use ($request, $data) {
            $daftarSaksi = $data['daftar_saksi'] ?? [];
            $keteranganBukti = $data['keterangan_bukti'] ?? null;
            unset($data['daftar_saksi'], $data['bukti_laporan'], $data['keterangan_bukti']);

            $data = $this->rapikanData($data, $request);
            $jenisIds = array_map('intval', $data['jenis_pelanggaran_ids'] ?? []);
            unset($data['jenis_pelanggaran_ids']);

            $data['nomor_laporan'] = $this->buatNomorLaporan($data['tanggal_kejadian']);
            $data['dibuat_oleh_pengguna_id'] = auth()->id();
            $data['status'] = 'baru';

            $laporan = LaporanPembinaanSiswa::create($data);
            $this->sinkronkanButirPelanggaran($laporan, $jenisIds);
            if ($laporan->jenis_laporan === 'pelanggaran') {
                $this->pengaturanBatasProses->tetapkanBatas($laporan);
            }
            $this->riwayatPembinaan->catat(
                $laporan,
                'laporan_dibuat',
                'Laporan dibuat',
                'Laporan awal dicatat dan siap ditindaklanjuti.',
                null,
                $laporan->status_verifikasi,
                $request->user()?->id,
            );
            $this->simpanSaksiAwal($laporan, $daftarSaksi, $request->user()?->id);
            $this->penyimpananBukti->simpanBanyak(
                $laporan,
                $request->file('bukti_laporan', []),
                $keteranganBukti,
                $request->user()?->id,
            );

            return $laporan;
        });

        $this->kirimNotifikasiLaporanBaru($request, $laporan);

        return redirect()
            ->route('laporan-pembinaan-siswa.show', $laporan)
            ->with('berhasil', $laporan->jenis_laporan === 'pelanggaran'
                ? 'Laporan pelanggaran berhasil diajukan untuk pemeriksaan BK.'
                : 'Catatan pembinaan siswa berhasil ditambahkan.');
    }

    public function show(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        $this->pastikanBolehAksesLaporan($request, $laporanPembinaanSiswa);

        $laporanPembinaanSiswa->load([
            'siswa', 'kategoriPembinaanSiswa', 'tahunPelajaran', 'kelas', 'anggotaKelas',
            'absensiSiswa', 'rentangPoinKeterlambatan',
            'pelaporPegawai', 'waliKelasPegawai', 'guruWaliPegawai', 'dibuatOlehPengguna',
            'butirPelanggaranLaporan',
            'verifikasiBkPelanggaran' => fn ($query) => $query->with(['bkPegawai', 'pengguna'])->latest('diverifikasi_pada'),
            'persetujuanPelanggaran' => fn ($query) => $query->with(['pegawai', 'pengguna'])->orderBy('jenis_persetujuan'),
            'tindakLanjutPembinaanSiswa' => fn ($query) => $query->with(['petugasPegawai', 'dibuatOlehPengguna'])
                ->orderByDesc('tanggal_tindak_lanjut')->orderByDesc('waktu_tindak_lanjut')->orderByDesc('id'),
            'buktiLaporanPembinaanSiswa' => fn ($query) => $query->with('diunggahOlehPengguna')->latest('diunggah_pada'),
            'saksiLaporanPembinaanSiswa' => fn ($query) => $query->with(['siswa', 'pegawai', 'dibuatOlehPengguna'])->oldest(),
            'klarifikasiSiswaPembinaan' => fn ($query) => $query->with('dicatatOlehPengguna')->latest('disampaikan_pada'),
            'riwayatProsesPembinaanSiswa' => fn ($query) => $query->with('pengguna')->latest('terjadi_pada')->latest('id'),
        ]);

        $bolehKelolaFakta = $this->aksesLaporan->bolehKelolaFakta($request->user(), $laporanPembinaanSiswa);
        $bolehMencatatKlarifikasi = $this->aksesLaporan->bolehMencatatKlarifikasi($request->user(), $laporanPembinaanSiswa);
        $daftarSiswaSaksi = $bolehKelolaFakta ? Siswa::where('aktif', true)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nisn']) : collect();
        $daftarPegawaiSaksi = $bolehKelolaFakta ? Pegawai::where('aktif', true)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nip']) : collect();
        $laporanMirip = LaporanPembinaanSiswa::query()
            ->with('kelas:id,nama')
            ->where('siswa_id', $laporanPembinaanSiswa->siswa_id)
            ->whereDate('tanggal_kejadian', $laporanPembinaanSiswa->tanggal_kejadian)
            ->whereKeyNot($laporanPembinaanSiswa->id)
            ->where('status_verifikasi', '!=', 'dibatalkan')
            ->latest('id')
            ->limit(5)
            ->get()
            ->filter(fn ($laporan) => $this->aksesLaporan->bolehLihat($request->user(), $laporan));

        return view('laporan-pembinaan-siswa.show', compact(
            'laporanPembinaanSiswa', 'bolehKelolaFakta', 'bolehMencatatKlarifikasi',
            'daftarSiswaSaksi', 'daftarPegawaiSaksi', 'laporanMirip',
        ));
    }

    public function edit(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        $this->pastikanBolehAksesLaporan($request, $laporanPembinaanSiswa);
        abort_if($laporanPembinaanSiswa->berasalDariAbsensi(), 422, 'Laporan otomatis diperbarui melalui koreksi rekap absensi.');
        abort_if(in_array($laporanPembinaanSiswa->status_verifikasi, ['disahkan', 'tidak_terbukti', 'dibatalkan'], true), 422, 'Laporan yang sudah diputuskan tidak dapat diedit.');

        $laporanPembinaanSiswa->load('butirPelanggaranLaporan');

        return view('laporan-pembinaan-siswa.edit', array_merge(
            compact('laporanPembinaanSiswa'),
            $this->pilihanForm($request),
        ));
    }

    public function update(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        $this->pastikanBolehAksesLaporan($request, $laporanPembinaanSiswa);
        abort_if($laporanPembinaanSiswa->berasalDariAbsensi(), 422, 'Laporan otomatis diperbarui melalui koreksi rekap absensi.');
        abort_if(in_array($laporanPembinaanSiswa->status_verifikasi, ['disahkan', 'tidak_terbukti', 'dibatalkan'], true), 422, 'Laporan yang sudah diputuskan tidak dapat diedit.');

        $data = $request->validate($this->aturanValidasi());
        DB::transaction(function () use ($request, $data, $laporanPembinaanSiswa) {
            $keteranganBukti = $data['keterangan_bukti'] ?? null;
            unset($data['daftar_saksi'], $data['bukti_laporan'], $data['keterangan_bukti']);
            $statusSebelum = $laporanPembinaanSiswa->status_verifikasi;
            $data = $this->rapikanData($data, $request, $laporanPembinaanSiswa);
            $jenisIds = array_map('intval', $data['jenis_pelanggaran_ids'] ?? []);
            unset($data['jenis_pelanggaran_ids']);

            $laporanPembinaanSiswa->update($data);
            $this->sinkronkanButirPelanggaran($laporanPembinaanSiswa, $jenisIds);

            if ($laporanPembinaanSiswa->jenis_laporan === 'pelanggaran') {
                $laporanPembinaanSiswa->verifikasiBkPelanggaran()->delete();
                $laporanPembinaanSiswa->persetujuanPelanggaran()->delete();
                $this->pengaturanBatasProses->tetapkanBatas($laporanPembinaanSiswa);
            }

            $this->riwayatPembinaan->catat(
                $laporanPembinaanSiswa,
                'laporan_diperbarui',
                'Laporan diperbarui',
                'Data pokok laporan diperbarui dan verifikasi pelanggaran dimulai kembali.',
                $statusSebelum,
                $laporanPembinaanSiswa->status_verifikasi,
                $request->user()?->id,
            );
            $this->penyimpananBukti->simpanBanyak(
                $laporanPembinaanSiswa,
                $request->file('bukti_laporan', []),
                $keteranganBukti,
                $request->user()?->id,
            );
        });

        return redirect()->route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa)
            ->with('berhasil', 'Laporan berhasil diperbarui dan proses verifikasi dimulai kembali.');
    }

    public function destroy(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        $this->pastikanBolehAksesLaporan($request, $laporanPembinaanSiswa);
        $this->prosesPoinSiswaService->batalkanPoinLaporan($laporanPembinaanSiswa);

        return redirect()->route('laporan-pembinaan-siswa.index')->with('berhasil', 'Laporan dibatalkan dan poin dikoreksi jika sebelumnya sudah ditetapkan.');
    }

    private function aturanValidasi(): array
    {
        return [
            'jenis_laporan' => ['required', Rule::in(array_keys(LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN))],
            'tanggal_kejadian' => ['required', 'date'],
            'waktu_kejadian' => ['nullable', 'date_format:H:i'],
            'tempat_kejadian' => ['nullable', 'string', 'max:150'],
            'siswa_id' => ['required', 'integer', Rule::exists('siswa', 'id')],
            'kategori_pembinaan_siswa_id' => ['nullable', 'integer', Rule::exists('kategori_pembinaan_siswa', 'id'), 'required_if:jenis_laporan,pembinaan'],
            'jenis_pelanggaran_ids' => ['nullable', 'array', 'min:1', 'required_if:jenis_laporan,pelanggaran'],
            'jenis_pelanggaran_ids.*' => ['integer', 'distinct', Rule::exists('jenis_pelanggaran_siswa', 'id')->where('aktif', true)],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'pelapor_pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')],
            'tingkat' => ['nullable', Rule::in(array_keys(LaporanPembinaanSiswa::DAFTAR_TINGKAT)), 'required_if:jenis_laporan,pembinaan'],
            'kronologi' => ['required', 'string'],
            'tindakan_awal' => ['nullable', 'string'],
            'catatan_rahasia' => ['nullable', 'string'],
            'bukti_laporan' => ['nullable', 'array', 'max:5'],
            'bukti_laporan.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'keterangan_bukti' => ['nullable', 'string', 'max:500'],
            'daftar_saksi' => ['nullable', 'array', 'max:10'],
            'daftar_saksi.*.jenis_saksi' => ['nullable', Rule::in(array_keys(SaksiLaporanPembinaanSiswa::DAFTAR_JENIS))],
            'daftar_saksi.*.nama_saksi' => ['nullable', 'string', 'max:160'],
            'daftar_saksi.*.pernyataan' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function rapikanData(array $data, Request $request, ?LaporanPembinaanSiswa $laporan = null): array
    {
        if ($laporan && ! ($request->user()?->administrator() || $request->user()?->memilikiPeran('bk'))) {
            $data['catatan_rahasia'] = $laporan->catatan_rahasia;
        }

        foreach (['waktu_kejadian', 'tempat_kejadian', 'tahun_pelajaran_id', 'kelas_id', 'pelapor_pegawai_id', 'tindakan_awal', 'catatan_rahasia'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? $data[$field] : null;
        }
        foreach (['tempat_kejadian', 'kronologi', 'tindakan_awal', 'catatan_rahasia'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }

        if (! $data['pelapor_pegawai_id'] && $request->user()?->pegawai_id) {
            $data['pelapor_pegawai_id'] = $request->user()->pegawai_id;
        }

        $data = $this->lengkapiKonteksKelas($data);

        if ($data['jenis_laporan'] === 'pelanggaran') {
            $jenis = JenisPelanggaranSiswa::query()
                ->whereIn('id', $data['jenis_pelanggaran_ids'] ?? [])
                ->where('aktif', true)
                ->get();
            $urutanTingkat = ['ringan' => 1, 'sedang' => 2, 'berat' => 3];
            $tingkatTertinggi = $jenis->sortByDesc(fn ($item) => $urutanTingkat[$item->tingkat] ?? 0)->first()?->tingkat ?? 'ringan';

            $data['kategori_pembinaan_siswa_id'] = $jenis->first()?->kategori_pembinaan_siswa_id;
            $data['tingkat'] = $tingkatTertinggi;
            $data['total_poin'] = (int) $jenis->sum('poin');
            $data['status_verifikasi'] = 'diajukan';
        } else {
            $data['jenis_pelanggaran_ids'] = [];
            $data['total_poin'] = 0;
            $data['status_verifikasi'] = 'tidak_perlu';
            $data['tahap_batas_proses'] = null;
            $data['batas_proses_pada'] = null;
        }

        if ($laporan && $laporan->status === 'dibatalkan') {
            $data['status'] = 'dibatalkan';
        }

        return $data;
    }

    private function sinkronkanButirPelanggaran(LaporanPembinaanSiswa $laporan, array $jenisIds): void
    {
        $laporan->butirPelanggaranLaporan()->delete();
        if ($laporan->jenis_laporan !== 'pelanggaran') {
            return;
        }

        JenisPelanggaranSiswa::whereIn('id', $jenisIds)->orderBy('urutan')->get()->each(function ($jenis) use ($laporan) {
            $laporan->butirPelanggaranLaporan()->create([
                'jenis_pelanggaran_siswa_id' => $jenis->id,
                'kode_pelanggaran' => $jenis->kode,
                'nama_pelanggaran' => $jenis->nama,
                'tingkat' => $jenis->tingkat,
                'poin' => $jenis->poin,
            ]);
        });
    }

    private function lengkapiKonteksKelas(array $data): array
    {
        $tahunPelajaranId = $data['tahun_pelajaran_id'] ?? null;
        if (! $tahunPelajaranId && filled($data['kelas_id'] ?? null)) {
            $tahunPelajaranId = Kelas::whereKey($data['kelas_id'])->value('tahun_pelajaran_id');
        }
        if (! $tahunPelajaranId) {
            $tahunPelajaranId = TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        }

        $data['tahun_pelajaran_id'] = $tahunPelajaranId ? (int) $tahunPelajaranId : null;
        $data['anggota_kelas_id'] = null;

        if (filled($data['siswa_id'] ?? null) && $data['tahun_pelajaran_id']) {
            $anggotaKelas = AnggotaKelas::query()
                ->where('siswa_id', $data['siswa_id'])
                ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
                ->where('status_keanggotaan', 'aktif')
                ->when(filled($data['kelas_id'] ?? null), fn ($query) => $query->where('kelas_id', $data['kelas_id']))
                ->first();

            if ($anggotaKelas) {
                $data['anggota_kelas_id'] = $anggotaKelas->id;
                $data['kelas_id'] = $anggotaKelas->kelas_id;
                $data['tahun_pelajaran_id'] = $anggotaKelas->tahun_pelajaran_id;
            }
        }

        $data['kelas_id'] = filled($data['kelas_id'] ?? null) ? (int) $data['kelas_id'] : null;
        $data['wali_kelas_pegawai_id'] = $data['kelas_id'] ? Kelas::whereKey($data['kelas_id'])->value('wali_kelas_id') : null;

        $tanggal = $data['tanggal_kejadian'] ?? now()->toDateString();
        $data['guru_wali_pegawai_id'] = PenugasanGuruWaliSiswa::query()
            ->where('siswa_id', $data['siswa_id'])
            ->where('tanggal_mulai', '<=', $tanggal)
            ->where(function ($query) use ($tanggal) {
                $query->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal);
            })
            ->latest('tanggal_mulai')
            ->value('guru_wali_pegawai_id');

        return $data;
    }

    private function buatNomorLaporan(string $tanggalKejadian): string
    {
        $prefix = 'PB-'.CarbonImmutable::parse($tanggalKejadian)->format('Ymd');
        $urut = LaporanPembinaanSiswa::where('nomor_laporan', 'like', $prefix.'-%')->count() + 1;
        do {
            $nomorLaporan = sprintf('%s-%04d', $prefix, $urut++);
        } while (LaporanPembinaanSiswa::where('nomor_laporan', $nomorLaporan)->exists());

        return $nomorLaporan;
    }

    private function pilihanFilter(Request $request): array
    {
        $kelasWaliIds = $request->user()?->kelasWaliIds() ?? [];
        $batasiKelas = ! $this->aksesPembinaanLuas($request) && $request->user()?->memilikiPeran('wali_kelas');

        return [
            'daftarKategoriPembinaan' => KategoriPembinaanSiswa::orderByDesc('aktif')->orderBy('nama')->get(),
            'daftarTingkat' => LaporanPembinaanSiswa::DAFTAR_TINGKAT,
            'daftarStatus' => LaporanPembinaanSiswa::DAFTAR_STATUS,
            'daftarJenisLaporan' => LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN,
            'daftarStatusVerifikasi' => LaporanPembinaanSiswa::DAFTAR_STATUS_VERIFIKASI,
            'daftarTahunPelajaran' => TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get(),
            'daftarKelas' => Kelas::with('tahunPelajaran:id,nama,aktif')
                ->when($batasiKelas, fn ($query) => $query->whereIn('id', $kelasWaliIds))
                ->orderBy('tingkat')->orderBy('nama')->get(),
        ];
    }

    private function pilihanForm(Request $request): array
    {
        return [
            'daftarKategoriPembinaan' => KategoriPembinaanSiswa::where('aktif', true)->orderBy('nama')->get(),
            'daftarJenisPelanggaran' => JenisPelanggaranSiswa::where('aktif', true)->orderBy('urutan')->get(),
            'daftarSiswa' => Siswa::with(['anggotaKelas' => fn ($query) => $query->where('status_keanggotaan', 'aktif')])
                ->where('aktif', true)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nis', 'nisn']),
            'daftarTahunPelajaran' => TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get(),
            'daftarKelas' => Kelas::with('tahunPelajaran:id,nama,aktif')->where('aktif', true)->orderBy('tingkat')->orderBy('nama')->get(),
            'daftarPegawai' => Pegawai::where('aktif', true)->orderBy('nama_lengkap')->get(),
            'daftarTingkat' => LaporanPembinaanSiswa::DAFTAR_TINGKAT,
            'daftarJenisLaporan' => LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN,
        ];
    }

    private function queryLaporanDalamCakupan(Request $request)
    {
        $query = LaporanPembinaanSiswa::query();
        $pengguna = $request->user();

        if ($this->aksesPembinaanLuas($request)) {
            return $query;
        }

        $kelasWaliIds = $pengguna?->kelasWaliIds() ?? [];
        $siswaWaliIds = $pengguna?->siswaWaliIds() ?? [];

        return $query->where(function ($query) use ($pengguna, $kelasWaliIds, $siswaWaliIds) {
            $query->where('dibuat_oleh_pengguna_id', $pengguna?->id)
                ->when($pengguna?->pegawai_id, fn ($query) => $query->orWhere('pelapor_pegawai_id', $pengguna->pegawai_id))
                ->when($kelasWaliIds !== [], fn ($query) => $query->orWhereIn('kelas_id', $kelasWaliIds))
                ->when($siswaWaliIds !== [], fn ($query) => $query->orWhereIn('siswa_id', $siswaWaliIds));
        });
    }

    private function pastikanBolehAksesLaporan(Request $request, LaporanPembinaanSiswa $laporan): void
    {
        $this->aksesLaporan->pastikanBolehLihat($request->user(), $laporan);
    }

    private function aksesPembinaanLuas(Request $request): bool
    {
        return $this->aksesLaporan->aksesLuas($request->user());
    }

    private function simpanSaksiAwal(LaporanPembinaanSiswa $laporan, array $daftarSaksi, ?int $penggunaId): void
    {
        $tersimpan = 0;
        foreach ($daftarSaksi as $index => $saksi) {
            $nama = trim((string) ($saksi['nama_saksi'] ?? ''));
            $pernyataan = trim((string) ($saksi['pernyataan'] ?? ''));
            if ($nama === '' && $pernyataan === '') {
                continue;
            }
            if ($nama === '' || $pernyataan === '') {
                throw ValidationException::withMessages([
                    "daftar_saksi.{$index}.nama_saksi" => 'Nama dan pernyataan saksi harus diisi lengkap.',
                ]);
            }

            $laporan->saksiLaporanPembinaanSiswa()->create([
                'jenis_saksi' => $saksi['jenis_saksi'] ?? 'lainnya',
                'nama_saksi' => $nama,
                'pernyataan' => $pernyataan,
                'dibuat_oleh_pengguna_id' => $penggunaId,
            ]);
            $tersimpan++;
        }

        if ($tersimpan > 0) {
            $this->riwayatPembinaan->catat(
                $laporan,
                'saksi_ditambahkan',
                'Pernyataan saksi ditambahkan',
                $tersimpan.' saksi awal dicatat bersama laporan.',
                $laporan->status_verifikasi,
                $laporan->status_verifikasi,
                $penggunaId,
                ['jumlah_saksi' => $tersimpan],
            );
        }
    }

    private function kirimNotifikasiLaporanBaru(Request $request, LaporanPembinaanSiswa $laporan): void
    {
        $laporan->loadMissing(['siswa', 'kelas', 'kategoriPembinaanSiswa']);
        $kodePeran = $laporan->jenis_laporan === 'pelanggaran'
            ? ['administrator', 'bk', 'wakil_pimpinan_kesiswaan']
            : ['administrator', 'bk'];

        $penerima = $this->notifikasiPenggunaService->penggunaDenganPeran($kodePeran, $request->user()?->id);
        $this->notifikasiPenggunaService->kirimKeBanyak(
            $penerima,
            $laporan->tingkat === 'berat' ? 'penting' : 'peringatan',
            $laporan->jenis_laporan === 'pelanggaran' ? 'Laporan pelanggaran menunggu pemeriksaan' : 'Catatan pembinaan siswa baru',
            sprintf('%s dari %s memiliki laporan %s.', $laporan->siswa?->nama_lengkap ?? 'Siswa', $laporan->kelas?->nama ?? 'kelas belum ditentukan', mb_strtolower($laporan->labelJenisLaporan())),
            route('laporan-pembinaan-siswa.show', $laporan, false),
            "laporan-pembinaan-baru:{$laporan->id}",
        );
    }

    private function inputId(Request $request, string $field): ?int
    {
        $value = $request->input($field);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
