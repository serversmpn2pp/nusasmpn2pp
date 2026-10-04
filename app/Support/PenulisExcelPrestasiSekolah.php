<?php

namespace App\Support;

use App\Models\PrestasiSekolah;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;
use XMLWriter;
use ZipArchive;

class PenulisExcelPrestasiSekolah
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function buat(Collection $daftar, array $filter): string
    {
        $folder = storage_path('app/exports');
        if (! is_dir($folder) && ! mkdir($folder, 0755, true) && ! is_dir($folder)) {
            throw new RuntimeException('Direktori ekspor belum tersedia.');
        }
        $path = $folder.'/prestasi-'.bin2hex(random_bytes(12)).'.xlsx';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas Excel belum dapat dibuat.');
        }
        try {
            $paket = [
                '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
                '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Prestasi Sekolah" sheetId="1" r:id="rId1"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
                'xl/styles.xml' => '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F0F7"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
                'xl/worksheets/sheet1.xml' => $this->sheet($daftar, $filter),
            ];
            foreach ($paket as $name => $isi) {
                if (! $zip->addFromString($name, $isi)) {
                    throw new RuntimeException('Isi Excel belum dapat ditulis.');
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('Excel belum dapat diselesaikan.');
            }
        } catch (Throwable $e) {
            @$zip->close();
            @unlink($path);
            throw $e;
        }

        return $path;
    }

    private function sheet(Collection $daftar, array $filter): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->writeRaw('<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        $xml->startElement('cols');
        foreach ([7, 18, 38, 24, 18, 22, 22, 30, 20, 16, 24, 45, 20, 30, 25, 30, 20, 22, 45, 45] as $i => $width) {
            $xml->startElement('col');
            foreach (['min' => $i + 1, 'max' => $i + 1, 'width' => $width, 'customWidth' => 1] as $key => $value) {
                $xml->writeAttribute($key, (string) $value);
            } $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('sheetData');
        $this->row($xml, 1, ['DATABASE PRESTASI - SMP NEGERI 2 PADANG PANJANG'], 1);
        $this->row($xml, 2, ['Diekspor '.now()->format('d-m-Y H:i').' WIB | '.$daftar->count().' catatan prestasi'], 2);
        $labels = ['tahun' => 'Tahun', 'tahun_pelajaran_id' => 'ID tahun pelajaran', 'kata_kunci' => 'Pencarian', 'kategori' => 'Kategori', 'tingkat' => 'Tingkat', 'perolehan' => 'Perolehan', 'penerima' => 'Penerima', 'status' => 'Status'];
        $options = ['kategori' => PrestasiSekolah::KATEGORI, 'tingkat' => PrestasiSekolah::TINGKAT, 'perolehan' => PrestasiSekolah::PEROLEHAN, 'penerima' => PrestasiSekolah::PENERIMA, 'status' => ['aktif' => 'Draf & terverifikasi', 'semua' => 'Semua status', ...PrestasiSekolah::STATUS]];
        $filter['status'] ??= 'aktif';
        $this->row($xml, 3, [collect($filter)->except('tab')->filter(fn ($v) => filled($v))->map(fn ($v, $k) => ($labels[$k] ?? $k).': '.($options[$k][$v] ?? $v))->implode(' | ')], 2);
        $this->row($xml, 5, ['No.', 'Tanggal prestasi', 'Kegiatan / lomba', 'Cabang / bidang', 'Kategori', 'Tingkat', 'Perolehan', 'Capaian', 'Jenis penerima', 'Bentuk', 'Nama tim', 'Penerima / anggota tim', 'Tahun pelajaran', 'Penyelenggara', 'Tempat', 'Pembina', 'Status', 'Bukti tersimpan', 'Tautan publik', 'Catatan'], 1);
        foreach ($daftar->values() as $i => $p) {
            $peserta = $p->penerima === 'sekolah' ? 'SMP Negeri 2 Padang Panjang' : $p->peserta->map(fn ($s) => $s->nama.($s->kelas ? ' ('.$s->kelas.')' : ''))->implode("\n");
            $this->row($xml, $i + 6, [$i + 1, $p->tanggal_prestasi->format('d-m-Y'), $p->nama_kegiatan, $p->cabang, PrestasiSekolah::KATEGORI[$p->kategori], PrestasiSekolah::TINGKAT[$p->tingkat], PrestasiSekolah::PEROLEHAN[$p->perolehan], $p->capaian, PrestasiSekolah::PENERIMA[$p->penerima], PrestasiSekolah::BENTUK[$p->bentuk], $p->nama_tim, $peserta, $p->tahunPelajaran?->nama, $p->penyelenggara, $p->tempat, $p->pembina, PrestasiSekolah::STATUS[$p->status], $p->riwayat_dokumen_humas_id ? 'Ada (akses privat)' : 'Tidak ada', $p->tautan, $p->catatan], 0);
        }
        $xml->endElement();
        $xml->writeRaw('<autoFilter ref="A5:T'.max(5, $daftar->count() + 5).'"/><mergeCells count="3"><mergeCell ref="A1:T1"/><mergeCell ref="A2:T2"/><mergeCell ref="A3:T3"/></mergeCells><pageMargins left=".25" right=".25" top=".5" bottom=".5" header=".2" footer=".2"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>');
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function row(XMLWriter $xml, int $number, array $values, int $style): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);
        foreach ($values as $i => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', chr(65 + $i).$number);
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
