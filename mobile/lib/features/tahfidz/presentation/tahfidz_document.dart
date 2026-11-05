import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../domain/tahfidz.dart';
import 'tahfidz_widgets.dart';

Future<Uint8List> buildTahfidzPdf(
  TfData data, {
  required bool certificate,
  List<TfData>? reports,
}) async {
  final doc = pw.Document(
    theme: pw.ThemeData.withFont(
      base: pw.Font.ttf(
        await rootBundle.load('assets/fonts/roboto-regular.ttf'),
      ),
      bold: pw.Font.ttf(await rootBundle.load('assets/fonts/roboto-bold.ttf')),
    ),
  );
  final school = pw.MemoryImage(
    (await rootBundle.load('assets/images/logo-smpn2pp.png')).buffer
        .asUint8List(),
  );
  for (final document in reports ?? [data]) {
    final data = document;
    doc.addPage(
      pw.MultiPage(
        pageFormat: certificate ? PdfPageFormat.a4.landscape : PdfPageFormat.a4,
        margin: const pw.EdgeInsets.all(40),
        build: (_) => [
          pw.Center(child: pw.Image(school, height: 50)),
          pw.SizedBox(height: 12),
          pw.Center(
            child: pw.Text(
              'SMP NEGERI 2 PADANG PANJANG',
              style: pw.TextStyle(fontSize: 16, fontWeight: pw.FontWeight.bold),
            ),
          ),
          pw.Center(
            child: pw.Text(
              certificate
                  ? 'SERTIFIKAT KELULUSAN TAHFIDZ JUZ 30'
                  : 'RAPOR TAHFIDZ',
              style: const pw.TextStyle(fontSize: 14),
            ),
          ),
          pw.Divider(),
          pw.Text(
            'Nama: ${tfText(data['nama'])}',
            style: pw.TextStyle(fontWeight: pw.FontWeight.bold),
          ),
          if (certificate) ...[
            pw.Text('Nomor sertifikat: ${tfText(data['nomor'])}'),
            pw.SizedBox(height: 25),
            pw.Text(
              'Telah menyelesaikan setoran 37 surah Juz 30 dan dinyatakan lulus Tahfidz.',
            ),
            pw.SizedBox(height: 16),
            pw.Text(
              '${tfText(data['acara'])}\n${tfText(data['lokasi'])}, ${tfDate(data['tanggal'])}',
            ),
          ] else ...[
            pw.Text(
              'Kelas: ${tfText(data['kelas'])}    Tahun pelajaran: ${tfText(data['tahun'])}',
            ),
            pw.Text(
              'Kelompok: ${tfText(data['kelompok'])}    Target: ${tfText(data['target'])} surah',
            ),
            pw.SizedBox(height: 16),
            pw.TableHelper.fromTextArray(
              headers: ['Surah', 'Nilai'],
              data: [
                for (final row in tfItems(data['baris']))
                  [tfText(row['surah'], 'Belum disetor'), tfText(row['nilai'])],
              ],
              headerDecoration: const pw.BoxDecoration(color: PdfColors.blue50),
              cellStyle: const pw.TextStyle(fontSize: 10),
            ),
            pw.SizedBox(height: 12),
            pw.Text(
              'Jumlah: ${tfText(data['jumlah'])}    Rata-rata: ${tfText(data['rata'])}    Predikat: ${tfText(data['predikat'])}',
            ),
            pw.Text('Tanggal rapor: ${tfDate(data['tanggal'])}'),
          ],
          pw.SizedBox(height: 35),
          pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
            children: [
              pw.Text('Pembimbing\n\n\n${tfText(data['pembimbing'])}'),
              if (certificate)
                pw.Text(
                  'Kepala Sekolah\n\n\n${tfText(data['kepala_sekolah'])}',
                ),
            ],
          ),
        ],
      ),
    );
  }
  return doc.save();
}

class TfDocumentView extends StatelessWidget {
  const TfDocumentView(this.data, {this.certificate = false, super.key});
  final TfData data;
  final bool certificate;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(certificate ? 'Sertifikat Tahfidz' : 'Rapor Tahfidz'),
    ),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        TfHero(
          tfText(data['nama']),
          certificate
              ? tfText(data['nomor'])
              : '${tfText(data['kelas'])} · ${tfText(data['tahun'])}',
        ),
        if (certificate) ...[
          TfCard(
            tfText(data['acara']),
            '${tfText(data['lokasi'])}\n${tfDate(data['tanggal'])}',
            icon: Icons.workspace_premium_rounded,
          ),
          const TfNotice('Telah menyelesaikan setoran 37 surah Juz 30.'),
        ] else ...[
          TfNotice(
            'Target ${tfText(data['target'])} surah · Rata-rata ${tfText(data['rata'])}\n${tfText(data['predikat'])}',
          ),
          for (final row in tfItems(data['baris']))
            TfCard(
              tfText(row['surah'], 'Belum disetor'),
              'Nilai ${tfText(row['nilai'])}',
            ),
        ],
        TfCard(
          'Pembimbing',
          tfText(data['pembimbing']),
          icon: Icons.person_outline,
        ),
        if (certificate)
          TfAction('Cetak / Simpan Sertifikat', () async {
            await Printing.layoutPdf(
              name: 'sertifikat-tahfidz.pdf',
              onLayout: (_) => buildTahfidzPdf(data, certificate: true),
            );
          }, icon: Icons.print_outlined),
      ],
    ),
  );
}
