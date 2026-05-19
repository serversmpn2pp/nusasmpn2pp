<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class PenulisExcelLaporanAbsensi
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const JUMLAH_KOLOM = 15;

    public function buat(array $laporan): string
    {
        $direktori = storage_path('app/exports');

        if (! is_dir($direktori) && ! mkdir($direktori, 0755, true) && ! is_dir($direktori)) {
            throw new RuntimeException('Direktori export Excel tidak bisa dibuat.');
        }

        $lokasiBerkas = $direktori . DIRECTORY_SEPARATOR . 'laporan-absensi-' . now()->format('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.xlsx';
        $zip = new ZipArchive();

        if ($zip->open($lokasiBerkas, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas export Excel tidak bisa dibuat.');
        }

        foreach ($this->isiPaket($laporan) as $namaBerkas => $isi) {
            $zip->addFromString($namaBerkas, $isi);
        }

        $zip->close();

        return $lokasiBerkas;
    }

    private function isiPaket(array $laporan): array
    {
        return [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->relasiUtamaXml(),
            'docProps/app.xml' => $this->appXml(),
            'docProps/core.xml' => $this->coreXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->relasiWorkbookXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $this->sheetXml($laporan),
        ];
    }

    private function sheetXml(array $laporan): string
    {
        $baris = $this->barisSheet($laporan);
        $barisXml = '';

        foreach ($baris as $nomorBaris => $kolom) {
            $nomorBaris++;
            $selXml = '';

            foreach ($kolom as $indexKolom => $sel) {
                $selXml .= $this->selXml($indexKolom + 1, $nomorBaris, $sel['nilai'], $sel['style'] ?? 0);
            }

            $barisXml .= '<row r="' . $nomorBaris . '" spans="1:' . self::JUMLAH_KOLOM . '">' . $selXml . '</row>';
        }

        $kolomTerakhir = $this->kolomExcel(self::JUMLAH_KOLOM);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="10" topLeftCell="A11" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . $this->kolomXml()
            . '<sheetData>' . $barisXml . '</sheetData>'
            . '<mergeCells count="2"><mergeCell ref="A1:' . $kolomTerakhir . '1"/><mergeCell ref="A2:' . $kolomTerakhir . '2"/></mergeCells>'
            . '<pageMargins left="0.25" right="0.25" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            . '</worksheet>';
    }

    private function barisSheet(array $laporan): array
    {
        $tahunPelajaran = $laporan['tahunPelajaran']?->nama ?? '-';
        $kelas = $laporan['kelasDipilih']?->nama ?? 'Semua kelas';
        $ringkasan = $laporan['ringkasan'];
        $baris = [
            [$this->sel('LAPORAN ABSENSI', 1)],
            [$this->sel('SMP Negeri 2 Padang Panjang', 2)],
            [],
            [$this->sel('Tahun Pelajaran', 3), $this->sel($tahunPelajaran)],
            [$this->sel('Kelas', 3), $this->sel($kelas)],
            [$this->sel('Periode', 3), $this->sel($laporan['labelPeriode'])],
            [$this->sel('Hari Efektif', 3), $this->sel((int) $laporan['jumlahHariEfektif'], 5)],
            [$this->sel('Ringkasan', 3), $this->sel($this->ringkasanTeks($ringkasan))],
            [],
            array_map(fn (string $judul) => $this->sel($judul, 4), $this->judulKolom()),
        ];

        foreach ($laporan['laporanAbsensi'] as $urutan => $item) {
            $anggota = $item['anggota_kelas'];
            $siswa = $anggota?->siswa;
            $kelasSiswa = $anggota?->kelas;

            $baris[] = [
                $this->sel($anggota?->nomor_absen ?: $urutan + 1, 5),
                $this->sel($siswa?->nama_lengkap ?: '-', 7),
                $this->sel($siswa?->nis ?: '-', 7),
                $this->sel($siswa?->nisn ?: '-', 7),
                $this->sel($kelasSiswa?->nama ?: '-', 7),
                $this->sel((int) ($item['hari_efektif'] ?? 0), 5),
                $this->sel((int) ($item['hadir'] ?? 0), 5),
                $this->sel((int) ($item['izin'] ?? 0), 5),
                $this->sel((int) ($item['sakit'] ?? 0), 5),
                $this->sel((int) ($item['alfa'] ?? 0), 5),
                $this->sel((int) ($item['terlambat'] ?? 0), 5),
                $this->sel((int) round((float) ($item['menit_terlambat'] ?? 0)), 5),
                $this->sel((int) ($item['pulang_cepat'] ?? 0), 5),
                $this->sel((int) round((float) ($item['menit_pulang_cepat'] ?? 0)), 5),
                $this->sel((float) ($item['persentase_hadir'] ?? 0), 6),
            ];
        }

        $baris[] = [
            $this->sel('', 4),
            $this->sel('RINGKASAN', 4),
            $this->sel('', 4),
            $this->sel('', 4),
            $this->sel('', 4),
            $this->sel((int) ($ringkasan['hari_efektif'] ?? 0), 5),
            $this->sel((int) ($ringkasan['hadir'] ?? 0), 5),
            $this->sel((int) ($ringkasan['izin'] ?? 0), 5),
            $this->sel((int) ($ringkasan['sakit'] ?? 0), 5),
            $this->sel((int) ($ringkasan['alfa'] ?? 0), 5),
            $this->sel((int) ($ringkasan['terlambat'] ?? 0), 5),
            $this->sel((int) ($ringkasan['menit_terlambat'] ?? 0), 5),
            $this->sel((int) ($ringkasan['pulang_cepat'] ?? 0), 5),
            $this->sel((int) ($ringkasan['menit_pulang_cepat'] ?? 0), 5),
            $this->sel((float) ($ringkasan['rata_persentase_hadir'] ?? 0), 6),
        ];

        return $baris;
    }

    private function judulKolom(): array
    {
        return [
            'No.',
            'Nama Siswa',
            'NIS',
            'NISN',
            'Kelas',
            'Hari Efektif',
            'Hadir',
            'Izin',
            'Sakit',
            'Alfa',
            'Terlambat (kali)',
            'Menit Terlambat',
            'Pulang Cepat (kali)',
            'Menit Pulang Cepat',
            'Persentase Hadir',
        ];
    }

    private function ringkasanTeks(array $ringkasan): string
    {
        return 'Siswa: ' . (int) ($ringkasan['siswa'] ?? 0)
            . ' | Hadir: ' . (int) ($ringkasan['hadir'] ?? 0)
            . ' | Izin: ' . (int) ($ringkasan['izin'] ?? 0)
            . ' | Sakit: ' . (int) ($ringkasan['sakit'] ?? 0)
            . ' | Alfa: ' . (int) ($ringkasan['alfa'] ?? 0);
    }

    private function sel(mixed $nilai, int $style = 0): array
    {
        return [
            'nilai' => $nilai,
            'style' => $style,
        ];
    }

    private function selXml(int $kolom, int $baris, mixed $nilai, int $style = 0): string
    {
        $referensi = $this->kolomExcel($kolom) . $baris;
        $styleXml = $style > 0 ? ' s="' . $style . '"' : '';

        if (is_int($nilai) || is_float($nilai)) {
            return '<c r="' . $referensi . '"' . $styleXml . '><v>' . $nilai . '</v></c>';
        }

        return '<c r="' . $referensi . '" t="inlineStr"' . $styleXml . '><is><t xml:space="preserve">' . $this->escapeXml((string) $nilai) . '</t></is></c>';
    }

    private function kolomExcel(int $kolom): string
    {
        $namaKolom = '';

        while ($kolom > 0) {
            $sisa = ($kolom - 1) % 26;
            $namaKolom = chr(65 + $sisa) . $namaKolom;
            $kolom = intdiv($kolom - 1, 26);
        }

        return $namaKolom;
    }

    private function kolomXml(): string
    {
        $lebar = [7, 30, 16, 18, 12, 13, 10, 10, 10, 10, 15, 17, 18, 20, 17];
        $xml = '<cols>';

        foreach ($lebar as $index => $nilai) {
            $kolom = $index + 1;
            $xml .= '<col min="' . $kolom . '" max="' . $kolom . '" width="' . $nilai . '" customWidth="1"/>';
        }

        return $xml . '</cols>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function relasiUtamaXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Laporan Absensi" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function relasiWorkbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>NUSA</Application>'
            . '</Properties>';
    }

    private function coreXml(): string
    {
        $waktu = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Laporan Absensi NUSA</dc:title>'
            . '<dc:creator>NUSA</dc:creator>'
            . '<cp:lastModifiedBy>NUSA</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $waktu . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $waktu . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0\%"/></numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF15477A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF15477A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF15477A"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1C40F"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD7DEE8"/></left><right style="thin"><color rgb="FFD7DEE8"/></right><top style="thin"><color rgb="FFD7DEE8"/></top><bottom style="thin"><color rgb="FFD7DEE8"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function escapeXml(string $teks): string
    {
        return htmlspecialchars($teks, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
