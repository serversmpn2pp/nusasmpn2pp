<?php

namespace App\Support;

use App\Models\KunjunganTamu;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;
use XMLWriter;
use ZipArchive;

class PenulisExcelBukuTamu
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function buat(Collection $kunjungan, string $periode): string
    {
        $folder = storage_path('app/exports');
        if (! is_dir($folder) && ! mkdir($folder, 0755, true) && ! is_dir($folder)) {
            throw new RuntimeException('Direktori ekspor belum dapat dibuat.');
        }
        $path = $folder.'/buku-tamu-'.bin2hex(random_bytes(12)).'.xlsx';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas Excel belum dapat dibuat.');
        }
        try {
            foreach ($this->paket($kunjungan, $periode) as $nama => $isi) {
                if (! $zip->addFromString($nama, $isi)) {
                    throw new RuntimeException('Isi berkas Excel belum dapat ditulis.');
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('Berkas Excel belum dapat diselesaikan.');
            }
        } catch (Throwable $e) {
            @$zip->close();
            @unlink($path);
            throw $e;
        }

        return $path;
    }

    private function paket(Collection $kunjungan, string $periode): array
    {
        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Buku Tamu" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F0F7"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFCFD9E2"/></left><right style="thin"><color rgb="FFCFD9E2"/></right><top style="thin"><color rgb="FFCFD9E2"/></top><bottom style="thin"><color rgb="FFCFD9E2"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => $this->sheet($kunjungan, $periode),
        ];
    }

    private function sheet(Collection $kunjungan, string $periode): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->startElement('pane');
        foreach (['ySplit' => '5', 'topLeftCell' => 'A6', 'activePane' => 'bottomLeft', 'state' => 'frozen'] as $k => $v) {
            $xml->writeAttribute($k, $v);
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('cols');
        foreach ([7, 28, 28, 35, 20, 22, 24, 45, 28, 23, 23, 16, 22, 40, 24, 40] as $i => $width) {
            $xml->startElement('col');
            foreach (['min' => $i + 1, 'max' => $i + 1, 'width' => $width, 'customWidth' => 1] as $k => $v) {
                $xml->writeAttribute($k, (string) $v);
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('sheetData');
        $this->baris($xml, 1, ['BUKU TAMU DIGITAL - SMP NEGERI 2 PADANG PANJANG'], 1);
        $this->baris($xml, 2, [$periode], 2);
        $this->baris($xml, 3, ['Diekspor: '.now()->format('d-m-Y H:i').' WIB | Jumlah: '.$kunjungan->count()], 2);
        $this->baris($xml, 5, ['No.', 'Nama tamu', 'Instansi', 'Alamat instansi', 'Jabatan', 'Nomor WA', 'Kategori', 'Keperluan', 'Pihak yang dituju', 'Datang', 'Pulang', 'Durasi (menit)', 'Status', 'Catatan', 'Pencatat', 'Alasan pembatalan'], 3);
        foreach ($kunjungan->values() as $i => $tamu) {
            $this->baris($xml, $i + 6, [$i + 1, $tamu->nama_tamu, $tamu->instansi, $tamu->alamat_instansi, $tamu->jabatan, $tamu->nomor_wa,
                KunjunganTamu::KATEGORI[$tamu->kategori] ?? $tamu->kategori, $tamu->keperluan, $tamu->nama_tujuan,
                $tamu->waktu_datang->format('d-m-Y H:i:s'), $tamu->waktu_pulang?->format('d-m-Y H:i:s'),
                $tamu->durasiMenit(), KunjunganTamu::STATUS[$tamu->status] ?? $tamu->status, $tamu->catatan,
                $tamu->pencatat?->nama, $tamu->alasan_pembatalan], 0);
        }
        $xml->endElement();
        $xml->startElement('autoFilter');
        $xml->writeAttribute('ref', 'A5:P'.max(5, $kunjungan->count() + 5));
        $xml->endElement();
        $xml->startElement('mergeCells');
        $xml->writeAttribute('count', '3');
        foreach ([1, 2, 3] as $row) {
            $xml->startElement('mergeCell');
            $xml->writeAttribute('ref', 'A'.$row.':P'.$row);
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('pageMargins');
        foreach (['left' => '.25', 'right' => '.25', 'top' => '.5', 'bottom' => '.5', 'header' => '.2', 'footer' => '.2'] as $k => $v) {
            $xml->writeAttribute($k, $v);
        }
        $xml->endElement();
        $xml->startElement('pageSetup');
        foreach (['orientation' => 'landscape', 'paperSize' => '9', 'fitToWidth' => '1', 'fitToHeight' => '0'] as $k => $v) {
            $xml->writeAttribute($k, $v);
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function baris(XMLWriter $xml, int $row, array $nilai, int $style): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $row);
        foreach ($nilai as $i => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', chr(65 + $i).$row);
            $xml->writeAttribute('s', (string) $style);
            if (is_int($value)) {
                $xml->writeElement('v', (string) $value);
            } else {
                // Guest input stays text, including phone numbers and strings starting with '='.
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->startElement('t');
                $xml->writeAttribute('xml:space', 'preserve');
                $xml->text(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value) ?? '');
                $xml->endElement();
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
    }
}
