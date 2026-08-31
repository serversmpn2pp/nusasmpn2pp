<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\LokasiBarang;
use App\Models\PengaturanInventaris;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\GeneratorIdentitasInventaris;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitBarangController extends Controller
{
    public function __construct(private GeneratorIdentitasInventaris $generatorIdentitas) {}

    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $this->pilihanValid($request->input('status', 'semua'), ['semua', 'aktif', 'nonaktif']);
        $kondisi = $this->pilihanValid($request->input('kondisi', 'semua'), array_merge(['semua'], array_keys(UnitBarang::DAFTAR_KONDISI)));
        $statusUnit = $this->pilihanValid($request->input('status_unit', 'semua'), array_merge(['semua'], array_keys(UnitBarang::DAFTAR_STATUS)));
        $barangId = $request->input('barang_id', 'semua');
        $lokasiBarangId = $request->input('lokasi_barang_id', 'semua');

        $unitBarang = UnitBarang::query()
            ->with(['barang.kategoriBarang', 'lokasiBarang', 'sumberPerolehanBarang'])
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($kondisi !== 'semua', fn ($query) => $query->where('kondisi', $kondisi))
            ->when($statusUnit !== 'semua', fn ($query) => $query->where('status_unit', $statusUnit))
            ->when($barangId !== 'semua', fn ($query) => $query->where('barang_id', $barangId))
            ->when($lokasiBarangId !== 'semua', fn ($query) => $query->where('lokasi_barang_id', $lokasiBarangId))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('kode_inventaris', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('nomor_aset_resmi', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('nomor_seri', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('merek', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('tipe', 'ilike', '%'.$kataKunci.'%')
                        ->orWhereHas('barang', fn ($query) => $query->where('nama', 'ilike', '%'.$kataKunci.'%'));
                });
            })
            ->orderByDesc('aktif')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('unit-barang.index', [
            'unitBarang' => $unitBarang,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'kondisi' => $kondisi,
            'statusUnit' => $statusUnit,
            'barangId' => $barangId,
            'lokasiBarangId' => $lokasiBarangId,
            'daftarKondisi' => UnitBarang::DAFTAR_KONDISI,
            'daftarStatusUnit' => UnitBarang::DAFTAR_STATUS,
            'daftarBarang' => $this->daftarBarang(),
            'daftarLokasi' => LokasiBarang::orderBy('nama')->get(),
            'jumlahUnit' => UnitBarang::count(),
            'jumlahAktif' => UnitBarang::where('aktif', true)->count(),
            'jumlahTersedia' => UnitBarang::where('aktif', true)->where('status_unit', 'tersedia')->count(),
            'jumlahPerluPerhatian' => UnitBarang::where('aktif', true)
                ->whereIn('status_unit', ['dalam_perbaikan', 'hilang'])
                ->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('unit-barang.create', array_merge(
            $this->pilihanForm(),
            ['barangTerpilihId' => $request->integer('barang_id') ?: null],
        ));
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData(
            $request->validate($this->aturanValidasi(true)),
            $request->boolean('aktif'),
        );
        $jumlahUnit = (int) $data['jumlah_unit'];
        unset($data['jumlah_unit']);

        if ($jumlahUnit > 1 && filled($data['nomor_seri'])) {
            throw ValidationException::withMessages([
                'nomor_seri' => 'Nomor seri hanya dapat langsung diisi jika menambahkan satu unit. Tambahkan nomor seri masing-masing unit melalui halaman edit.',
            ]);
        }

        $unitPertama = DB::transaction(function () use ($data, $jumlahUnit) {
            $barang = Barang::query()->lockForUpdate()->findOrFail($data['barang_id']);
            $this->pastikanBarangAsetIndividual($barang);
            $nomorTerakhir = (int) UnitBarang::where('barang_id', $barang->id)->max('nomor_unit');
            $unitPertama = null;
            $data['lokasi_barang_id'] ??= $barang->lokasi_penyimpanan_id;

            for ($urutan = 1; $urutan <= $jumlahUnit; $urutan++) {
                $nomorUnit = $nomorTerakhir + $urutan;
                $unit = UnitBarang::create(array_merge($data, [
                    'nomor_unit' => $nomorUnit,
                    'kode_inventaris' => $this->generatorIdentitas->buatKodeUnitAset((int) $data['tahun_perolehan']),
                    'nomor_aset_resmi' => $this->generatorIdentitas->buatNomorAsetResmi((int) $data['tahun_perolehan']),
                ]));
                $unitPertama ??= $unit;
            }

            return $unitPertama;
        });

        $pesan = $jumlahUnit === 1
            ? 'Unit aset berhasil ditambahkan.'
            : $jumlahUnit.' unit aset berhasil ditambahkan.';

        return redirect()
            ->route('unit-barang.show', $unitPertama)
            ->with('berhasil', $pesan);
    }

    public function show(UnitBarang $unitBarang)
    {
        $unitBarang->load([
            'barang.kategoriBarang',
            'barang.satuanBarang',
            'lokasiBarang',
            'sumberPerolehanBarang',
            'detailPenerimaanBarang.penerimaanBarang.sumberPerolehanBarang',
            'detailPenerimaanBarang.penerimaanBarang.dibuatOleh',
            'detailPenerimaanBarang.lokasiBarang',
            'detailPeminjamanBarang.peminjamanBarang.siswa',
            'detailPeminjamanBarang.peminjamanBarang.pegawai',
            'detailPeminjamanBarang.peminjamanBarang.dibuatOleh',
            'detailPeminjamanBarang.detailPengembalianBarang.pengembalianBarang.dibuatOleh',
        ]);

        $detailPeminjamanAktif = $unitBarang->detailPeminjamanBarang
            ->sortByDesc('id')
            ->first(fn ($detail) => $detail->peminjamanBarang?->masihAktif()
                && $detail->jumlahBelumDikembalikan() > 0);

        return view('unit-barang.show', [
            'unitBarang' => $unitBarang,
            'detailPeminjamanAktif' => $detailPeminjamanAktif,
            'riwayatUnit' => $this->susunRiwayatUnit($unitBarang),
        ]);
    }

    public function edit(UnitBarang $unitBarang)
    {
        return view('unit-barang.edit', array_merge(
            compact('unitBarang'),
            $this->pilihanForm($unitBarang),
        ));
    }

    public function update(Request $request, UnitBarang $unitBarang)
    {
        $data = $this->rapikanData(
            $request->validate($this->aturanValidasi()),
            $request->boolean('aktif'),
        );
        unset($data['jumlah_unit']);

        if (filled($data['tahun_perolehan'] ?? null)) {
            $data['nomor_aset_resmi'] = $this->generatorIdentitas->buatNomorAsetResmi((int) $data['tahun_perolehan']);
        }

        $unitBarang->update($data);

        return redirect()
            ->route('unit-barang.show', $unitBarang)
            ->with('berhasil', 'Unit aset berhasil diperbarui.');
    }

    public function destroy(UnitBarang $unitBarang)
    {
        $unitBarang->update(['aktif' => false]);

        return redirect()
            ->route('unit-barang.index')
            ->with('berhasil', 'Unit aset berhasil dinonaktifkan.');
    }

    private function pilihanForm(?UnitBarang $unitBarang = null): array
    {
        return [
            'daftarBarang' => $this->daftarBarang(aktifSaja: true),
            'daftarLokasi' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarSumberPerolehan' => SumberPerolehanBarang::query()
                ->where(function ($query) use ($unitBarang) {
                    $query->where('aktif', true)
                        ->when(
                            $unitBarang?->sumber_perolehan_barang_id,
                            fn ($query, $sumberId) => $query->orWhere('id', $sumberId),
                        );
                })
                ->orderByDesc('aktif')
                ->orderBy('nama')
                ->get(),
            'pengaturanInventaris' => PengaturanInventaris::utama(),
            'daftarKondisi' => UnitBarang::DAFTAR_KONDISI,
            'daftarStatusUnit' => UnitBarang::DAFTAR_STATUS,
        ];
    }

    private function daftarBarang(bool $aktifSaja = false)
    {
        return Barang::query()
            ->where('tipe_pengelolaan', 'aset_individual')
            ->when($aktifSaja, fn ($query) => $query->where('aktif', true))
            ->orderBy('nama')
            ->get();
    }

    private function aturanValidasi(bool $tambah = false): array
    {
        return [
            'barang_id' => [$tambah ? 'required' : 'sometimes', 'integer', 'exists:barang,id'],
            'jumlah_unit' => [$tambah ? 'required' : 'sometimes', 'integer', 'min:1', 'max:100'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'nomor_seri' => ['nullable', 'string', 'max:120'],
            'kondisi' => ['required', Rule::in(array_keys(UnitBarang::DAFTAR_KONDISI))],
            'status_unit' => ['required', Rule::in(array_keys(UnitBarang::DAFTAR_STATUS))],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tahun_perolehan' => [$tambah ? 'required' : 'nullable', 'integer', 'min:1900', 'max:2100'],
            'sumber_perolehan_barang_id' => [$tambah ? 'required' : 'nullable', 'integer', 'exists:sumber_perolehan_barang,id'],
            'merek' => ['nullable', 'string', 'max:120'],
            'tipe' => ['nullable', 'string', 'max:120'],
            'harga_perolehan' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'keterangan' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    private function rapikanData(array $data, bool $aktif): array
    {
        $data['lokasi_barang_id'] = filled($data['lokasi_barang_id'] ?? null) ? $data['lokasi_barang_id'] : null;
        $data['nomor_seri'] = filled($data['nomor_seri'] ?? null) ? trim($data['nomor_seri']) : null;
        $data['merek'] = filled($data['merek'] ?? null) ? trim($data['merek']) : null;
        $data['tipe'] = filled($data['tipe'] ?? null) ? trim($data['tipe']) : null;
        $data['harga_perolehan'] = filled($data['harga_perolehan'] ?? null) ? $data['harga_perolehan'] : null;
        $data['keterangan'] = filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null;
        $data['aktif'] = $aktif;

        if (
            filled($data['tanggal_perolehan'] ?? null)
            && filled($data['tahun_perolehan'] ?? null)
            && (int) substr($data['tanggal_perolehan'], 0, 4) !== (int) $data['tahun_perolehan']
        ) {
            throw ValidationException::withMessages([
                'tahun_perolehan' => 'Tahun perolehan harus sama dengan tahun pada tanggal perolehan.',
            ]);
        }

        if (filled($data['sumber_perolehan_barang_id'] ?? null)) {
            $sumber = SumberPerolehanBarang::findOrFail($data['sumber_perolehan_barang_id']);
            $data['sumber_perolehan'] = $sumber->nama;
        } else {
            unset($data['sumber_perolehan_barang_id'], $data['tahun_perolehan']);
        }

        return $data;
    }

    private function pastikanBarangAsetIndividual(Barang $barang): void
    {
        if ($barang->tipe_pengelolaan !== 'aset_individual') {
            throw ValidationException::withMessages([
                'barang_id' => 'Unit inventaris hanya dapat dibuat untuk barang dengan tipe aset individual.',
            ]);
        }
    }

    private function susunRiwayatUnit(UnitBarang $unitBarang)
    {
        $riwayat = collect();
        $detailPenerimaan = $unitBarang->detailPenerimaanBarang;
        $penerimaan = $detailPenerimaan?->penerimaanBarang;

        if ($penerimaan) {
            $riwayat->push([
                'jenis' => 'penerimaan',
                'label' => 'Penerimaan',
                'judul' => 'Aset diterima dan dicatat',
                'keterangan' => collect([
                    $penerimaan->nomor_penerimaan,
                    $penerimaan->asal_barang ? 'Dari '.$penerimaan->asal_barang : null,
                ])->filter()->join(' - '),
                'tanggal' => $penerimaan->tanggal_penerimaan,
                'kunci_urut' => $this->kunciUrutRiwayat($penerimaan->tanggal_penerimaan, $penerimaan->created_at),
                'meta' => array_filter([
                    'Sumber' => $penerimaan->sumberPerolehanBarang?->nama,
                    'Lokasi awal' => $detailPenerimaan->lokasiBarang?->nama,
                    'Kondisi awal' => UnitBarang::DAFTAR_KONDISI[$detailPenerimaan->kondisi] ?? null,
                    'Petugas' => $penerimaan->dibuatOleh?->nama,
                ]),
                'tautan' => route('penerimaan-barang.show', $penerimaan),
                'label_tautan' => 'Lihat penerimaan',
            ]);
        } else {
            $tanggalPencatatan = $unitBarang->tanggal_perolehan ?: $unitBarang->created_at;
            $riwayat->push([
                'jenis' => 'pencatatan',
                'label' => 'Pencatatan',
                'judul' => 'Unit aset dicatat di NUSA',
                'keterangan' => 'Riwayat penerimaan terperinci belum tersedia untuk unit ini.',
                'tanggal' => $tanggalPencatatan,
                'kunci_urut' => $this->kunciUrutRiwayat($tanggalPencatatan, $unitBarang->created_at),
                'meta' => array_filter([
                    'Sumber' => $unitBarang->sumberPerolehanBarang?->nama ?: $unitBarang->sumber_perolehan,
                    'Lokasi awal' => $unitBarang->lokasiBarang?->nama,
                    'Kondisi awal' => $unitBarang->labelKondisi(),
                ]),
                'tautan' => null,
                'label_tautan' => null,
            ]);
        }

        foreach ($unitBarang->detailPeminjamanBarang as $detailPeminjaman) {
            $peminjaman = $detailPeminjaman->peminjamanBarang;

            if (! $peminjaman) {
                continue;
            }

            $riwayat->push([
                'jenis' => 'peminjaman',
                'label' => 'Peminjaman',
                'judul' => 'Dipinjam oleh '.$peminjaman->namaPeminjam(),
                'keterangan' => $detailPeminjaman->catatan ?: 'Aset diserahkan kepada peminjam.',
                'tanggal' => $peminjaman->tanggal_peminjaman,
                'kunci_urut' => $this->kunciUrutRiwayat($peminjaman->tanggal_peminjaman, $peminjaman->created_at),
                'meta' => array_filter([
                    'Transaksi' => $peminjaman->nomor_peminjaman,
                    'Identitas' => $peminjaman->identitasPeminjam(),
                    'Rencana kembali' => $peminjaman->rencana_kembali?->locale('id')->translatedFormat('d F Y'),
                    'Petugas' => $peminjaman->dibuatOleh?->nama,
                ]),
                'tautan' => route('peminjaman-barang.show', $peminjaman),
                'label_tautan' => 'Lihat peminjaman',
            ]);

            foreach ($detailPeminjaman->detailPengembalianBarang as $detailPengembalian) {
                $pengembalian = $detailPengembalian->pengembalianBarang;

                if (! $pengembalian) {
                    continue;
                }

                $riwayat->push([
                    'jenis' => 'pengembalian',
                    'label' => 'Pengembalian',
                    'judul' => 'Aset dikembalikan oleh '.$peminjaman->namaPeminjam(),
                    'keterangan' => $detailPengembalian->catatan
                        ?: ($pengembalian->catatan ?: 'Aset diterima kembali dan kondisinya diperbarui.'),
                    'tanggal' => $pengembalian->tanggal_pengembalian,
                    'kunci_urut' => $this->kunciUrutRiwayat($pengembalian->tanggal_pengembalian, $pengembalian->created_at),
                    'meta' => array_filter([
                        'Transaksi' => $pengembalian->nomor_pengembalian,
                        'Kondisi kembali' => UnitBarang::DAFTAR_KONDISI[$detailPengembalian->kondisi_pengembalian] ?? null,
                        'Cara input' => $detailPengembalian->cara_input_barang === 'scan' ? 'Scan barcode' : 'Manual',
                        'Petugas' => $pengembalian->dibuatOleh?->nama,
                    ]),
                    'tautan' => route('peminjaman-barang.show', $peminjaman),
                    'label_tautan' => 'Lihat transaksi',
                ]);
            }
        }

        return $riwayat->sortByDesc('kunci_urut')->values();
    }

    private function kunciUrutRiwayat($tanggal, $dibuatPada): string
    {
        $bagianTanggal = $tanggal?->format('Y-m-d') ?: ($dibuatPada?->format('Y-m-d') ?: '0000-00-00');
        $bagianWaktu = $dibuatPada?->format('H:i:s.u') ?: '00:00:00.000000';

        return $bagianTanggal.' '.$bagianWaktu;
    }

    private function pilihanValid(mixed $nilai, array $daftar): string
    {
        return in_array($nilai, $daftar, true) ? $nilai : 'semua';
    }
}
