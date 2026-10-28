<?php

namespace App\Support;

use App\Models\AcaraBukuTamu;
use App\Models\ResponAcaraBukuTamu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use XMLWriter;
use ZipArchive;

class PenulisExcelAcaraBukuTamu
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function buat(AcaraBukuTamu $acara, Collection $rows, array $s): string
    {
        $folder = storage_path('app/exports');
        File::ensureDirectoryExists($folder);
        $path = $folder.'/acara-'.bin2hex(random_bytes(12)).'.xlsx';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Ekspor belum dapat dibuat.');
        }
        $ringkasan = [['ACARA & PAMERAN - NUSA', 'Nilai'], ['Acara', $acara->nama], ['Lokasi', $acara->lokasi],
            ['Diekspor', now()->format('d-m-Y H:i:s').' WIB'], ['Pengunjung tercatat (valid)', $s['total']],
            ['Puas / sangat puas (%)', $s['persen_puas'] ?? '-'], ['Rata-rata (1-5)', $s['rata_rata'] ?? '-'],
            ['Penilai NUSA', $s['penilai_nusa']], ['Puas terhadap NUSA (%)', $s['persen_puas_nusa'] ?? '-'],
            ['Cakupan', 'Statistik respons valid dalam rentang dan kategori; daftar mengikuti seluruh filter.']];
        foreach (['kepuasan' => ResponAcaraBukuTamu::KEPUASAN, 'kategori' => ResponAcaraBukuTamu::KATEGORI, 'minat' => $s['label_minat']] as $key => $labels) {
            $ringkasan[] = [strtoupper($key), 'Jumlah'];
            foreach ($labels as $kode => $label) {
                $ringkasan[] = [$label, $s[$key][$kode]];
            }
        }
        $daftar = [['No.', 'Nama', 'Instansi', 'Kategori', 'Minat', 'Kepuasan', 'Penilaian NUSA', 'Saran', 'Waktu (WIB)', 'Status', 'Alasan status']];
        foreach ($rows as $i => $r) {
            $daftar[] = [$i + 1, $r->nama, $r->instansi, ResponAcaraBukuTamu::KATEGORI[$r->kategori],
                $acara->namaMinat($r->minat),
                ResponAcaraBukuTamu::KEPUASAN[$r->kepuasan], $r->nilai_nusa === null ? 'Belum mencoba / tidak menilai' : ResponAcaraBukuTamu::KEPUASAN[$r->nilai_nusa],
                $r->saran, $r->created_at->format('d-m-Y H:i:s'), $r->status, $r->alasan_status];
        }
        $ns = 'http://schemas.openxmlformats.org';
        $types = '<Types xmlns="'.$ns.'/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $workbook = '<workbook xmlns="'.$ns.'/spreadsheetml/2006/main" xmlns:r="'.$ns.'/officeDocument/2006/relationships"><sheets>';
        $rels = '<Relationships xmlns="'.$ns.'/package/2006/relationships">';
        try {
            foreach (['Ringkasan' => [$ringkasan, [48, 100]], 'Pengunjung' => [$daftar, [8, 28, 30, 24, 42, 18, 24, 55, 25, 18, 45]]] as $name => [$values, $widths]) {
                $index = $name === 'Ringkasan' ? 1 : 2;
                $workbook .= '<sheet name="'.$name.'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
                $rels .= '<Relationship Id="rId'.$index.'" Type="'.$ns.'/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
                $types .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                if (! $zip->addFromString('xl/worksheets/sheet'.$index.'.xml', $this->sheet($values, $widths))) {
                    throw new RuntimeException('Data ekspor tidak dapat ditulis.');
                }
            }
            $files = ['[Content_Types].xml' => $types.'</Types>',
                '_rels/.rels' => '<Relationships xmlns="'.$ns.'/package/2006/relationships"><Relationship Id="rId1" Type="'.$ns.'/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => $workbook.'</sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => $rels.'<Relationship Id="rId3" Type="'.$ns.'/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
                'xl/styles.xml' => '<styleSheet xmlns="'.$ns.'/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF15477A"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>'];
            foreach ($files as $name => $xml) {
                if (! $zip->addFromString($name, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$xml)) {
                    throw new RuntimeException('Berkas ekspor tidak dapat ditulis.');
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('Berkas ekspor tidak dapat diselesaikan.');
            }
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($path);
            throw $e;
        }

        return $path;
    }

    private function sheet(array $rows, array $widths): string
    {
        $x = new XMLWriter;
        $x->openMemory();
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('worksheet');
        $x->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $x->startElement('sheetViews');
        $x->startElement('sheetView');
        $x->writeAttribute('workbookViewId', '0');
        $x->startElement('pane');
        foreach (['ySplit' => '1', 'topLeftCell' => 'A2', 'activePane' => 'bottomLeft', 'state' => 'frozen'] as $k => $v) {
            $x->writeAttribute($k, $v);
        }
        $x->endElement();
        $x->endElement();
        $x->endElement();
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
        foreach ($rows as $i => $row) {
            $x->startElement('row');
            $x->writeAttribute('r', (string) ($i + 1));
            if ($i === 0) {
                $x->writeAttribute('ht', '30');
                $x->writeAttribute('customHeight', '1');
            }
            foreach ($row as $j => $value) {
                $x->startElement('c');
                $x->writeAttribute('r', chr(65 + $j).($i + 1));
                $x->writeAttribute('s', $i === 0 ? '1' : '0');
                if (is_int($value) || is_float($value)) {
                    $x->writeElement('v', (string) $value);
                } else {
                    $x->writeAttribute('t', 'inlineStr');
                    $x->startElement('is');
                    $x->startElement('t');
                    $x->writeAttribute('xml:space', 'preserve');
                    $x->text(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value) ?? '');
                    $x->endElement();
                    $x->endElement();
                }
                $x->endElement();
            }
            $x->endElement();
        }
        $x->endElement();
        $x->startElement('autoFilter');
        $x->writeAttribute('ref', 'A1:'.chr(64 + count($widths)).count($rows));
        $x->endElement();
        $x->endElement();
        $x->endDocument();

        return $x->outputMemory();
    }
}
