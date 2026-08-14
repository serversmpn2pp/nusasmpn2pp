<?php

namespace App\Support;

use DateTimeImmutable;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class PembacaExcelPenerimaanBarang
{
    private const NAMESPACE_SPREADSHEET = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NAMESPACE_RELASI_DOKUMEN = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const NAMESPACE_RELASI_PAKET = 'http://schemas.openxmlformats.org/package/2006/relationships';

    public function baca(string $lokasiBerkas): array
    {
        $zip = new ZipArchive;

        if ($zip->open($lokasiBerkas) !== true) {
            throw new RuntimeException('Berkas Excel tidak dapat dibuka.');
        }

        try {
            $stringBersama = $this->bacaStringBersama($zip);
            $daftarSheet = $this->daftarSheet($zip);
            $lokasiInformasi = $this->cariSheet($daftarSheet, 'informasi penerimaan');
            $lokasiRincian = $this->cariSheet($daftarSheet, 'rincian barang');

            if (! $lokasiInformasi || ! $lokasiRincian) {
                throw new RuntimeException('Sheet Informasi Penerimaan atau Rincian Barang tidak ditemukan. Gunakan template resmi NUSA.');
            }

            return [
                'informasi' => $this->bacaInformasi($zip, $lokasiInformasi, $stringBersama),
                'rincian' => $this->bacaRincian($zip, $lokasiRincian, $stringBersama),
            ];
        } finally {
            $zip->close();
        }
    }

    private function bacaInformasi(ZipArchive $zip, string $lokasiSheet, array $stringBersama): array
    {
        $baris = $this->bacaSemuaBaris($zip, $lokasiSheet, $stringBersama);
        $pemetaan = [
            'tanggal penerimaan' => 'tanggal_penerimaan',
            'sumber perolehan' => 'sumber_perolehan',
            'cara perolehan' => 'cara_perolehan',
            'nomor dokumen' => 'nomor_dokumen',
            'asal barang' => 'asal_barang',
            'catatan' => 'catatan',
        ];
        $hasil = [];

        foreach ($baris as $nilai) {
            $label = $this->normalisasiLabel($nilai['A'] ?? null);

            if (! isset($pemetaan[$label])) {
                continue;
            }

            $hasil[$pemetaan[$label]] = $this->bersihkanTeks($nilai['B'] ?? null);
        }

        $hasil['tanggal_penerimaan'] = $this->ubahTanggal($hasil['tanggal_penerimaan'] ?? null);

        return $hasil;
    }

    private function bacaRincian(ZipArchive $zip, string $lokasiSheet, array $stringBersama): array
    {
        $baris = $this->bacaSemuaBaris($zip, $lokasiSheet, $stringBersama);
        $header = [];
        $nomorHeader = null;

        foreach ($baris as $nomorBaris => $nilai) {
            foreach ($nilai as $kolom => $teks) {
                $label = $this->normalisasiLabel($teks);

                if ($label !== '') {
                    $header[$kolom] = $label;
                }
            }

            if (in_array('kode barang', $header, true) && in_array('jumlah', $header, true)) {
                $nomorHeader = $nomorBaris;
                break;
            }

            $header = [];
        }

        if ($nomorHeader === null) {
            throw new RuntimeException('Judul kolom pada sheet Rincian Barang tidak ditemukan. Gunakan template resmi NUSA.');
        }

        $pemetaan = [
            'kode barang' => 'kode_barang',
            'nama barang' => 'nama_barang',
            'jenis barang' => 'jenis_barang',
            'kode kategori' => 'kode_kategori',
            'kode satuan' => 'kode_satuan',
            'kode lokasi' => 'kode_lokasi',
            'jumlah' => 'jumlah',
            'harga satuan' => 'harga_satuan',
            'merek' => 'merek',
            'tipe' => 'tipe',
            'kondisi' => 'kondisi',
            'keterangan' => 'keterangan',
        ];
        $hasil = [];

        foreach ($baris as $nomorBaris => $nilai) {
            if ($nomorBaris <= $nomorHeader || strtoupper(trim((string) ($nilai['A'] ?? ''))) === 'CONTOH') {
                continue;
            }

            $data = [];
            foreach ($header as $kolom => $label) {
                if (isset($pemetaan[$label])) {
                    $data[$pemetaan[$label]] = $this->bersihkanTeks($nilai[$kolom] ?? null);
                }
            }

            if (collect($data)->filter(fn ($item) => filled($item))->isEmpty()) {
                continue;
            }

            $hasil[] = [
                'nomor_baris' => $nomorBaris,
                'data' => $data,
            ];
        }

        return $hasil;
    }

    private function bacaSemuaBaris(ZipArchive $zip, string $lokasiSheet, array $stringBersama): array
    {
        $isi = $zip->getFromName($lokasiSheet);

        if ($isi === false) {
            throw new RuntimeException('Isi sheet Excel tidak dapat dibaca.');
        }

        $sheet = simplexml_load_string($isi);

        if (! $sheet) {
            throw new RuntimeException('Struktur sheet Excel tidak valid.');
        }

        $hasil = [];
        foreach ($sheet->children(self::NAMESPACE_SPREADSHEET)->sheetData->row as $barisXml) {
            $nomorBaris = (int) $barisXml->attributes()['r'];
            $hasil[$nomorBaris] = $this->ambilNilaiBaris($barisXml, $stringBersama);
        }

        return $hasil;
    }

    private function daftarSheet(ZipArchive $zip): array
    {
        $isiWorkbook = $zip->getFromName('xl/workbook.xml');
        $isiRelasi = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($isiWorkbook === false || $isiRelasi === false) {
            throw new RuntimeException('Struktur workbook Excel tidak lengkap.');
        }

        $workbook = simplexml_load_string($isiWorkbook);
        $relasi = simplexml_load_string($isiRelasi);
        $targetRelasi = [];

        foreach ($relasi->children(self::NAMESPACE_RELASI_PAKET)->Relationship as $item) {
            $targetRelasi[(string) $item->attributes()['Id']] = (string) $item->attributes()['Target'];
        }

        $hasil = [];
        foreach ($workbook->children(self::NAMESPACE_SPREADSHEET)->sheets->sheet as $sheet) {
            $atributRelasi = $sheet->attributes(self::NAMESPACE_RELASI_DOKUMEN);
            $idRelasi = (string) ($atributRelasi['id'] ?? '');
            $target = $targetRelasi[$idRelasi] ?? null;

            if (! $target) {
                continue;
            }

            $lokasi = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/'.ltrim($target, '/');
            $hasil[$this->normalisasiLabel((string) $sheet->attributes()['name'])] = $lokasi;
        }

        return $hasil;
    }

    private function cariSheet(array $daftarSheet, string $nama): ?string
    {
        $nama = $this->normalisasiLabel($nama);

        foreach ($daftarSheet as $namaSheet => $lokasi) {
            if ($namaSheet === $nama || str_contains($namaSheet, $nama)) {
                return $lokasi;
            }
        }

        return null;
    }

    private function bacaStringBersama(ZipArchive $zip): array
    {
        $isi = $zip->getFromName('xl/sharedStrings.xml');

        if ($isi === false) {
            return [];
        }

        $xml = simplexml_load_string($isi);
        $hasil = [];

        foreach ($xml->children(self::NAMESPACE_SPREADSHEET)->si as $item) {
            $anak = $item->children(self::NAMESPACE_SPREADSHEET);
            $teks = isset($anak->t) ? (string) $anak->t : '';

            foreach ($anak->r as $bagian) {
                $teks .= (string) $bagian->children(self::NAMESPACE_SPREADSHEET)->t;
            }

            $hasil[] = $teks;
        }

        return $hasil;
    }

    private function ambilNilaiBaris(SimpleXMLElement $barisXml, array $stringBersama): array
    {
        $hasil = [];

        foreach ($barisXml->c as $selXml) {
            $alamat = (string) $selXml->attributes()['r'];

            if (! preg_match('/^([A-Z]+)/', $alamat, $cocok)) {
                continue;
            }

            $hasil[$cocok[1]] = $this->ambilNilaiSel($selXml, $stringBersama);
        }

        return $hasil;
    }

    private function ambilNilaiSel(SimpleXMLElement $selXml, array $stringBersama): ?string
    {
        $tipe = (string) $selXml->attributes()['t'];
        $anak = $selXml->children(self::NAMESPACE_SPREADSHEET);

        if ($tipe === 's') {
            return $stringBersama[(int) $anak->v] ?? null;
        }

        if ($tipe === 'inlineStr') {
            $inline = $anak->is;
            $teks = isset($inline->t) ? (string) $inline->t : '';

            foreach ($inline->r as $bagian) {
                $teks .= (string) $bagian->children(self::NAMESPACE_SPREADSHEET)->t;
            }

            return $teks;
        }

        return isset($anak->v) ? (string) $anak->v : null;
    }

    private function bersihkanTeks(mixed $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $teks = preg_replace('/\s+/u', ' ', trim((string) $nilai));

        return $teks === '' ? null : $teks;
    }

    private function normalisasiLabel(mixed $nilai): string
    {
        $nilai = mb_strtolower($this->bersihkanTeks($nilai) ?? '');
        $nilai = preg_replace('/\s*\*+\s*$/u', '', $nilai);

        return trim($nilai);
    }

    private function ubahTanggal(mixed $nilai): ?string
    {
        $teks = $this->bersihkanTeks($nilai);

        if ($teks === null) {
            return null;
        }

        if (is_numeric($teks)) {
            return (new DateTimeImmutable('1899-12-30'))
                ->modify('+'.((int) round((float) $teks)).' days')
                ->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $tanggal = DateTimeImmutable::createFromFormat('!'.$format, $teks);

            if ($tanggal && $tanggal->format($format) === $teks) {
                return $tanggal->format('Y-m-d');
            }
        }

        return $teks;
    }
}
