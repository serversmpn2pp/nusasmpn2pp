<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Pegawai;
use App\Models\PeminjamanBarang;
use App\Models\SaldoStokBarang;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPeminjamanBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeminjamanBarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $jenisPeminjam = $this->pilihanValid($request->input('jenis_peminjam', 'semua'), array_merge(['semua'], array_keys(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM)));
        $status = $this->pilihanValid($request->input('status', 'semua'), array_merge(['semua'], array_keys(PeminjamanBarang::DAFTAR_STATUS)));
        $tanggalMulai = $request->input('tanggal_mulai');
        $tanggalSelesai = $request->input('tanggal_selesai');

        $request->validate([
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $peminjamanBarang = PeminjamanBarang::query()
            ->with(['siswa', 'pegawai', 'dibuatOleh'])
            ->withCount('detailPeminjamanBarang')
            ->when($jenisPeminjam !== 'semua', fn ($query) => $query->where('jenis_peminjam', $jenisPeminjam))
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->when($tanggalMulai, fn ($query) => $query->whereDate('tanggal_peminjaman', '>=', $tanggalMulai))
            ->when($tanggalSelesai, fn ($query) => $query->whereDate('tanggal_peminjaman', '<=', $tanggalSelesai))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nomor_peminjaman', 'ilike', '%'.$kataKunci.'%')
                        ->orWhereHas('siswa', function ($query) use ($kataKunci) {
                            $query->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                                ->orWhere('nisn', 'ilike', '%'.$kataKunci.'%');
                        })
                        ->orWhereHas('pegawai', function ($query) use ($kataKunci) {
                            $query->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                                ->orWhere('nip', 'ilike', '%'.$kataKunci.'%');
                        });
                });
            })
            ->orderByDesc('tanggal_peminjaman')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $hariIni = now()->toDateString();

        return view('peminjaman-barang.index', [
            'peminjamanBarang' => $peminjamanBarang,
            'kataKunci' => $kataKunci,
            'jenisPeminjam' => $jenisPeminjam,
            'status' => $status,
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'daftarJenisPeminjam' => PeminjamanBarang::DAFTAR_JENIS_PEMINJAM,
            'daftarStatus' => PeminjamanBarang::DAFTAR_STATUS,
            'jumlahTransaksi' => PeminjamanBarang::count(),
            'jumlahAktif' => PeminjamanBarang::whereIn('status', ['dipinjam', 'sebagian_dikembalikan'])->count(),
            'jumlahSelesai' => PeminjamanBarang::where('status', 'selesai')->count(),
            'jumlahHariIni' => PeminjamanBarang::whereDate('tanggal_peminjaman', $hariIni)->count(),
        ]);
    }

    public function create()
    {
        return view('peminjaman-barang.create', [
            'daftarSiswa' => $this->daftarSiswa(),
            'daftarPegawai' => $this->daftarPegawai(),
            'daftarItemManual' => $this->daftarItemManual(),
        ]);
    }

    public function store(Request $request, ProsesPeminjamanBarang $prosesPeminjaman)
    {
        $data = $this->rapikanData($request->validate([
            'jenis_peminjam' => ['required', Rule::in(array_keys(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM))],
            'siswa_id' => ['nullable', 'integer', 'exists:siswa,id'],
            'pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'cara_input_peminjam' => ['required', Rule::in(['manual', 'scan'])],
            'tanggal_peminjaman' => ['required', 'date'],
            'rencana_kembali' => ['nullable', 'date', 'after_or_equal:tanggal_peminjaman'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.tipe_item' => ['required', Rule::in(['unit', 'stok'])],
            'items.*.unit_barang_id' => ['nullable', 'integer', 'exists:unit_barang,id'],
            'items.*.barang_id' => ['nullable', 'integer', 'exists:barang,id'],
            'items.*.lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'items.*.jumlah' => ['nullable', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'items.*.cara_input_barang' => ['required', Rule::in(['manual', 'scan', 'campuran'])],
            'items.*.catatan' => ['nullable', 'string'],
            'items.*.label' => ['nullable', 'string', 'max:255'],
        ]));

        $peminjamanBarang = $prosesPeminjaman->catat($data, $request->user()?->id);

        return redirect()
            ->route('peminjaman-barang.show', $peminjamanBarang)
            ->with('berhasil', 'Transaksi peminjaman barang berhasil dicatat.');
    }

    public function show(PeminjamanBarang $peminjamanBarang)
    {
        $peminjamanBarang->load([
            'siswa',
            'pegawai',
            'dibuatOleh',
            'detailPeminjamanBarang.barang.satuanBarang',
            'detailPeminjamanBarang.unitBarang',
            'detailPeminjamanBarang.lokasiBarang',
            'pengembalianBarang.dibuatOleh',
            'pengembalianBarang.detailPengembalianBarang.detailPeminjamanBarang.barang.satuanBarang',
        ]);

        return view('peminjaman-barang.show', compact('peminjamanBarang'));
    }

    public function identifikasiPeminjam(Request $request)
    {
        $data = $request->validate([
            'jenis_peminjam' => ['nullable', Rule::in(array_merge(['otomatis'], array_keys(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM)))],
            'kode' => ['required', 'string', 'max:100'],
        ]);
        $kode = preg_replace('/\s+/', '', trim($data['kode']));
        $jenisPeminjam = $data['jenis_peminjam'] ?? 'otomatis';

        $siswa = $jenisPeminjam !== 'pegawai'
            ? $this->cariSiswaDariKode($kode)
            : null;
        $pegawai = $jenisPeminjam !== 'siswa'
            ? Pegawai::query()->where('aktif', true)->where('nip', $kode)->first()
            : null;

        if ($siswa && $pegawai) {
            return response()->json([
                'pesan' => 'Nomor kartu ditemukan pada siswa dan pegawai. Pilih jenis peminjam lalu gunakan pilihan manual.',
            ], 422);
        }

        if ($siswa) {
            return response()->json([
                'jenis_peminjam' => 'siswa',
                'id' => $siswa->id,
                'nama' => $siswa->nama_lengkap,
                'identitas' => 'NISN '.($siswa->nisn ?: '-'),
                'informasi' => $this->namaKelasAktif($siswa),
            ]);
        }

        if ($pegawai) {
            return response()->json([
                'jenis_peminjam' => 'pegawai',
                'id' => $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'identitas' => 'NIP '.($pegawai->nip ?: '-'),
                'informasi' => $pegawai->jenis_pegawai ?: 'Pegawai',
            ]);
        }

        $pesan = match ($jenisPeminjam) {
            'siswa' => 'Siswa dengan NISN atau NIS tersebut tidak ditemukan.',
            'pegawai' => 'Pegawai dengan NIP tersebut tidak ditemukan.',
            default => 'Kartu tidak ditemukan sebagai siswa maupun pegawai aktif.',
        };

        return response()->json(['pesan' => $pesan], 422);
    }

    public function identifikasiBarang(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:120'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
        ]);
        $kode = strtoupper(trim($data['kode']));

        $unitBarang = UnitBarang::query()
            ->with(['barang.satuanBarang', 'lokasiBarang'])
            ->whereRaw('LOWER(kode_inventaris) = ?', [strtolower($kode)])
            ->first();

        if ($unitBarang) {
            if (! $unitBarang->aktif || $unitBarang->status_unit !== 'tersedia' || $unitBarang->barang?->tipe_pengelolaan !== 'aset_individual') {
                return response()->json(['pesan' => 'Unit aset tersebut sedang tidak tersedia untuk dipinjam.'], 422);
            }

            return response()->json(['item' => $this->formatUnitBarang($unitBarang)]);
        }

        $barang = Barang::query()
            ->with('satuanBarang')
            ->where('aktif', true)
            ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
            ->whereRaw('LOWER(kode) = ?', [strtolower($kode)])
            ->first();

        if (! $barang) {
            $pesan = str_starts_with($kode, 'AST-')
                ? 'Barcode aset tidak ditemukan. Pastikan label AST sudah terdaftar dan terbaca lengkap.'
                : (str_starts_with($kode, 'BHP-')
                    ? 'Barcode barang habis pakai tidak ditemukan.'
                    : 'Barcode atau kode barang tidak ditemukan.');

            return response()->json(['pesan' => $pesan], 422);
        }

        $saldoStok = SaldoStokBarang::query()
            ->with(['barang.satuanBarang', 'lokasiBarang'])
            ->where('barang_id', $barang->id)
            ->where('jumlah', '>', 0)
            ->when($data['lokasi_barang_id'] ?? null, fn ($query, $lokasiId) => $query->where('lokasi_barang_id', $lokasiId))
            ->orderBy('lokasi_barang_id')
            ->get();

        if ($saldoStok->isEmpty()) {
            return response()->json(['pesan' => 'Stok barang tersebut sedang habis pada lokasi yang dipilih.'], 422);
        }

        if ($saldoStok->count() > 1 && blank($data['lokasi_barang_id'] ?? null)) {
            return response()->json([
                'perlu_pilih_lokasi' => true,
                'pesan' => 'Barang tersimpan di beberapa lokasi. Pilih lokasi asal, lalu lanjutkan scan.',
                'pilihan_lokasi' => $saldoStok->map(fn (SaldoStokBarang $saldo) => [
                    'id' => $saldo->lokasi_barang_id,
                    'nama' => $saldo->lokasiBarang->nama,
                    'saldo' => (float) $saldo->jumlah,
                    'satuan' => $barang->satuanBarang->nama,
                ])->values(),
            ]);
        }

        return response()->json(['item' => $this->formatStokBarang($saldoStok->first())]);
    }

    private function daftarSiswa()
    {
        $tahunPelajaranId = TahunPelajaran::query()->where('aktif', true)->value('id');

        return Siswa::query()
            ->where('aktif', true)
            ->with(['anggotaKelas' => function ($query) use ($tahunPelajaranId) {
                $query->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
                    ->where('status_keanggotaan', 'aktif')
                    ->with('kelas');
            }])
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn (Siswa $siswa) => [
                'id' => $siswa->id,
                'label' => $siswa->nama_lengkap.' - NISN '.($siswa->nisn ?: '-').' - '.($siswa->anggotaKelas->first()?->kelas?->nama ?: 'Belum ditempatkan'),
            ]);
    }

    private function daftarPegawai()
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn (Pegawai $pegawai) => [
                'id' => $pegawai->id,
                'label' => $pegawai->nama_lengkap.' - NIP '.($pegawai->nip ?: '-'),
            ]);
    }

    private function daftarItemManual()
    {
        $unitBarang = UnitBarang::query()
            ->with(['barang.satuanBarang', 'lokasiBarang'])
            ->where('aktif', true)
            ->where('status_unit', 'tersedia')
            ->whereHas('barang', fn ($query) => $query->where('aktif', true)->where('tipe_pengelolaan', 'aset_individual'))
            ->orderBy('kode_inventaris')
            ->get()
            ->map(fn (UnitBarang $unitBarang) => $this->formatUnitBarang($unitBarang));

        $saldoStokBarang = SaldoStokBarang::query()
            ->with(['barang.satuanBarang', 'lokasiBarang'])
            ->where('jumlah', '>', 0)
            ->whereHas('barang', fn ($query) => $query->where('aktif', true)->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai']))
            ->orderBy('barang_id')
            ->orderBy('lokasi_barang_id')
            ->get()
            ->map(fn (SaldoStokBarang $saldoStokBarang) => $this->formatStokBarang($saldoStokBarang));

        return $unitBarang->concat($saldoStokBarang)->values();
    }

    private function formatUnitBarang(UnitBarang $unitBarang): array
    {
        return [
            'kunci' => 'unit:'.$unitBarang->id,
            'tipe_item' => 'unit',
            'unit_barang_id' => $unitBarang->id,
            'barang_id' => $unitBarang->barang_id,
            'lokasi_barang_id' => $unitBarang->lokasi_barang_id,
            'kode' => $unitBarang->kode_inventaris,
            'label' => $unitBarang->barang->nama,
            'keterangan' => $unitBarang->kode_inventaris
                .($unitBarang->nomor_aset_resmi ? ' - Aset '.$unitBarang->nomor_aset_resmi : '')
                .' - '.($unitBarang->lokasiBarang?->nama ?: 'Tanpa lokasi'),
            'jenis_tampilan' => 'Aset individual',
            'kelompok' => 'Aset individual (wajib kembali)',
            'wajib_dikembalikan' => true,
            'satuan' => 'unit',
            'saldo' => 1,
        ];
    }

    private function formatStokBarang(SaldoStokBarang $saldoStokBarang): array
    {
        $barang = $saldoStokBarang->barang;
        $habisPakai = $barang->tipe_pengelolaan === 'habis_pakai';

        return [
            'kunci' => 'stok:'.$saldoStokBarang->barang_id.':'.$saldoStokBarang->lokasi_barang_id,
            'tipe_item' => 'stok',
            'unit_barang_id' => null,
            'barang_id' => $saldoStokBarang->barang_id,
            'lokasi_barang_id' => $saldoStokBarang->lokasi_barang_id,
            'kode' => $barang->kode,
            'label' => $barang->nama,
            'keterangan' => $barang->kode.' - '.$saldoStokBarang->lokasiBarang->nama.' - tersedia '.number_format((float) $saldoStokBarang->jumlah, 2, ',', '.').' '.$barang->satuanBarang->nama,
            'jenis_tampilan' => $habisPakai ? 'Barang habis pakai' : 'Stok yang dikembalikan',
            'kelompok' => $habisPakai ? 'Barang habis pakai' : 'Stok yang wajib dikembalikan',
            'wajib_dikembalikan' => ! $habisPakai,
            'satuan' => $barang->satuanBarang->nama,
            'saldo' => (float) $saldoStokBarang->jumlah,
        ];
    }

    private function cariSiswaDariKode(string $kode): ?Siswa
    {
        return Siswa::query()
            ->where('aktif', true)
            ->where(function ($query) use ($kode) {
                $query->where('nisn', $kode)->orWhere('nis', $kode);
            })
            ->first();
    }

    private function namaKelasAktif(Siswa $siswa): string
    {
        $tahunPelajaranId = TahunPelajaran::query()->where('aktif', true)->value('id');

        if (! $tahunPelajaranId) {
            return 'Belum ditempatkan';
        }

        return $siswa->anggotaKelas()
            ->with('kelas')
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('status_keanggotaan', 'aktif')
            ->first()?->kelas?->nama ?: 'Belum ditempatkan';
    }

    private function rapikanData(array $data): array
    {
        $data['catatan'] = filled($data['catatan'] ?? null) ? trim($data['catatan']) : null;

        foreach ($data['items'] ?? [] as &$item) {
            $item['catatan'] = filled($item['catatan'] ?? null) ? trim($item['catatan']) : null;
        }

        return $data;
    }

    private function pilihanValid(mixed $nilai, array $daftar): string
    {
        return in_array($nilai, $daftar, true) ? $nilai : 'semua';
    }
}
