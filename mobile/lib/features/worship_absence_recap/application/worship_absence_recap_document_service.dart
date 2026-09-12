import 'dart:typed_data';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/worship_absence_recap/domain/worship_absence_recap.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

abstract interface class WorshipAbsenceRecapDocumentService {
  Future<bool> printReport(WorshipAbsenceRecapPage page);
  Future<bool> shareReport(WorshipAbsenceRecapPage page);
}

final worshipAbsenceRecapDocumentServiceProvider =
    Provider<WorshipAbsenceRecapDocumentService>(
      (ref) => PdfWorshipAbsenceRecapDocumentService(),
    );

final class PdfWorshipAbsenceRecapDocumentService
    implements WorshipAbsenceRecapDocumentService {
  PdfWorshipAbsenceRecapDocumentService({
    WorshipAbsenceRecapPdfBuilder? builder,
  }) : _builder = builder ?? WorshipAbsenceRecapPdfBuilder();

  final WorshipAbsenceRecapPdfBuilder _builder;

  @override
  Future<bool> printReport(WorshipAbsenceRecapPage page) async {
    final bytes = await _builder.build(page);
    return Printing.layoutPdf(
      name: _fileName(page),
      format: PdfPageFormat.a4.landscape,
      dynamicLayout: false,
      onLayout: (_) async => bytes,
    );
  }

  @override
  Future<bool> shareReport(WorshipAbsenceRecapPage page) async {
    final bytes = await _builder.build(page);
    return Printing.sharePdf(
      bytes: bytes,
      filename: _fileName(page),
      subject: 'Rekap berhalangan ibadah ${page.monthLabel}',
      body: 'Dokumen internal dan privat NUSA untuk ${page.monthLabel}.',
    );
  }

  String _fileName(WorshipAbsenceRecapPage page) =>
      'rekap-berhalangan-ibadah-${page.month}.pdf';
}

class WorshipAbsenceRecapPdfBuilder {
  static const _primary = PdfColor.fromInt(0xFF15477A);
  static const _accent = PdfColor.fromInt(0xFFF1C40F);
  static const _border = PdfColor.fromInt(0xFFD8E2EE);
  static const _surface = PdfColor.fromInt(0xFFF5F8FC);

  Future<Uint8List> build(WorshipAbsenceRecapPage page) async {
    final document = pw.Document();
    document.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.all(28),
        header: (_) => _header(page),
        footer: (context) => pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text(
              'Dokumen privat - catatan percakapan sengaja tidak disertakan.',
              style: const pw.TextStyle(fontSize: 7, color: PdfColors.grey700),
            ),
            pw.Text(
              'Halaman ${context.pageNumber} dari ${context.pagesCount}',
              style: const pw.TextStyle(fontSize: 7, color: PdfColors.grey700),
            ),
          ],
        ),
        build: (_) => [
          pw.Row(
            children: [
              _summary('Periode', page.summary.periods),
              pw.SizedBox(width: 6),
              _summary('Siswi', page.summary.students),
              pw.SizedBox(width: 6),
              _summary('Dipantau', page.summary.active),
              pw.SizedBox(width: 6),
              _summary('Perlu konfirmasi', page.summary.needsConfirmation),
              pw.SizedBox(width: 6),
              _summary('Selesai', page.summary.completed),
            ],
          ),
          pw.SizedBox(height: 13),
          if (page.items.isEmpty)
            pw.Container(
              width: double.infinity,
              padding: const pw.EdgeInsets.all(18),
              decoration: pw.BoxDecoration(
                color: _surface,
                border: pw.Border.all(color: _border),
              ),
              child: pw.Text(
                'Tidak ada periode berhalangan untuk filter terpilih.',
                textAlign: pw.TextAlign.center,
              ),
            )
          else
            pw.TableHelper.fromTextArray(
              headers: const [
                'No.',
                'Siswi / NISN',
                'Kelas',
                'Periode',
                'Durasi',
                'Scan bulan ini',
                'Konfirmasi bulan ini',
                'Konfirmasi terakhir',
                'Status',
              ],
              data: [
                for (final entry in page.items.indexed)
                  [
                    entry.$1 + 1,
                    '${entry.$2.student.name}\nNISN ${entry.$2.student.nisn ?? '-'}',
                    entry.$2.schoolClass.name,
                    '${entry.$2.startDateLabel} - ${entry.$2.endDateLabel}',
                    '${entry.$2.durationDays} hari',
                    '${entry.$2.monthlyScans}',
                    '${entry.$2.monthlyConfirmations}',
                    entry.$2.lastConfirmation == null
                        ? '-'
                        : '${entry.$2.lastConfirmation!.resultLabel}\n${entry.$2.lastConfirmation!.dateLabel}',
                    '${entry.$2.statusLabel}${entry.$2.completionMethod == null ? '' : '\n${entry.$2.completionMethod}'}',
                  ],
              ],
              headerDecoration: const pw.BoxDecoration(color: _primary),
              headerStyle: pw.TextStyle(
                color: PdfColors.white,
                fontSize: 7,
                fontWeight: pw.FontWeight.bold,
              ),
              cellStyle: const pw.TextStyle(fontSize: 7),
              cellPadding: const pw.EdgeInsets.all(4),
              border: pw.TableBorder.all(color: _border, width: .5),
              oddRowDecoration: const pw.BoxDecoration(color: _surface),
            ),
        ],
      ),
    );
    return document.save();
  }

  pw.Widget _header(WorshipAbsenceRecapPage page) => pw.Container(
    margin: const pw.EdgeInsets.only(bottom: 12),
    padding: const pw.EdgeInsets.only(bottom: 9),
    decoration: const pw.BoxDecoration(
      border: pw.Border(bottom: pw.BorderSide(color: _accent, width: 2)),
    ),
    child: pw.Row(
      crossAxisAlignment: pw.CrossAxisAlignment.end,
      children: [
        pw.Expanded(
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Text(
                'REKAP BERHALANGAN IBADAH',
                style: pw.TextStyle(
                  color: _primary,
                  fontSize: 17,
                  fontWeight: pw.FontWeight.bold,
                ),
              ),
              pw.SizedBox(height: 3),
              pw.Text(
                '${page.monthLabel} | Tahun Pelajaran ${page.academicYear?.name ?? '-'}',
                style: const pw.TextStyle(fontSize: 8),
              ),
            ],
          ),
        ),
        pw.Column(
          crossAxisAlignment: pw.CrossAxisAlignment.end,
          children: [
            pw.Text(
              'INTERNAL & PRIVAT',
              style: pw.TextStyle(
                color: PdfColors.red800,
                fontSize: 8,
                fontWeight: pw.FontWeight.bold,
              ),
            ),
            pw.Text(
              'Dicetak ${page.printedAt} WIB',
              style: const pw.TextStyle(fontSize: 7),
            ),
          ],
        ),
      ],
    ),
  );

  pw.Widget _summary(String label, int value) => pw.Expanded(
    child: pw.Container(
      padding: const pw.EdgeInsets.all(8),
      decoration: pw.BoxDecoration(
        color: _surface,
        border: pw.Border.all(color: _border),
      ),
      child: pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Text(label, style: const pw.TextStyle(fontSize: 7)),
          pw.SizedBox(height: 2),
          pw.Text(
            '$value',
            style: pw.TextStyle(
              color: _primary,
              fontSize: 13,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
        ],
      ),
    ),
  );
}
