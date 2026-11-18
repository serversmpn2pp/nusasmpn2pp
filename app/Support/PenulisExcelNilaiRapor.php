<?php

namespace App\Support;

use RuntimeException;
use XMLWriter;
use ZipArchive;

class PenulisExcelNilaiRapor
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const HEADER = 5;

    public function buat(array $laporan): string
    {
        $penugasan = $laporan['guruMataPelajaranDipilih'];
        $predikat = $penugasan->mataPelajaran->menggunakanPredikat();
        $skema = $laporan['skemaBobotNilai'];
        $jenis = ['formatif' => 'Formatif', 'sumatif' => 'Sumatif Harian', 'sts' => 'STS', 'sas_saj' => $laporan['labelNilaiAkhir']];
        $bobot = collect($jenis)->map(fn ($label, $kode) => $predikat || ! $skema ? null : $skema->{'bobot_'.$kode} / 100);
        $bobotValid = $bobot->every(fn ($nilai) => $nilai !== null && $nilai >= 0) && round($bobot->sum(), 10) === 1.0;

        $komponen = collect(array_keys($jenis))->flatMap(fn ($kode) => $laporan['komponenNilai']->where('jenis_komponen', $kode))->values();
        if ($komponen->count() > 16380) {
            throw new RuntimeException('Jumlah komponen melebihi batas kolom Excel.');
        }
        $kolomAkhir = $this->kolom($komponen->count() + 4);
        $judul = ['A' => $this->sel('No.', 4), 'B' => $this->sel('Nama', 4), 'C' => $this->sel('Kelas', 4)];
        $grup = ['A' => $this->sel('Identitas Siswa', 3), $kolomAkhir => $this->sel('Hasil', 3)];
        $gabung = ['A1:'.$kolomAkhir.'1', 'A2:'.$kolomAkhir.'2', 'A3:'.$kolomAkhir.'3', 'A4:C4'];
        $rentang = [];
        $nilaiPerKomponen = [];
        foreach ($komponen as $index => $item) {
            $kolom = $this->kolom($index + 4);
            $judul[$kolom] = $this->sel($item->nama, 4);
            $rentang[$item->jenis_komponen][] = $kolom;
            $nilaiPerKomponen[$item->id] = $item->nilaiSiswa->keyBy('siswa_id');
        }
        foreach ($rentang as $kode => $kolom) {
            $grup[$kolom[0]] = $this->sel($jenis[$kode], 3);
            if (count($kolom) > 1) {
                $gabung[] = $kolom[0].'4:'.end($kolom).'4';
            }
        }
        $judul[$kolomAkhir] = $this->sel('Nilai Akhir Sementara', 4);
        $konteks = implode(' | ', [
            $penugasan->mataPelajaran->nama, $penugasan->kelas->nama,
            $penugasan->tahunPelajaran->nama, 'Semester '.ucfirst($laporan['semester']),
        ]);
        $keterangan = match (true) {
            $predikat => 'Penilaian predikat: nilai akhir angka tidak dihitung.',
            ! $skema => 'Skema bobot belum tersedia. Nilai Akhir Sementara belum dihitung.',
            ! $bobotValid => 'Skema bobot tidak valid. Nilai Akhir Sementara belum dihitung.',
            default => 'Nilai kosong dihitung 0 hanya di file ini. Lihat lembar Bobot.',
        };
        $baris = [
            1 => ['A' => $this->sel('REKAP NILAI SEMENTARA', 1)],
            2 => ['A' => $this->sel($konteks, 2)],
            3 => ['A' => $this->sel($keterangan, 2)],
            4 => $grup,
            self::HEADER => $judul,
        ];
        $siswa = $laporan['rekapNilai']
            ->filter(fn ($item) => (int) $item['anggota_kelas']->tahun_pelajaran_id === (int) $penugasan->tahun_pelajaran_id)
            ->unique('anggota_kelas.siswa_id')->values();
        foreach ($siswa as $index => $item) {
            $nomor = self::HEADER + 1 + $index;
            $anggota = $item['anggota_kelas'];
            $baris[$nomor] = [
                'A' => $this->sel($index + 1, 6),
                'B' => $this->sel($anggota->siswa?->nama_lengkap ?? '-', 6),
                'C' => $this->sel($penugasan->kelas->nama, 6),
            ];
            $jumlahPerJenis = [];
            foreach ($komponen as $i => $data) {
                $nilai = $nilaiPerKomponen[$data->id]->get($anggota->siswa_id);
                $isi = $predikat ? $nilai?->predikat : ($nilai?->nilai === null ? null : (float) $nilai->nilai);
                $baris[$nomor][$this->kolom($i + 4)] = $this->sel($isi, $predikat ? 6 : 5);
                if (is_float($isi)) {
                    $kode = $data->jenis_komponen;
                    $jumlahPerJenis[$kode] = ($jumlahPerJenis[$kode] ?? 0) + $isi;
                }
            }
            $baris[$nomor][$kolomAkhir] = $this->sel(
                $bobotValid ? $this->nilaiAkhirSementara($jumlahPerJenis, $rentang, $bobot->all()) : null,
                7,
                $predikat ? null : $this->rumusAkhirSementara($nomor, array_keys($jenis), $rentang),
            );
        }
        $akhir = self::HEADER + $siswa->count();
        $headerBobot = [1 => [
            'A' => $this->sel('Jenis penilaian', 3), 'B' => $this->sel('Bobot', 3), 'C' => $this->sel('Komponen aktif', 3),
        ]];
        foreach (array_keys($jenis) as $index => $kode) {
            $jumlah = count($rentang[$kode] ?? []);
            $headerBobot[$index + 2] = [
                'A' => $this->sel($jenis[$kode], 6),
                'B' => $this->sel($bobot[$kode], 8),
                'C' => $this->sel($jumlah > 0 ? $jumlah : 'Komponen belum dibuat', 6),
            ];
        }
        $headerBobot[6] = [
            'A' => $this->sel('Total bobot', 4),
            'B' => $this->sel($bobot->containsStrict(null) ? null : $bobot->sum(), 8, 'IF(COUNT(B2:B5)=4,SUM(B2:B5),"")'),
        ];
        $headerBobot[8] = ['A' => $this->sel($predikat ? 'Tidak menggunakan bobot angka.' : ($skema
            ? 'Skema NUSA: '.$skema->labelTingkat().' | '.$penugasan->tahunPelajaran->nama.' | '.ucfirst($laporan['semester'])
            : 'Skema bobot belum tersedia di NUSA.'), 2)];

        $lembar = $this->lembar(
            $baris,
            [6, 36, 22, ...array_fill(0, $komponen->count(), 24), 24],
            $gabung,
            ['xSplit' => '3', 'ySplit' => (string) self::HEADER, 'topLeftCell' => 'D6', 'activePane' => 'bottomRight', 'state' => 'frozen'],
            'A'.self::HEADER.':'.$kolomAkhir.$akhir,
            ! $predikat && $siswa->isNotEmpty() ? ['D6:'.$this->kolom($komponen->count() + 3).$akhir, '100'] : null,
            [1 => 28, 2 => 22, 3 => 22, 4 => 24, self::HEADER => max(43, 16 * (int) $komponen->max(
                fn ($item) => substr_count(wordwrap($item->nama, 22, "\n", true), "\n") + 1,
            ) + 6)],
        );
        $lembarBobot = $this->lembar($headerBobot, [32, 14, 27], ['A8:C8'], validasi: $predikat ? null : ['B2:B5', '1'], tinggi: [1 => 25, 8 => 28]);

        return $this->arsip($lembar, $lembarBobot);
    }

    private function nilaiAkhirSementara(array $jumlahPerJenis, array $rentang, array $bobot): float
    {
        $total = 0.0;
        foreach ($rentang as $kode => $kolom) {
            $total += round(($jumlahPerJenis[$kode] ?? 0) / count($kolom), 2) * $bobot[$kode];
        }

        return round($total, 2);
    }

    private function rumusAkhirSementara(int $baris, array $jenis, array $rentang): string
    {
        $bobot = 'Bobot!$B$2:$B$5';
        $syarat = ['COUNT('.$bobot.')=4', 'ROUND(SUM('.$bobot.'),10)=1', 'MIN('.$bobot.')>=0'];
        $bagian = [];
        // SUM / COLUMNS menyertakan sel kosong dalam penyebut tanpa mengubah isinya.
        foreach ($jenis as $i => $kode) {
            $selBobot = 'Bobot!$B$'.($i + 2);
            $kolom = $rentang[$kode] ?? [];
            if ($kolom === []) {
                continue;
            }
            $ref = $kolom[0].$baris.':'.end($kolom).$baris;
            $bagian[] = 'IF('.$selBobot.'=0,0,ROUND(SUM('.$ref.')/COLUMNS('.$ref.'),2)*'.$selBobot.')';
        }

        return 'IFERROR(IF(AND('.implode(',', $syarat).'),ROUND(SUM('.implode(',', $bagian ?: ['0']).'),2),""),"")';
    }

    private function sel(string|int|float|null $nilai, int $style, ?string $rumus = null): array
    {
        return [$nilai, $style, $rumus];
    }

    private function kolom(int $nomor): string
    {
        $huruf = '';
        while ($nomor > 0) {
            $huruf = chr(65 + ($nomor - 1) % 26).$huruf;
            $nomor = intdiv($nomor - 1, 26);
        }

        return $huruf;
    }

    private function lembar(
        array $baris,
        array $lebar,
        array $gabung,
        ?array $panel = null,
        ?string $filter = null,
        ?array $validasi = null,
        array $tinggi = [],
    ): string {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElementNS(null, 'worksheet', self::NS);
        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->writeAttribute('showGridLines', '0');
        if ($panel) {
            $this->elemenAtribut($xml, 'pane', $panel);
        }
        $xml->endElement();
        $xml->endElement();
        $this->elemenAtribut($xml, 'sheetFormatPr', ['defaultRowHeight' => '20']);
        $xml->startElement('cols');
        foreach ($lebar as $index => $width) {
            $this->elemenAtribut($xml, 'col', ['min' => $index + 1, 'max' => $index + 1, 'width' => $width, 'customWidth' => 1]);
        }
        $xml->endElement();
        $xml->startElement('sheetData');
        $posisi = array_flip(array_map($this->kolom(...), range(1, count($lebar))));
        foreach ($baris as $nomor => $daftarSel) {
            $xml->startElement('row');
            $xml->writeAttribute('r', (string) $nomor);
            if (isset($tinggi[$nomor])) {
                $xml->writeAttribute('ht', (string) $tinggi[$nomor]);
                $xml->writeAttribute('customHeight', '1');
            }
            uksort($daftarSel, fn ($a, $b) => $posisi[$a] <=> $posisi[$b]);
            foreach ($daftarSel as $kolom => [$nilai, $style, $rumus]) {
                $xml->startElement('c');
                $xml->writeAttribute('r', $kolom.$nomor);
                $xml->writeAttribute('s', (string) $style);
                if ($rumus !== null) {
                    $xml->writeAttribute('t', $nilai === null ? 'str' : 'n');
                    $xml->writeElement('f', $rumus);
                    $xml->writeElement('v', (string) $nilai);
                } elseif (is_string($nilai)) {
                    $xml->writeAttribute('t', 'inlineStr');
                    $xml->startElement('is');
                    $xml->startElement('t');
                    $xml->writeAttribute('xml:space', 'preserve');
                    $xml->text(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $nilai) ?? '');
                    $xml->endElement();
                    $xml->endElement();
                } elseif ($nilai !== null) {
                    $xml->writeElement('v', (string) $nilai);
                }
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
        if ($filter) {
            $this->elemenAtribut($xml, 'autoFilter', ['ref' => $filter]);
        }
        $xml->startElement('mergeCells');
        $xml->writeAttribute('count', (string) count($gabung));
        foreach ($gabung as $sel) {
            $this->elemenAtribut($xml, 'mergeCell', ['ref' => $sel]);
        }
        $xml->endElement();
        if ($validasi) {
            $xml->startElement('dataValidations');
            $xml->writeAttribute('count', '1');
            $xml->startElement('dataValidation');
            foreach (['type' => 'decimal', 'operator' => 'between', 'allowBlank' => '1', 'showErrorMessage' => '1', 'sqref' => $validasi[0]] as $attr => $nilai) {
                $xml->writeAttribute($attr, $nilai);
            }
            $xml->writeElement('formula1', '0');
            $xml->writeElement('formula2', $validasi[1]);
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function elemenAtribut(XMLWriter $xml, string $nama, array $atribut): void
    {
        $xml->startElement($nama);
        foreach ($atribut as $kunci => $nilai) {
            $xml->writeAttribute($kunci, (string) $nilai);
        }
        $xml->endElement();
    }

    private function arsip(string $lembar, string $bobot): string
    {
        $nama = tempnam(sys_get_temp_dir(), 'nusa-nilai-');
        if ($nama === false) {
            throw new RuntimeException('Berkas sementara Excel tidak bisa dibuat.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($nama, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Berkas Excel tidak bisa dibuat.');
            }
            $paket = [
                'xl/worksheets/sheet1.xml' => $lembar,
                'xl/worksheets/sheet2.xml' => $bobot,
            ];
            foreach ([
                '[Content_Types].xml' => 'content-types.xml',
                '_rels/.rels' => 'rels.xml',
                'xl/workbook.xml' => 'workbook.xml',
                'xl/_rels/workbook.xml.rels' => 'workbook-rels.xml',
                'xl/styles.xml' => 'styles.xml',
            ] as $path => $template) {
                $paket[$path] = file_get_contents(resource_path('xlsx/nilai-rapor/'.$template));
            }
            foreach ($paket as $path => $isi) {
                if ($isi === false || ! $zip->addFromString($path, $isi)) {
                    $zip->close();
                    throw new RuntimeException('Isi berkas Excel tidak bisa disimpan.');
                }
            }
            if (! $zip->close() || ($isi = file_get_contents($nama)) === false) {
                throw new RuntimeException('Berkas Excel tidak bisa dibaca.');
            }

            return $isi;
        } finally {
            unlink($nama);
        }
    }
}
