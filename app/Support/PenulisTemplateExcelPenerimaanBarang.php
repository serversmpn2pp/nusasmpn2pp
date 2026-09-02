<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class PenulisTemplateExcelPenerimaanBarang
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function buat(array $referensi, array $informasi = [], array $rincian = []): string
    {
        $direktori = storage_path('app/exports');

        if (! is_dir($direktori) && ! mkdir($direktori, 0755, true) && ! is_dir($direktori)) {
            throw new RuntimeException('Direktori template Excel tidak dapat dibuat.');
        }

        $lokasiBerkas = $direktori.DIRECTORY_SEPARATOR.'template-barang-datang-'.bin2hex(random_bytes(5)).'.xlsx';
        $zip = new ZipArchive;

        if ($zip->open($lokasiBerkas, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Template Excel tidak dapat dibuat.');
        }

        try {
            foreach ($this->isiPaket($referensi, $informasi, $rincian) as $namaBerkas => $isi) {
                $zip->addFromString($namaBerkas, $isi);
            }
        } finally {
            $zip->close();
        }

        return $lokasiBerkas;
    }

    private function isiPaket(array $referensi, array $informasi, array $rincian): array
    {
        return [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->relasiUtamaXml(),
            'docProps/app.xml' => $this->appXml(),
            'docProps/core.xml' => $this->coreXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->relasiWorkbookXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $this->sheetXml(
                $this->barisInformasi($referensi, $informasi),
                [28, 58],
                ['A1:B1'],
            ),
            'xl/worksheets/sheet2.xml' => $this->sheetXml(
                $this->barisRincian($rincian),
                [11, 22, 30, 22, 18, 16, 16, 13, 18, 20, 18, 18, 38],
                ['A1:M1', 'A2:M2'],
                4,
                'A4:M4',
            ),
            'xl/worksheets/sheet3.xml' => $this->sheetXml(
                $this->barisReferensi($referensi),
                [22, 34, 24, 22, 20, 22],
                ['A1:F1'],
                3,
            ),
        ];
    }

    private function barisInformasi(array $referensi, array $informasi): array
    {
        $sumberAwal = $informasi['sumber_perolehan'] ?? ($referensi['sumber'][0]['kode'] ?? '');

        return [
            [$this->sel('TEMPLATE IMPORT BARANG DATANG', 1)],
            [$this->sel('Isi kolom nilai di sebelah kanan. Kolom bertanda * wajib diisi.', 2)],
            [],
            [$this->sel('Tanggal Penerimaan *', 3), $this->sel($informasi['tanggal_penerimaan'] ?? now()->toDateString(), 4)],
            [$this->sel('Sumber Perolehan *', 3), $this->sel($sumberAwal, 4)],
            [$this->sel('Cara Perolehan *', 3), $this->sel($informasi['cara_perolehan'] ?? 'pembelian', 4)],
            [$this->sel('Nomor Dokumen', 3), $this->sel($informasi['nomor_dokumen'] ?? '', 4)],
            [$this->sel('Asal Barang', 3), $this->sel($informasi['asal_barang'] ?? '', 4)],
            [$this->sel('Catatan', 3), $this->sel($informasi['catatan'] ?? '', 4)],
            [],
            [$this->sel('Gunakan kode pada sheet Referensi NUSA. Cara perolehan: pembelian, hibah, atau lainnya.', 2)],
        ];
    }

    private function barisRincian(array $rincian): array
    {
        $baris = [
            [$this->sel('RINCIAN BARANG DATANG', 1)],
            [$this->sel('Kode barang tidak habis pakai terdiri dari 10 angka. Titik boleh diketik atau tidak. Nomor unit .01, .02, dan seterusnya dibuat otomatis saat import disimpan.', 2)],
            [],
            array_map(fn (string $judul) => $this->sel($judul, 5), [
                'No.',
                'Kode Barang',
                'Nama Barang',
                'Jenis Barang',
                'Kode Kategori',
                'Kode Satuan',
                'Kode Lokasi',
                'Jumlah',
                'Harga Satuan',
                'Merek',
                'Tipe',
                'Kondisi',
                'Keterangan',
            ]),
            array_map(fn (mixed $nilai) => $this->sel($nilai, 6), [
                'CONTOH',
                'OTOMATIS',
                'Spidol Whiteboard',
                'habis_pakai',
                'ATK',
                'BUAH',
                'GUDANG',
                24,
                12000,
                '',
                '',
                '',
                'Baris contoh ini tidak akan diimport.',
            ]),
        ];

        foreach ($rincian as $nomor => $item) {
            $baris[] = array_map(fn (mixed $nilai) => $this->sel($nilai, 4), [
                $nomor + 1,
                $item['kode_barang'] ?? '',
                $item['nama_barang'] ?? '',
                $item['jenis_barang'] ?? '',
                $item['kode_kategori'] ?? '',
                $item['kode_satuan'] ?? '',
                $item['kode_lokasi'] ?? '',
                $item['jumlah'] ?? '',
                $item['harga_satuan'] ?? '',
                $item['merek'] ?? '',
                $item['tipe'] ?? '',
                $item['kondisi'] ?? '',
                $item['keterangan'] ?? '',
            ]);
        }

        for ($i = count($rincian); $i < 20; $i++) {
            $baris[] = array_fill(0, 13, $this->sel('', 4));
        }

        return $baris;
    }

    private function barisReferensi(array $referensi): array
    {
        $baris = [
            [$this->sel('REFERENSI DATA NUSA', 1)],
            [$this->sel('Gunakan kode berikut saat mengisi template. Jangan mengubah nama sheet.', 2)],
            [],
            [$this->sel('BARANG AKTIF', 7)],
            array_map(fn (string $judul) => $this->sel($judul, 5), ['Kode', 'Nama', 'Jenis', 'Kategori', 'Satuan', 'Lokasi Utama']),
        ];

        foreach ($referensi['barang'] ?? [] as $item) {
            $baris[] = array_map(fn (mixed $nilai) => $this->sel($nilai, 4), [
                $item['kode'], $item['nama'], $item['jenis'], $item['kategori'], $item['satuan'], $item['lokasi'],
            ]);
        }

        $baris[] = [];
        $baris[] = [$this->sel('KATEGORI', 7), $this->sel('SATUAN', 7), $this->sel('LOKASI', 7), $this->sel('SUMBER PEROLEHAN', 7), $this->sel('NILAI YANG DIPERBOLEHKAN', 7)];
        $baris[] = [$this->sel('Kode - Nama', 5), $this->sel('Kode - Nama', 5), $this->sel('Kode - Nama', 5), $this->sel('Kode - Nama', 5), $this->sel('Jenis / kondisi / cara', 5)];

        $kategori = $referensi['kategori'] ?? [];
        $satuan = $referensi['satuan'] ?? [];
        $lokasi = $referensi['lokasi'] ?? [];
        $sumber = $referensi['sumber'] ?? [];
        $nilai = [
            'habis_pakai',
            'tidak_habis_pakai',
            'baik',
            'rusak_ringan',
            'rusak_berat',
            'pembelian',
            'hibah',
            'lainnya',
        ];
        $jumlah = max(count($kategori), count($satuan), count($lokasi), count($sumber), count($nilai));

        for ($i = 0; $i < $jumlah; $i++) {
            $baris[] = [
                $this->sel(isset($kategori[$i]) ? $kategori[$i]['kode'].' - '.$kategori[$i]['nama'] : '', 4),
                $this->sel(isset($satuan[$i]) ? $satuan[$i]['kode'].' - '.$satuan[$i]['nama'] : '', 4),
                $this->sel(isset($lokasi[$i]) ? $lokasi[$i]['kode'].' - '.$lokasi[$i]['nama'] : '', 4),
                $this->sel(isset($sumber[$i]) ? $sumber[$i]['kode'].' - '.$sumber[$i]['nama'] : '', 4),
                $this->sel($nilai[$i] ?? '', 4),
            ];
        }

        return $baris;
    }

    private function sheetXml(array $baris, array $lebarKolom, array $gabungan = [], ?int $barisBeku = null, ?string $filter = null): string
    {
        $barisXml = '';

        foreach ($baris as $indeksBaris => $kolom) {
            $nomorBaris = $indeksBaris + 1;
            $selXml = '';

            foreach ($kolom as $indeksKolom => $sel) {
                $selXml .= $this->selXml($indeksKolom + 1, $nomorBaris, $sel['nilai'] ?? '', $sel['style'] ?? 0);
            }

            $barisXml .= '<row r="'.$nomorBaris.'">'.$selXml.'</row>';
        }

        $kolomXml = '<cols>';
        foreach ($lebarKolom as $indeks => $lebar) {
            $kolom = $indeks + 1;
            $kolomXml .= '<col min="'.$kolom.'" max="'.$kolom.'" width="'.$lebar.'" customWidth="1"/>';
        }
        $kolomXml .= '</cols>';

        $pane = $barisBeku
            ? '<pane ySplit="'.$barisBeku.'" topLeftCell="A'.($barisBeku + 1).'" activePane="bottomLeft" state="frozen"/>'
            : '';
        $mergeXml = $gabungan
            ? '<mergeCells count="'.count($gabungan).'">'.collect($gabungan)->map(fn ($ref) => '<mergeCell ref="'.$ref.'"/>')->join('').'</mergeCells>'
            : '';
        $filterXml = $filter ? '<autoFilter ref="'.$filter.'"/>' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0">'.$pane.'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="20"/>'.$kolomXml.'<sheetData>'.$barisXml.'</sheetData>'
            .$filterXml.$mergeXml
            .'<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'</worksheet>';
    }

    private function sel(mixed $nilai, int $style = 0): array
    {
        return ['nilai' => $nilai, 'style' => $style];
    }

    private function selXml(int $kolom, int $baris, mixed $nilai, int $style): string
    {
        $referensi = $this->kolomExcel($kolom).$baris;
        $styleXml = $style > 0 ? ' s="'.$style.'"' : '';

        if (is_int($nilai) || is_float($nilai)) {
            return '<c r="'.$referensi.'"'.$styleXml.'><v>'.$nilai.'</v></c>';
        }

        return '<c r="'.$referensi.'" t="inlineStr"'.$styleXml.'><is><t xml:space="preserve">'.$this->escapeXml((string) $nilai).'</t></is></c>';
    }

    private function kolomExcel(int $kolom): string
    {
        $hasil = '';

        while ($kolom > 0) {
            $sisa = ($kolom - 1) % 26;
            $hasil = chr(65 + $sisa).$hasil;
            $kolom = intdiv($kolom - 1, 26);
        }

        return $hasil;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function relasiUtamaXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="Informasi Penerimaan" sheetId="1" r:id="rId1"/>'
            .'<sheet name="Rincian Barang" sheetId="2" r:id="rId2"/>'
            .'<sheet name="Referensi NUSA" sheetId="3" r:id="rId3"/>'
            .'</sheets></workbook>';
    }

    private function relasiWorkbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            .'<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>NUSA</Application></Properties>';
    }

    private function coreXml(): string
    {
        $waktu = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>Template Import Barang Datang NUSA</dc:title><dc:creator>NUSA</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$waktu.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$waktu.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4">'
            .'<font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF15477A"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="5">'
            .'<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF15477A"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFF3B8"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD7DEE8"/></left><right style="thin"><color rgb="FFD7DEE8"/></right><top style="thin"><color rgb="FFD7DEE8"/></top><bottom style="thin"><color rgb="FFD7DEE8"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function escapeXml(string $teks): string
    {
        return htmlspecialchars($teks, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
