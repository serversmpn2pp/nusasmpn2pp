<?php

namespace App\Services\Inventaris;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\PenerimaanBarang;
use App\Models\SatuanBarang;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProsesImportPenerimaanBarang
{
    public function __construct(
        private GeneratorIdentitasInventaris $generatorIdentitas,
        private ProsesPenerimaanBarang $prosesPenerimaan,
    ) {}

    public function siapkan(array $hasilBaca): array
    {
        $barang = Barang::query()
            ->with(['kategoriBarang', 'satuanBarang', 'lokasiPenyimpanan'])
            ->get();
        $kategori = KategoriBarang::all();
        $satuan = SatuanBarang::all();
        $lokasi = LokasiBarang::all();
        $sumber = SumberPerolehanBarang::all();
        $kesalahanUmum = [];
        $informasi = $this->siapkanInformasi($hasilBaca['informasi'] ?? [], $sumber, $kesalahanUmum);
        $rincianBaca = $hasilBaca['rincian'] ?? [];

        if ($rincianBaca === []) {
            $kesalahanUmum[] = 'Belum ada rincian barang yang dapat dibaca.';
        } elseif (count($rincianBaca) > 100) {
            $kesalahanUmum[] = 'Maksimal 100 baris barang dalam satu kali import.';
        }

        $rincian = [];
        $kunciDiBerkas = [];
        $barangBaruDiBerkas = [];
        $totalUnitAset = 0;

        foreach (array_slice($rincianBaca, 0, 100) as $baris) {
            $item = $this->siapkanRincian(
                $baris,
                $barang,
                $kategori,
                $satuan,
                $lokasi,
            );

            if ($item['kunci_duplikat'] && isset($kunciDiBerkas[$item['kunci_duplikat']])) {
                $item['kesalahan'][] = 'Barang dan lokasi ini juga terdapat pada baris '.$kunciDiBerkas[$item['kunci_duplikat']].'. Gabungkan jumlahnya dalam satu baris.';
            } elseif ($item['kunci_duplikat']) {
                $kunciDiBerkas[$item['kunci_duplikat']] = $item['nomor_baris'];
            }

            if ($item['status_barang'] === 'baru' && $item['kunci_barang']) {
                if (isset($barangBaruDiBerkas[$item['kunci_barang']])) {
                    $item['kesalahan'][] = 'Barang baru yang sama juga terdapat pada baris '.$barangBaruDiBerkas[$item['kunci_barang']].'. Buat barang baru hanya pada satu baris.';
                } else {
                    $barangBaruDiBerkas[$item['kunci_barang']] = $item['nomor_baris'];
                }
            }

            if ($item['jenis_barang'] === 'tidak_habis_pakai' && is_numeric($item['jumlah'])) {
                $totalUnitAset += (int) $item['jumlah'];
            }

            $rincian[] = $item;
        }

        if ($totalUnitAset > 500) {
            $kesalahanUmum[] = 'Maksimal 500 unit aset dapat dibuat dalam satu kali import.';
        }

        $jumlahKesalahanBaris = collect($rincian)->sum(fn (array $item) => count($item['kesalahan']));
        $jumlahBaru = collect($rincian)->where('status_barang', 'baru')->count();

        return [
            'informasi' => $informasi,
            'rincian' => $rincian,
            'kesalahan_umum' => array_values(array_unique($kesalahanUmum)),
            'jumlah_baris' => count($rincian),
            'jumlah_barang_baru' => $jumlahBaru,
            'jumlah_barang_lama' => count($rincian) - $jumlahBaru,
            'total_unit_aset' => $totalUnitAset,
            'jumlah_kesalahan' => count($kesalahanUmum) + $jumlahKesalahanBaris,
            'valid' => $kesalahanUmum === [] && $jumlahKesalahanBaris === 0 && $rincian !== [],
            'dibuat_pada' => now()->toIso8601String(),
        ];
    }

    public function simpan(array $pratinjau, ?int $penggunaId): PenerimaanBarang
    {
        if (! ($pratinjau['valid'] ?? false)) {
            throw ValidationException::withMessages([
                'berkas_excel' => 'Data import masih memiliki kesalahan dan belum dapat disimpan.',
            ]);
        }

        return DB::transaction(function () use ($pratinjau, $penggunaId) {
            $informasi = $pratinjau['informasi'];

            if (filled($informasi['nomor_dokumen'] ?? null) && PenerimaanBarang::query()
                ->where('status', PenerimaanBarang::STATUS_AKTIF)
                ->whereRaw('LOWER(nomor_dokumen) = ?', [mb_strtolower($informasi['nomor_dokumen'])])
                ->exists()) {
                throw ValidationException::withMessages([
                    'nomor_dokumen' => 'Nomor dokumen sudah pernah digunakan. Import dibatalkan untuk mencegah data ganda.',
                ]);
            }

            $rincianPenerimaan = [];
            foreach ($pratinjau['rincian'] as $indeks => $item) {
                $barang = $this->ambilAtauBuatBarang($item, $indeks);
                $rincianPenerimaan[] = [
                    'barang_id' => $barang->id,
                    'lokasi_barang_id' => $item['data_simpan']['lokasi_barang_id'],
                    'jumlah' => $item['data_simpan']['jumlah'],
                    'harga_satuan' => $item['data_simpan']['harga_satuan'],
                    'merek' => $item['data_simpan']['merek'],
                    'tipe' => $item['data_simpan']['tipe'],
                    'kondisi' => $item['data_simpan']['kondisi'],
                    'keterangan' => $item['data_simpan']['keterangan'],
                ];
            }

            return $this->prosesPenerimaan->catat([
                'tanggal_penerimaan' => $informasi['tanggal_penerimaan'],
                'sumber_perolehan_barang_id' => $informasi['sumber_perolehan_barang_id'],
                'cara_perolehan' => $informasi['cara_perolehan'],
                'nomor_dokumen' => $informasi['nomor_dokumen'],
                'asal_barang' => $informasi['asal_barang'],
                'catatan' => $informasi['catatan'],
                'rincian' => $rincianPenerimaan,
            ], $penggunaId);
        });
    }

    private function siapkanInformasi(array $data, Collection $daftarSumber, array &$kesalahan): array
    {
        $tanggal = trim((string) ($data['tanggal_penerimaan'] ?? ''));
        $tanggalObjek = $tanggal !== '' ? Carbon::createFromFormat('!Y-m-d', $tanggal) : false;

        if (! $tanggalObjek || $tanggalObjek->format('Y-m-d') !== $tanggal) {
            $kesalahan[] = 'Tanggal penerimaan tidak valid. Gunakan format YYYY-MM-DD.';
        } elseif ($tanggalObjek->isFuture()) {
            $kesalahan[] = 'Tanggal penerimaan tidak boleh melewati hari ini.';
        }

        $sumberInput = $this->bersihkanTeks($data['sumber_perolehan'] ?? null);
        $sumber = $this->cariKodeAtauNama($daftarSumber, $sumberInput);

        if (! $sumber) {
            $kesalahan[] = 'Sumber perolehan tidak ditemukan pada Referensi NUSA.';
        } elseif (! $sumber->aktif) {
            $kesalahan[] = 'Sumber perolehan yang dipilih sudah tidak aktif.';
        }

        $cara = $this->normalisasiCaraPerolehan($data['cara_perolehan'] ?? null);
        if (! $cara) {
            $kesalahan[] = 'Cara perolehan harus pembelian, hibah, atau lainnya.';
        }

        $nomorDokumen = $this->batasiTeks($data['nomor_dokumen'] ?? null, 120, 'Nomor dokumen', $kesalahan);
        $asalBarang = $this->batasiTeks($data['asal_barang'] ?? null, 160, 'Asal barang', $kesalahan);
        $catatan = $this->bersihkanTeks($data['catatan'] ?? null);

        if ($nomorDokumen && PenerimaanBarang::query()
            ->where('status', PenerimaanBarang::STATUS_AKTIF)
            ->whereRaw('LOWER(nomor_dokumen) = ?', [mb_strtolower($nomorDokumen)])
            ->exists()) {
            $kesalahan[] = 'Nomor dokumen sudah pernah digunakan pada penerimaan sebelumnya.';
        }

        return [
            'tanggal_penerimaan' => $tanggal,
            'sumber_perolehan_barang_id' => $sumber?->id,
            'sumber_perolehan' => $sumber?->nama ?: ($sumberInput ?: '-'),
            'cara_perolehan' => $cara,
            'label_cara_perolehan' => $cara ? PenerimaanBarang::DAFTAR_CARA_PEROLEHAN[$cara] : '-',
            'nomor_dokumen' => $nomorDokumen,
            'asal_barang' => $asalBarang,
            'catatan' => $catatan,
        ];
    }

    private function siapkanRincian(
        array $baris,
        Collection $daftarBarang,
        Collection $daftarKategori,
        Collection $daftarSatuan,
        Collection $daftarLokasi,
    ): array {
        $data = $baris['data'] ?? [];
        $nomorBaris = (int) ($baris['nomor_baris'] ?? 0);
        $kesalahan = [];
        $kodeInput = strtoupper($this->bersihkanTeks($data['kode_barang'] ?? null) ?? '');
        $namaInput = $this->batasiTeks($data['nama_barang'] ?? null, 150, 'Nama barang', $kesalahan);
        $barang = null;

        if ($kodeInput !== '' && $kodeInput !== 'OTOMATIS') {
            $barang = $this->cariKode($daftarBarang, $kodeInput);
        }

        if (! $barang && $namaInput && in_array($kodeInput, ['', 'OTOMATIS'], true)) {
            $berdasarkanNama = $daftarBarang->filter(
                fn ($item) => $this->normalisasi($item->nama) === $this->normalisasi($namaInput),
            );

            if ($berdasarkanNama->count() === 1) {
                $barang = $berdasarkanNama->first();
            }
        }

        if ($barang && ! $barang->aktif) {
            $kesalahan[] = 'Barang ditemukan tetapi sudah tidak aktif.';
        }

        $jenisInput = $this->normalisasiJenisBarang($data['jenis_barang'] ?? null);
        $jenisBarang = $barang?->jenis_barang ?: $jenisInput;

        if ($barang && $jenisInput && $jenisInput !== $barang->jenis_barang) {
            $kesalahan[] = 'Jenis barang pada Excel tidak sama dengan data master NUSA.';
        }

        if (! $barang && ! $jenisBarang) {
            $kesalahan[] = 'Jenis barang wajib diisi untuk barang baru.';
        }

        if (! $barang && ! $namaInput) {
            $kesalahan[] = 'Nama barang wajib diisi untuk barang baru.';
        }

        if (! $barang && $jenisBarang === 'tidak_habis_pakai') {
            if ($kodeInput === '' || $kodeInput === 'OTOMATIS') {
                $kesalahan[] = 'Kode klasifikasi wajib diisi untuk barang tidak habis pakai.';
            } elseif (! preg_match('/^\d{2}(?:\.\d{2}){5}$/', $kodeInput)) {
                $kesalahan[] = 'Kode aset harus terdiri dari enam kelompok dua angka, misalnya 02.06.01.05.40.01.';
            }
        }

        if (! $barang && $jenisBarang === 'habis_pakai' && ! in_array($kodeInput, ['', 'OTOMATIS'], true)) {
            $kesalahan[] = 'Kode barang habis pakai dibuat otomatis. Kosongkan kode atau isi OTOMATIS.';
        }

        $kategori = $barang?->kategoriBarang ?: $this->cariKodeAtauNama($daftarKategori, $data['kode_kategori'] ?? null);
        $satuan = $barang?->satuanBarang ?: $this->cariKodeAtauNama($daftarSatuan, $data['kode_satuan'] ?? null);
        $lokasiInput = $this->cariKodeAtauNama($daftarLokasi, $data['kode_lokasi'] ?? null);
        $lokasi = $lokasiInput ?: $barang?->lokasiPenyimpanan;

        foreach ([['Kategori', $kategori], ['Satuan', $satuan], ['Lokasi', $lokasi]] as [$label, $referensi]) {
            if (! $referensi) {
                $kesalahan[] = $label.' tidak ditemukan pada Referensi NUSA.';
            } elseif (! $referensi->aktif) {
                $kesalahan[] = $label.' yang dipilih sudah tidak aktif.';
            }
        }

        $jumlah = $this->ubahAngka($data['jumlah'] ?? null);
        if ($jumlah === null || $jumlah <= 0) {
            $kesalahan[] = 'Jumlah harus berupa angka lebih dari nol.';
        } elseif ($jenisBarang === 'tidak_habis_pakai' && abs($jumlah - round($jumlah)) > 0.00001) {
            $kesalahan[] = 'Jumlah aset harus berupa bilangan bulat.';
        } elseif ($jenisBarang === 'tidak_habis_pakai' && $jumlah > 200) {
            $kesalahan[] = 'Maksimal 200 unit aset dalam satu baris.';
        }

        $harga = $this->ubahAngka($data['harga_satuan'] ?? null, bolehKosong: true);
        if ($harga !== null && $harga < 0) {
            $kesalahan[] = 'Harga satuan tidak boleh negatif.';
        }

        $kondisi = $jenisBarang === 'tidak_habis_pakai'
            ? ($this->normalisasiKondisi($data['kondisi'] ?? null) ?: 'baik')
            : null;
        if ($jenisBarang === 'tidak_habis_pakai' && ! array_key_exists($kondisi, UnitBarang::DAFTAR_KONDISI)) {
            $kesalahan[] = 'Kondisi aset harus baik, rusak_ringan, atau rusak_berat.';
        }

        $merek = $this->batasiTeks($data['merek'] ?? null, 120, 'Merek', $kesalahan);
        $tipe = $this->batasiTeks($data['tipe'] ?? null, 120, 'Tipe', $kesalahan);
        $keterangan = $this->batasiTeks($data['keterangan'] ?? null, 1000, 'Keterangan', $kesalahan);
        $kodeTampilan = $barang?->kode ?: ($jenisBarang === 'habis_pakai' ? 'Otomatis oleh NUSA' : $kodeInput);
        $namaTampilan = $barang?->nama ?: ($namaInput ?: '-');
        $kunciBarang = $barang
            ? 'barang-'.$barang->id
            : 'baru-'.$this->normalisasi($kodeInput ?: $namaInput);
        $kunciDuplikat = $lokasi ? $kunciBarang.'-lokasi-'.$lokasi->id : null;

        return [
            'nomor_baris' => $nomorBaris,
            'kode_barang' => $kodeTampilan,
            'nama_barang' => $namaTampilan,
            'jenis_barang' => $jenisBarang,
            'label_jenis_barang' => $jenisBarang ? (Barang::DAFTAR_JENIS_BARANG[$jenisBarang] ?? $jenisBarang) : '-',
            'kategori' => $kategori?->nama ?: '-',
            'satuan' => $satuan?->nama ?: '-',
            'lokasi' => $lokasi?->nama ?: '-',
            'jumlah' => $jumlah,
            'harga_satuan' => $harga,
            'merek_tipe' => collect([$merek, $tipe])->filter()->join(' - ') ?: '-',
            'kondisi' => $kondisi,
            'label_kondisi' => $kondisi ? (UnitBarang::DAFTAR_KONDISI[$kondisi] ?? $kondisi) : '-',
            'keterangan' => $keterangan,
            'status_barang' => $barang ? 'lama' : 'baru',
            'kesalahan' => array_values(array_unique($kesalahan)),
            'kunci_duplikat' => $kunciDuplikat,
            'kunci_barang' => $kunciBarang,
            'data_simpan' => [
                'barang_id' => $barang?->id,
                'barang_baru' => $barang ? null : [
                    'kode' => $jenisBarang === 'tidak_habis_pakai' ? $kodeInput : null,
                    'nama' => $namaInput,
                    'jenis_barang' => $jenisBarang,
                    'kategori_barang_id' => $kategori?->id,
                    'satuan_barang_id' => $satuan?->id,
                    'lokasi_penyimpanan_id' => $lokasi?->id,
                ],
                'lokasi_barang_id' => $lokasi?->id,
                'jumlah' => $jumlah,
                'harga_satuan' => $harga,
                'merek' => $jenisBarang === 'tidak_habis_pakai' ? $merek : null,
                'tipe' => $jenisBarang === 'tidak_habis_pakai' ? $tipe : null,
                'kondisi' => $kondisi,
                'keterangan' => $keterangan,
            ],
        ];
    }

    private function ambilAtauBuatBarang(array $item, int $indeks): Barang
    {
        $data = $item['data_simpan'];

        if ($data['barang_id']) {
            $barang = Barang::query()->lockForUpdate()->find($data['barang_id']);

            if (! $barang || ! $barang->aktif) {
                throw ValidationException::withMessages([
                    "rincian.$indeks.barang_id" => 'Barang pada baris '.$item['nomor_baris'].' tidak lagi tersedia.',
                ]);
            }

            return $barang;
        }

        $baru = $data['barang_baru'];
        $kategoriAktif = KategoriBarang::whereKey($baru['kategori_barang_id'])->where('aktif', true)->exists();
        $satuanAktif = SatuanBarang::whereKey($baru['satuan_barang_id'])->where('aktif', true)->exists();
        $lokasiAktif = LokasiBarang::whereKey($baru['lokasi_penyimpanan_id'])->where('aktif', true)->exists();

        if (! $kategoriAktif || ! $satuanAktif || ! $lokasiAktif) {
            throw ValidationException::withMessages([
                "rincian.$indeks.referensi" => 'Referensi barang baru pada baris '.$item['nomor_baris'].' sudah berubah. Unggah ulang template.',
            ]);
        }

        $kode = $baru['jenis_barang'] === 'habis_pakai'
            ? $this->generatorIdentitas->buatKodeBarangHabisPakai()
            : $baru['kode'];

        if (Barang::whereRaw('LOWER(kode) = ?', [mb_strtolower($kode)])->exists()) {
            throw ValidationException::withMessages([
                "rincian.$indeks.kode" => 'Kode barang pada baris '.$item['nomor_baris'].' sudah digunakan.',
            ]);
        }

        return Barang::create([
            'kode' => $kode,
            'nama' => $baru['nama'],
            'kategori_barang_id' => $baru['kategori_barang_id'],
            'satuan_barang_id' => $baru['satuan_barang_id'],
            'lokasi_penyimpanan_id' => $baru['lokasi_penyimpanan_id'],
            'jenis_barang' => $baru['jenis_barang'],
            'tipe_pengelolaan' => $baru['jenis_barang'] === 'habis_pakai' ? 'habis_pakai' : 'aset_individual',
            'stok_minimum' => 0,
            'aktif' => true,
        ]);
    }

    private function cariKodeAtauNama(Collection $daftar, mixed $nilai)
    {
        $nilai = $this->normalisasi($nilai);

        if ($nilai === '') {
            return null;
        }

        return $daftar->first(fn ($item) => $this->normalisasi($item->kode) === $nilai)
            ?: $daftar->first(fn ($item) => $this->normalisasi($item->nama) === $nilai);
    }

    private function cariKode(Collection $daftar, string $kode)
    {
        $kode = $this->normalisasi($kode);

        return $daftar->first(fn ($item) => $this->normalisasi($item->kode) === $kode);
    }

    private function normalisasiCaraPerolehan(mixed $nilai): ?string
    {
        return match ($this->normalisasi($nilai)) {
            'pembelian' => 'pembelian',
            'hibah', 'hibah bantuan', 'bantuan' => 'hibah',
            'lainnya', 'lain lain', 'lain' => 'lainnya',
            default => null,
        };
    }

    private function normalisasiJenisBarang(mixed $nilai): ?string
    {
        return match ($this->normalisasi($nilai)) {
            'habis pakai', 'barang habis pakai' => 'habis_pakai',
            'tidak habis pakai', 'barang tidak habis pakai', 'aset', 'aset individual' => 'tidak_habis_pakai',
            default => null,
        };
    }

    private function normalisasiKondisi(mixed $nilai): ?string
    {
        $nilai = $this->normalisasi($nilai);

        return $nilai === '' ? null : str_replace(' ', '_', $nilai);
    }

    private function normalisasi(mixed $nilai): string
    {
        $nilai = mb_strtolower(trim((string) $nilai));
        $nilai = str_replace(['_', '-'], ' ', $nilai);

        return preg_replace('/\s+/u', ' ', $nilai);
    }

    private function bersihkanTeks(mixed $nilai): ?string
    {
        $nilai = preg_replace('/\s+/u', ' ', trim((string) $nilai));

        return $nilai === '' ? null : $nilai;
    }

    private function batasiTeks(mixed $nilai, int $maksimal, string $label, array &$kesalahan): ?string
    {
        $nilai = $this->bersihkanTeks($nilai);

        if ($nilai !== null && mb_strlen($nilai) > $maksimal) {
            $kesalahan[] = $label.' maksimal '.$maksimal.' karakter.';
        }

        return $nilai;
    }

    private function ubahAngka(mixed $nilai, bool $bolehKosong = false): ?float
    {
        $teks = trim((string) $nilai);

        if ($teks === '') {
            return $bolehKosong ? null : null;
        }

        $teks = preg_replace('/\s+/', '', $teks);

        if (str_contains($teks, ',') && str_contains($teks, '.')) {
            $teks = str_replace('.', '', $teks);
            $teks = str_replace(',', '.', $teks);
        } elseif (substr_count($teks, '.') > 1 || preg_match('/^\d{1,3}(?:\.\d{3})+$/', $teks)) {
            $teks = str_replace('.', '', $teks);
        } elseif (str_contains($teks, ',')) {
            $teks = str_replace(',', '.', $teks);
        }

        return is_numeric($teks) ? (float) $teks : null;
    }
}
