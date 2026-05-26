<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaporanPembinaanSiswaController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $request->input('status', 'semua');
        $tingkat = $request->input('tingkat', 'semua');
        $kategoriDipilih = $this->inputId($request, 'kategori_pembinaan_siswa_id');
        $tahunPelajaranDipilih = $this->inputId($request, 'tahun_pelajaran_id');
        $kelasDipilih = $this->inputId($request, 'kelas_id');

        if (! array_key_exists($status, LaporanPembinaanSiswa::DAFTAR_STATUS) && $status !== 'semua') {
            $status = 'semua';
        }

        if (! array_key_exists($tingkat, LaporanPembinaanSiswa::DAFTAR_TINGKAT) && $tingkat !== 'semua') {
            $tingkat = 'semua';
        }

        $laporanPembinaanSiswa = LaporanPembinaanSiswa::query()
            ->with(['siswa', 'kategoriPembinaanSiswa', 'tahunPelajaran', 'kelas', 'pelaporPegawai'])
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->when($tingkat !== 'semua', fn ($query) => $query->where('tingkat', $tingkat))
            ->when($kategoriDipilih, fn ($query) => $query->where('kategori_pembinaan_siswa_id', $kategoriDipilih))
            ->when($tahunPelajaranDipilih, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranDipilih))
            ->when($kelasDipilih, fn ($query) => $query->where('kelas_id', $kelasDipilih))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nomor_laporan', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('tempat_kejadian', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('kronologi', 'ilike', '%' . $kataKunci . '%')
                        ->orWhereHas('siswa', function ($query) use ($kataKunci) {
                            $query->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                                ->orWhere('nis', 'ilike', '%' . $kataKunci . '%')
                                ->orWhere('nisn', 'ilike', '%' . $kataKunci . '%');
                        })
                        ->orWhereHas('kategoriPembinaanSiswa', function ($query) use ($kataKunci) {
                            $query->where('nama', 'ilike', '%' . $kataKunci . '%')
                                ->orWhere('kode', 'ilike', '%' . $kataKunci . '%');
                        })
                        ->orWhereHas('pelaporPegawai', function ($query) use ($kataKunci) {
                            $query->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                                ->orWhere('nip', 'ilike', '%' . $kataKunci . '%');
                        });
                });
            })
            ->orderByDesc('tanggal_kejadian')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $ringkasan = [
            'total' => LaporanPembinaanSiswa::count(),
            'baru' => LaporanPembinaanSiswa::where('status', 'baru')->count(),
            'diproses' => LaporanPembinaanSiswa::where('status', 'diproses')->count(),
            'tindak_lanjut' => LaporanPembinaanSiswa::where('status', 'perlu_tindak_lanjut')->count(),
            'selesai' => LaporanPembinaanSiswa::where('status', 'selesai')->count(),
        ];

        return view('laporan-pembinaan-siswa.index', array_merge(
            compact(
                'laporanPembinaanSiswa',
                'kataKunci',
                'status',
                'tingkat',
                'kategoriDipilih',
                'tahunPelajaranDipilih',
                'kelasDipilih',
                'ringkasan',
            ),
            $this->pilihanFilter(),
        ));
    }

    public function create()
    {
        $laporanPembinaanSiswa = new LaporanPembinaanSiswa([
            'tanggal_kejadian' => now()->toDateString(),
            'tingkat' => 'ringan',
            'status' => 'baru',
            'tahun_pelajaran_id' => TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id'),
        ]);

        return view('laporan-pembinaan-siswa.create', array_merge(
            compact('laporanPembinaanSiswa'),
            $this->pilihanForm(),
        ));
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['nomor_laporan'] = $this->buatNomorLaporan($data['tanggal_kejadian']);
        $data['dibuat_oleh_pengguna_id'] = auth()->id();

        $laporanPembinaanSiswa = LaporanPembinaanSiswa::create($data);

        return redirect()
            ->route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa)
            ->with('berhasil', 'Laporan pembinaan siswa berhasil ditambahkan.');
    }

    public function show(LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        $laporanPembinaanSiswa->load([
            'siswa',
            'kategoriPembinaanSiswa',
            'tahunPelajaran',
            'kelas',
            'anggotaKelas',
            'pelaporPegawai',
            'dibuatOlehPengguna',
            'tindakLanjutPembinaanSiswa' => function ($query) {
                $query->with(['petugasPegawai', 'dibuatOlehPengguna'])
                    ->orderByDesc('tanggal_tindak_lanjut')
                    ->orderByDesc('waktu_tindak_lanjut')
                    ->orderByDesc('id');
            },
        ]);

        return view('laporan-pembinaan-siswa.show', compact('laporanPembinaanSiswa'));
    }

    public function edit(LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        return view('laporan-pembinaan-siswa.edit', array_merge(
            compact('laporanPembinaanSiswa'),
            $this->pilihanForm(),
        ));
    }

    public function update(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));

        $laporanPembinaanSiswa->update($data);

        return redirect()
            ->route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa)
            ->with('berhasil', 'Laporan pembinaan siswa berhasil diperbarui.');
    }

    public function destroy(LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        $laporanPembinaanSiswa->update(['status' => 'dibatalkan']);

        return redirect()
            ->route('laporan-pembinaan-siswa.index')
            ->with('berhasil', 'Laporan pembinaan siswa berhasil dibatalkan.');
    }

    private function aturanValidasi(): array
    {
        return [
            'tanggal_kejadian' => ['required', 'date'],
            'waktu_kejadian' => ['nullable', 'date_format:H:i'],
            'tempat_kejadian' => ['nullable', 'string', 'max:150'],
            'siswa_id' => ['required', 'integer', Rule::exists('siswa', 'id')],
            'kategori_pembinaan_siswa_id' => ['required', 'integer', Rule::exists('kategori_pembinaan_siswa', 'id')],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'pelapor_pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')],
            'tingkat' => ['required', Rule::in(array_keys(LaporanPembinaanSiswa::DAFTAR_TINGKAT))],
            'status' => ['required', Rule::in(array_keys(LaporanPembinaanSiswa::DAFTAR_STATUS))],
            'kronologi' => ['required', 'string'],
            'tindakan_awal' => ['nullable', 'string'],
            'catatan_rahasia' => ['nullable', 'string'],
        ];
    }

    private function rapikanData(array $data): array
    {
        foreach (['waktu_kejadian', 'tempat_kejadian', 'tahun_pelajaran_id', 'kelas_id', 'pelapor_pegawai_id', 'tindakan_awal', 'catatan_rahasia'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? $data[$field] : null;
        }

        foreach (['tempat_kejadian', 'kronologi', 'tindakan_awal', 'catatan_rahasia'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }

        return $this->lengkapiKonteksKelas($data);
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

        return $data;
    }

    private function buatNomorLaporan(string $tanggalKejadian): string
    {
        $prefix = 'PB-' . CarbonImmutable::parse($tanggalKejadian)->format('Ymd');
        $urut = LaporanPembinaanSiswa::where('nomor_laporan', 'like', $prefix . '-%')->count() + 1;

        do {
            $nomorLaporan = sprintf('%s-%04d', $prefix, $urut);
            $urut++;
        } while (LaporanPembinaanSiswa::where('nomor_laporan', $nomorLaporan)->exists());

        return $nomorLaporan;
    }

    private function inputId(Request $request, string $field): ?int
    {
        $value = $request->input($field);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function pilihanFilter(): array
    {
        return [
            'daftarKategoriPembinaan' => KategoriPembinaanSiswa::orderByDesc('aktif')->orderBy('nama')->get(['id', 'nama', 'kode', 'aktif']),
            'daftarTingkat' => LaporanPembinaanSiswa::DAFTAR_TINGKAT,
            'daftarStatus' => LaporanPembinaanSiswa::DAFTAR_STATUS,
            'daftarTahunPelajaran' => TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get(['id', 'nama', 'aktif']),
            'daftarKelas' => Kelas::with('tahunPelajaran:id,nama,aktif')
                ->when(request('tahun_pelajaran_id'), fn ($query) => $query->where('tahun_pelajaran_id', request('tahun_pelajaran_id')))
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat', 'aktif']),
        ];
    }

    private function pilihanForm(): array
    {
        return [
            'daftarKategoriPembinaan' => KategoriPembinaanSiswa::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'kode']),
            'daftarSiswa' => Siswa::with([
                'anggotaKelas' => function ($query) {
                    $query->where('status_keanggotaan', 'aktif')
                        ->select('id', 'tahun_pelajaran_id', 'kelas_id', 'siswa_id', 'status_keanggotaan');
                },
            ])
                ->where('aktif', true)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nis', 'nisn']),
            'daftarTahunPelajaran' => TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get(['id', 'nama', 'aktif']),
            'daftarKelas' => Kelas::with('tahunPelajaran:id,nama,aktif')
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat', 'aktif']),
            'daftarPegawai' => Pegawai::where('aktif', true)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama']),
            'daftarTingkat' => LaporanPembinaanSiswa::DAFTAR_TINGKAT,
            'daftarStatus' => LaporanPembinaanSiswa::DAFTAR_STATUS,
        ];
    }
}
