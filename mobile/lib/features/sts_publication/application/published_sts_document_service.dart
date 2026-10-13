import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'package:nusa/features/sts_publication/data/sts_publication_repository.dart';

class PublishedStsDocumentService {
  Future<bool> printReport(StsData data) => Printing.layoutPdf(
    name: 'rapor-sts-versi-${data['versi']}.pdf',
    onLayout: (_) => build(data),
  );
  Future<Uint8List> build(StsData data) async {
    final r = stsMap(data['rapor']);
    final city = pw.MemoryImage(
      (await rootBundle.load('assets/images/logo-padang-panjang.png')).buffer
          .asUint8List(),
    );
    final school = pw.MemoryImage(
      (await rootBundle.load('assets/images/logo-smpn2pp.png')).buffer
          .asUint8List(),
    );
    final doc = pw.Document(
      theme: pw.ThemeData.withFont(
        base: pw.Font.ttf(
          await rootBundle.load('assets/fonts/roboto-regular.ttf'),
        ),
        bold: pw.Font.ttf(
          await rootBundle.load('assets/fonts/roboto-bold.ttf'),
        ),
      ),
    );
    pw.Widget header(String title) => pw.Column(
      children: [
        pw.Row(
          crossAxisAlignment: pw.CrossAxisAlignment.center,
          children: [
            pw.Image(city, width: 45, height: 58, fit: pw.BoxFit.contain),
            pw.SizedBox(width: 12),
            pw.Expanded(
              child: pw.Column(
                children: [
                  pw.Text(
                    'SMP NEGERI 2 PADANG PANJANG',
                    textAlign: pw.TextAlign.center,
                    style: pw.TextStyle(
                      fontWeight: pw.FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                  pw.Text(
                    title,
                    textAlign: pw.TextAlign.center,
                    style: const pw.TextStyle(fontSize: 11),
                  ),
                  pw.Text(
                    'SUMATIF TENGAH SEMESTER ${stsMap(r['kegiatan'])['semester'] == 'ganjil' ? 'I' : 'II'}',
                    style: const pw.TextStyle(fontSize: 10),
                  ),
                  pw.Text(
                    'TAHUN PELAJARAN ${stsText(stsMap(r['kegiatan'])['tahun_pelajaran'])}',
                    style: const pw.TextStyle(fontSize: 10),
                  ),
                ],
              ),
            ),
            pw.SizedBox(width: 12),
            pw.Image(school, width: 52, height: 52, fit: pw.BoxFit.contain),
          ],
        ),
        pw.Divider(),
        pw.Text(
          'Nama: ${stsText(stsMap(r['siswa'])['nama'])}     Kelas: ${stsText(stsMap(r['kelas'])['nama'])}',
          style: pw.TextStyle(fontWeight: pw.FontWeight.bold),
        ),
        pw.SizedBox(height: 14),
      ],
    );
    pw.Widget footer(pw.Context c) => pw.Align(
      alignment: pw.Alignment.centerRight,
      child: pw.Text(
        'Versi ${data['versi']} - Terbit ${stsDate(data['dipublikasikan_pada'])} - Halaman ${c.pageNumber}',
        style: const pw.TextStyle(fontSize: 8),
      ),
    );
    String score(Object? v) =>
        v is num ? v.toStringAsFixed(2).replaceAll('.', ',') : '-';
    pw.Widget signatures(List<({String role, StsData person})> entries) =>
        pw.Row(
          crossAxisAlignment: pw.CrossAxisAlignment.start,
          children: [
            for (final e in entries)
              pw.Expanded(
                child: pw.Column(
                  children: [
                    pw.Text(
                      e.role,
                      textAlign: pw.TextAlign.center,
                      style: const pw.TextStyle(fontSize: 9),
                    ),
                    pw.SizedBox(height: 48),
                    pw.Text(
                      e.person.isEmpty
                          ? '(....................................)'
                          : stsText(e.person['nama_lengkap']),
                      textAlign: pw.TextAlign.center,
                      style: pw.TextStyle(
                        fontSize: 9,
                        fontWeight: pw.FontWeight.bold,
                      ),
                    ),
                    if (e.person.isNotEmpty)
                      pw.Text(
                        'NIP. ${stsText(e.person['nip'])}',
                        style: const pw.TextStyle(fontSize: 8),
                      ),
                  ],
                ),
              ),
          ],
        );
    pw.Widget table(List<String> headers, List<List<String>> rows) =>
        pw.TableHelper.fromTextArray(
          headers: headers,
          data: rows,
          headerStyle: pw.TextStyle(
            fontWeight: pw.FontWeight.bold,
            fontSize: 9,
          ),
          cellStyle: const pw.TextStyle(fontSize: 9),
          headerDecoration: const pw.BoxDecoration(color: PdfColors.blue50),
          cellPadding: const pw.EdgeInsets.all(7),
        );
    doc.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.all(36),
        header: (_) => header('LAPORAN HASIL CAPAIAN PEMBELAJARAN'),
        footer: footer,
        build: (_) => [
          table(
            ['No.', 'Mata pelajaran', 'Nilai', 'Keterangan'],
            [
              for (final (i, n) in stsItems(r['nilai']).indexed)
                [
                  '${i + 1}',
                  stsText(n['mapel']),
                  score(n['nilai']),
                  stsText(n['keterangan']),
                ],
              ['', 'Jumlah', score(r['jumlah']), ''],
              ['', 'Rata-rata', score(r['rata']), ''],
            ],
          ),
          if (r['nilai_tuntas'] != true)
            pw.Padding(
              padding: const pw.EdgeInsets.only(top: 8),
              child: pw.Text(
                'Jumlah dan rata-rata belum dihitung karena ada nilai belum tersedia.',
                style: const pw.TextStyle(fontSize: 8),
              ),
            ),
          pw.SizedBox(height: 18),
          table(
            ['Sakit', 'Izin', 'Alfa', 'Terlambat', 'Total terlambat'],
            [
              [
                '${stsInt(stsMap(r['kehadiran'])['sakit'])} hari',
                '${stsInt(stsMap(r['kehadiran'])['izin'])} hari',
                '${stsInt(stsMap(r['kehadiran'])['alfa'])} hari',
                '${stsInt(stsMap(r['keterlambatan'])['jumlah'])} kali',
                '${stsInt(stsMap(r['keterlambatan'])['total_menit'])} menit',
              ],
            ],
          ),
          pw.SizedBox(height: 18),
          pw.Text(
            'Padang Panjang, ${stsDate(stsMap(r['periode'])['tanggal_rapor'])}',
            textAlign: pw.TextAlign.right,
          ),
          pw.SizedBox(height: 10),
          signatures([
            (role: 'Orang Tua / Wali Siswa', person: {}),
            (role: 'Wali Kelas', person: stsMap(r['wali_kelas'])),
          ]),
          pw.SizedBox(height: 16),
          pw.Text(
            'Periode presensi: ${stsDate(stsMap(r['periode'])['awal'])} - ${stsDate(stsMap(r['periode'])['akhir'])}',
            style: const pw.TextStyle(fontSize: 8),
          ),
          pw.Text(
            'Kriteria: < 70 Perlu Bimbingan; 70 - < 80 Cukup; 80 - < 90 Baik; 90 - 100 Sangat Baik.',
            style: const pw.TextStyle(fontSize: 8),
          ),
        ],
      ),
    );
    final l = stsMap(r['perilaku']);
    if (l.isNotEmpty) {
      doc.addPage(
        pw.MultiPage(
          pageFormat: PdfPageFormat.a4,
          margin: const pw.EdgeInsets.all(36),
          header: (_) => header('LAPORAN PERILAKU DAN PEMBINAAN SISWA'),
          footer: footer,
          build: (_) => [
            pw.Text(
              stsText(l['label_isi']),
              style: const pw.TextStyle(fontSize: 9),
            ),
            pw.SizedBox(height: 10),
            if (stsItems(l['baris']).isEmpty)
              pw.Text(stsText(l['pesan_kosong']))
            else
              table(
                [
                  'No.',
                  'Tanggal',
                  'Kejadian / pelanggaran',
                  'Teguran / tindak lanjut',
                  'Poin',
                ],
                [
                  for (final (i, b) in stsItems(l['baris']).indexed)
                    [
                      '${i + 1}',
                      stsDate(b['tanggal']),
                      stsText(b['kejadian']),
                      stsText(b['tindakan']),
                      '${stsInt(b['poin'])}',
                    ],
                ],
              ),
            pw.SizedBox(height: 14),
            table(
              ['Kejadian', 'Poin masuk', 'Pengurangan', 'Saldo'],
              [
                [
                  for (final k in [
                    'jumlah_kejadian',
                    'poin_masuk',
                    'poin_dikurangi',
                    'saldo',
                  ])
                    '${stsInt(stsMap(l['ringkasan'])[k])}',
                ],
              ],
            ),
            if (l['catatan'] != null)
              pw.Padding(
                padding: const pw.EdgeInsets.symmetric(vertical: 12),
                child: pw.Text('Catatan pembinaan: ${l['catatan']}'),
              ),
            pw.SizedBox(height: 20),
            signatures([
              (role: 'Orang Tua / Wali Siswa', person: {}),
              (
                role: 'Guru BK Tingkat ${stsMap(r['kelas'])['tingkat']}',
                person: stsMap(l['guru_bk']),
              ),
              (role: 'Wakil Kesiswaan', person: stsMap(l['wakil_kesiswaan'])),
            ]),
          ],
        ),
      );
    }
    return doc.save();
  }
}

final publishedStsDocumentServiceProvider = Provider(
  (ref) => PublishedStsDocumentService(),
);
