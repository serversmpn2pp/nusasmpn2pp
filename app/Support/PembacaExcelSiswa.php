<?php

namespace App\Support;

use DateTimeImmutable;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class PembacaExcelSiswa
{
    private const NAMESPACE_SPREADSHEET = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function baca(string $lokasiBerkas): array
    {
        $zip = new ZipArchive();

        if ($zip->open($lokasiBerkas) !== true) {
            throw new RuntimeException('Berkas Excel tidak bisa dibuka.');
        }

        $stringBersama = $this->bacaStringBersama($zip);
        $lokasiSheet = $this->ambilLokasiSheetPertama($zip);
        $isiSheet = $zip->getFromName($lokasiSheet);

        if ($isiSheet === false) {
            $zip->close();
            throw new RuntimeException('Sheet pertama tidak ditemukan di berkas Excel.');
        }

        $sheet = simplexml_load_string($isiSheet);
        $hasil = [];

        foreach ($sheet->children(self::NAMESPACE_SPREADSHEET)->sheetData->row as $barisXml) {
            $nomorBaris = (int) $barisXml->attributes()['r'];

            if ($nomorBaris < 9) {
                continue;
            }

            $nilai = $this->ambilNilaiBaris($barisXml, $stringBersama);
            $jenisKelamin = strtoupper($this->bersihkanTeks($nilai['D'] ?? null) ?? '');
            $namaLengkap = $this->bersihkanTeks($nilai['C'] ?? null);

            if ($namaLengkap === '' || ! in_array($jenisKelamin, ['L', 'P'], true)) {
                continue;
            }

            $hasil[] = [
                'nomor_baris' => $nomorBaris,
                'kelas_saat_import' => $this->bersihkanTeks($nilai['K'] ?? null),
                'data' => [
                    'nama_lengkap' => $namaLengkap,
                    'nis' => $this->bersihkanAngkaIdentitas($nilai['E'] ?? null),
                    'nik' => $this->bersihkanAngkaIdentitas($nilai['F'] ?? null),
                    'nisn' => $this->bersihkanAngkaIdentitas($nilai['G'] ?? null),
                    'jenis_kelamin' => $jenisKelamin,
                    'tempat_lahir' => $this->bersihkanTeks($nilai['H'] ?? null),
                    'tanggal_lahir' => $this->ubahTanggal($nilai['I'] ?? null),
                    'agama' => $this->bersihkanTeks($nilai['J'] ?? null),
                    'status_dalam_keluarga' => $this->bersihkanTeks($nilai['L'] ?? null),
                    'anak_ke' => $this->ubahAngkaKecil($nilai['M'] ?? null),
                    'nama_ayah' => $this->bersihkanTeks($nilai['N'] ?? null),
                    'nama_ibu' => $this->bersihkanTeks($nilai['O'] ?? null),
                    'pekerjaan_ayah' => $this->bersihkanTeks($nilai['P'] ?? null),
                    'pekerjaan_ibu' => $this->bersihkanTeks($nilai['Q'] ?? null),
                    'alamat' => $this->bersihkanTeks($nilai['R'] ?? null),
                    'sekolah_asal' => $this->bersihkanTeks($nilai['S'] ?? null),
                    'aktif' => true,
                ],
            ];
        }

        $zip->close();

        return $hasil;
    }

    private function bacaStringBersama(ZipArchive $zip): array
    {
        $isiXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($isiXml === false) {
            return [];
        }

        $xml = simplexml_load_string($isiXml);
        $hasil = [];

        foreach ($xml->children(self::NAMESPACE_SPREADSHEET)->si as $item) {
            $itemAnak = $item->children(self::NAMESPACE_SPREADSHEET);

            if (isset($itemAnak->t)) {
                $hasil[] = (string) $itemAnak->t;
                continue;
            }

            $teks = '';
            foreach ($itemAnak->r as $bagian) {
                $teks .= (string) $bagian->children(self::NAMESPACE_SPREADSHEET)->t;
            }

            $hasil[] = $teks;
        }

        return $hasil;
    }

    private function ambilLokasiSheetPertama(ZipArchive $zip): string
    {
        $isiWorkbook = $zip->getFromName('xl/workbook.xml');
        $isiRelasi = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($isiWorkbook === false || $isiRelasi === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($isiWorkbook);
        $sheetPertama = $workbook->children(self::NAMESPACE_SPREADSHEET)->sheets->sheet[0] ?? null;

        if (! $sheetPertama) {
            return 'xl/worksheets/sheet1.xml';
        }

        $atributRelasi = $sheetPertama->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $idRelasi = (string) ($atributRelasi['id'] ?? '');

        if ($idRelasi === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        $relasi = simplexml_load_string($isiRelasi);
        $relasiAnak = $relasi->children('http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($relasiAnak->Relationship as $item) {
            if ((string) $item->attributes()['Id'] === $idRelasi) {
                $target = (string) $item->attributes()['Target'];

                return str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/' . ltrim($target, '/');
            }
        }

        return 'xl/worksheets/sheet1.xml';
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
        $selAnak = $selXml->children(self::NAMESPACE_SPREADSHEET);

        if ($tipe === 's') {
            $index = (int) $selAnak->v;

            return $stringBersama[$index] ?? null;
        }

        if ($tipe === 'inlineStr') {
            return isset($selAnak->is->t) ? (string) $selAnak->is->t : null;
        }

        return isset($selAnak->v) ? (string) $selAnak->v : null;
    }

    private function bersihkanTeks(mixed $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $teks = preg_replace('/\s+/u', ' ', trim((string) $nilai));

        return $teks === '' ? null : $teks;
    }

    private function bersihkanAngkaIdentitas(mixed $nilai): ?string
    {
        $teks = $this->bersihkanTeks($nilai);

        if ($teks === null) {
            return null;
        }

        $teks = ltrim($teks, "'");

        if (preg_match('/^\d+\.0$/', $teks)) {
            $teks = substr($teks, 0, -2);
        }

        return $teks;
    }

    private function ubahAngkaKecil(mixed $nilai): ?int
    {
        $teks = $this->bersihkanTeks($nilai);

        if ($teks === null || ! is_numeric($teks)) {
            return null;
        }

        return (int) $teks;
    }

    private function ubahTanggal(mixed $nilai): ?string
    {
        $teks = $this->bersihkanTeks($nilai);

        if ($teks === null) {
            return null;
        }

        if (is_numeric($teks)) {
            $tanggal = (new DateTimeImmutable('1899-12-30'))->modify('+' . ((int) round((float) $teks)) . ' days');

            return $tanggal->format('Y-m-d');
        }

        $teksKecil = mb_strtolower($teks);
        $bulan = [
            'januari' => '01',
            'februari' => '02',
            'maret' => '03',
            'april' => '04',
            'mei' => '05',
            'juni' => '06',
            'juli' => '07',
            'agustus' => '08',
            'september' => '09',
            'oktober' => '10',
            'november' => '11',
            'desember' => '12',
        ];

        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{4})$/u', $teksKecil, $cocok) && isset($bulan[$cocok[2]])) {
            return sprintf('%04d-%02d-%02d', (int) $cocok[3], (int) $bulan[$cocok[2]], (int) $cocok[1]);
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $tanggal = DateTimeImmutable::createFromFormat($format, $teks);

            if ($tanggal) {
                return $tanggal->format('Y-m-d');
            }
        }

        return null;
    }
}
