<?php

namespace App\Support;

use RuntimeException;
use Throwable;
use XMLWriter;
use ZipArchive;

class PenulisExcelSupervisi
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function buat(array $data): string
    {
        $folder = storage_path('app/exports');
        if (! is_dir($folder) && ! mkdir($folder, 0755, true) && ! is_dir($folder)) {
            throw new RuntimeException('Direktori ekspor belum dapat dibuat.');
        }
        $path = $folder.'/supervisi-'.bin2hex(random_bytes(12)).'.xlsx';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas Excel belum dapat dibuat.');
        }
        try {
            $paket = [
                '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
                '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rekap Supervisi" sheetId="1" r:id="rId1"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
                'xl/styles.xml' => '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F0F7"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFCFD9E2"/></left><right style="thin"><color rgb="FFCFD9E2"/></right><top style="thin"><color rgb="FFCFD9E2"/></top><bottom style="thin"><color rgb="FFCFD9E2"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
                'xl/worksheets/sheet1.xml' => $this->sheet($data),
            ];
            foreach ($paket as $name => $content) {
                if (! $zip->addFromString($name, $content)) {
                    throw new RuntimeException('Isi Excel belum dapat ditulis.');
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

    private function sheet(array $d): string
    {
        $coverage = $d['filter']['tab'] === 'belum_terjadwal';
        $headers = $coverage ? ['No.', 'Guru', 'Mata pelajaran', 'Kelas', 'Status'] : ['No.', 'Guru', 'Mata pelajaran', 'Kelas', 'Supervisor', 'Program', 'Tanggal', 'Perencanaan (50%)', 'Pelaksanaan (50%)', 'Nilai akhir', 'Kategori', 'Progres', 'Tindak lanjut', 'TL selesai', 'TL melewati tenggat'];
        $widths = $coverage ? [7, 32, 45, 40, 35] : [7, 30, 25, 16, 30, 30, 16, 20, 20, 15, 22, 35, 16, 16, 22];
        $last = chr(64 + count($headers));
        $x = new XMLWriter;
        $x->openMemory();
        $x->startDocument('1.0', 'UTF-8', 'yes');
        $x->startElement('worksheet');
        $x->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $x->writeRaw('<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        $x->startElement('cols');
        foreach ($widths as $i => $width) {
            $x->startElement('col');
            foreach (['min' => $i + 1, 'max' => $i + 1, 'width' => $width, 'customWidth' => 1] as $k => $v) {
                $x->writeAttribute($k, (string) $v);
            }
            $x->endElement();
        }
        $x->endElement();
        $x->startElement('sheetData');
        $this->baris($x, 1, [$coverage ? 'GURU BELUM TERJADWAL - SUPERVISI AKADEMIK' : 'REKAP SUPERVISI AKADEMIK'], 1);
        $this->baris($x, 2, [implode(' | ', $d['label_filter'])], 2);
        $this->baris($x, 3, ['Diekspor '.now()->format('d-m-Y H:i').' WIB | '.$d['semua']->count().' baris | Nilai hanya dari hasil final. Nilai kosong bukan nol.'], 2);
        $this->baris($x, 5, $headers, 1);
        foreach ($d['semua'] as $i => $r) {
            $values = $coverage ? [$i + 1, $r['guru'], $r['mapel'], $r['kelas'], $r['status']] : [$i + 1, $r['guru'], $r['mapel'], $r['kelas'], $r['supervisor'], $r['program'], $r['tanggal'], $r['nilai_perencanaan'], $r['nilai_pelaksanaan'], $r['nilai_akhir'], $r['kategori'], $r['label_tahap'], $r['tindak_lanjut'], $r['tindak_lanjut_selesai'], $r['tindak_lanjut_terlambat']];
            $this->baris($x, $i + 6, $values, 0, $coverage ? [] : [7, 8, 9]);
        }
        $x->endElement();
        $x->writeRaw('<autoFilter ref="A5:'.$last.max(5, $d['semua']->count() + 5).'"/><mergeCells count="3"><mergeCell ref="A1:'.$last.'1"/><mergeCell ref="A2:'.$last.'2"/><mergeCell ref="A3:'.$last.'3"/></mergeCells><pageMargins left=".25" right=".25" top=".5" bottom=".5" header=".2" footer=".2"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>');
        $x->endElement();
        $x->endDocument();

        return $x->outputMemory();
    }

    private function baris(XMLWriter $x, int $nomor, array $values, int $style, array $decimals = []): void
    {
        $x->startElement('row');
        $x->writeAttribute('r', (string) $nomor);
        foreach ($values as $i => $v) {
            $x->startElement('c');
            $x->writeAttribute('r', chr(65 + $i).$nomor);
            $x->writeAttribute('s', (string) (in_array($i, $decimals, true) ? 3 : $style));
            if (is_int($v) || is_float($v)) {
                $x->writeElement('v', (string) $v);
            } elseif ($v !== null) {
                // Inline strings keep user-entered names literal, including leading "=".
                $x->writeAttribute('t', 'inlineStr');
                $x->startElement('is');
                $x->startElement('t');
                $x->writeAttribute('xml:space', 'preserve');
                $x->text(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $v) ?? '');
                $x->endElement();
                $x->endElement();
            }
            $x->endElement();
        }
        $x->endElement();
    }
}
