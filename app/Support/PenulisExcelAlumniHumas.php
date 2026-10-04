<?php

namespace App\Support;

use App\Models\AlumniHumas;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;
use XMLWriter;
use ZipArchive;

class PenulisExcelAlumniHumas
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function buat(Collection $alumni, array $filter, bool $kontak = false): string
    {
        $folder = storage_path('app/exports');
        if (! is_dir($folder) && ! mkdir($folder, 0755, true) && ! is_dir($folder)) {
            throw new RuntimeException('Direktori ekspor belum dapat dibuat.');
        }
        $path = $folder.'/alumni-'.bin2hex(random_bytes(12)).'.xlsx';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas Excel belum dapat dibuat.');
        }
        try {
            $paket = [
                '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
                '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Database Alumni" sheetId="1" r:id="rId1"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
                'xl/styles.xml' => '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F0F7"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFCFD9E2"/></left><right style="thin"><color rgb="FFCFD9E2"/></right><top style="thin"><color rgb="FFCFD9E2"/></top><bottom style="thin"><color rgb="FFCFD9E2"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
                'xl/worksheets/sheet1.xml' => $this->sheet($alumni, $filter, $kontak),
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

    private function sheet(Collection $alumni, array $filter, bool $kontak): string
    {
        $headers = ['No.', 'Nama alumni', 'NIS', 'NISN', 'Jenis kelamin', 'Tahun masuk', 'Tahun lulus', 'Tanggal lulus', 'Kelas terakhir', 'Status alumni',
            'Penelusuran', 'Jenis sekolah', 'Sekolah lanjutan', 'Kota sekolah', 'Jurusan / program', 'Tanggal penelusuran', 'Sumber data'];
        if ($kontak) {
            $headers = [...$headers, 'Nomor WA (privat)', 'Email (privat)'];
        }
        $last = chr(64 + count($headers));
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->writeRaw('<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        $xml->startElement('cols');
        foreach ([7, 30, 15, 18, 15, 15, 15, 18, 18, 18, 29, 16, 38, 25, 25, 23, 18, ...($kontak ? [22, 32] : [])] as $i => $width) {
            $xml->startElement('col');
            foreach (['min' => $i + 1, 'max' => $i + 1, 'width' => $width, 'customWidth' => 1] as $k => $v) {
                $xml->writeAttribute($k, (string) $v);
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('sheetData');
        $this->baris($xml, 1, ['DATABASE ALUMNI - SMP NEGERI 2 PADANG PANJANG'], 1);
        $this->baris($xml, 2, ['Diekspor '.now()->format('d-m-Y H:i').' WIB | '.$alumni->count().' alumni | '.($kontak ? 'Termasuk kontak privat' : 'Tanpa kontak privat')], 2);
        $label = collect($filter)->except('tab')->filter(fn ($v) => filled($v))->map(fn ($value, $key) => $key.': '.$value)->implode(' | ');
        $this->baris($xml, 3, [$label ?: 'Alumni aktif - semua angkatan'], 2);
        $this->baris($xml, 5, $headers, 1);
        foreach ($alumni->values() as $i => $a) {
            $values = [$i + 1, $a->nama_lengkap, $a->nis, $a->nisn, ['L' => 'Laki-laki', 'P' => 'Perempuan'][$a->jenis_kelamin] ?? '-',
                $a->tahun_masuk, $a->tahun_lulus, $a->tanggal_lulus?->format('d-m-Y'), $a->kelas_terakhir, AlumniHumas::STATUS[$a->status],
                AlumniHumas::PENELUSURAN[$a->status_penelusuran], AlumniHumas::SEKOLAH[$a->jenis_sekolah] ?? '-', $a->nama_sekolah, $a->kota_sekolah, $a->jurusan,
                $a->tanggal_penelusuran?->format('d-m-Y'), $a->siswa_id ? 'Siswa NUSA' : 'Input manual'];
            $this->baris($xml, $i + 6, [...$values, ...($kontak ? [$a->nomor_wa, $a->email] : [])], 0);
        }
        $xml->endElement();
        $xml->writeRaw('<autoFilter ref="A5:'.$last.max(5, $alumni->count() + 5).'"/><mergeCells count="3"><mergeCell ref="A1:'.$last.'1"/><mergeCell ref="A2:'.$last.'2"/><mergeCell ref="A3:'.$last.'3"/></mergeCells><pageMargins left=".25" right=".25" top=".5" bottom=".5" header=".2" footer=".2"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>');
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function baris(XMLWriter $xml, int $nomor, array $values, int $style): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $nomor);
        foreach ($values as $i => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', chr(65 + $i).$nomor);
            $xml->writeAttribute('s', (string) $style);
            if (is_int($value)) {
                $xml->writeElement('v', (string) $value);
            } else {
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
