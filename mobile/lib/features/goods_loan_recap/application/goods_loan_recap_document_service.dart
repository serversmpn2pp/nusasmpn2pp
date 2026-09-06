import 'dart:typed_data';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';
import 'package:nusa/features/goods_loan_recap/domain/goods_loan_recap.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

abstract interface class GoodsLoanRecapDocumentService {
  Future<bool> printReport(GoodsLoanRecapPage page);
  Future<bool> shareReport(GoodsLoanRecapPage page);
}

final goodsLoanRecapDocumentServiceProvider =
    Provider<GoodsLoanRecapDocumentService>(
      (ref) => PdfGoodsLoanRecapDocumentService(),
    );

final class PdfGoodsLoanRecapDocumentService
    implements GoodsLoanRecapDocumentService {
  PdfGoodsLoanRecapDocumentService({GoodsLoanRecapPdfBuilder? builder})
    : _builder = builder ?? GoodsLoanRecapPdfBuilder();
  final GoodsLoanRecapPdfBuilder _builder;

  @override
  Future<bool> printReport(GoodsLoanRecapPage page) async {
    final bytes = await _builder.build(page);
    return Printing.layoutPdf(
      name: _fileName(page),
      format: PdfPageFormat.a4.landscape,
      dynamicLayout: false,
      onLayout: (_) async => bytes,
    );
  }

  @override
  Future<bool> shareReport(GoodsLoanRecapPage page) async {
    final bytes = await _builder.build(page);
    return Printing.sharePdf(
      bytes: bytes,
      filename: _fileName(page),
      subject: 'Rekap peminjaman barang NUSA',
      body: '${page.items.length} transaksi sesuai filter rekap.',
    );
  }

  String _fileName(GoodsLoanRecapPage page) {
    final status = page.filter.monitoringStatus.replaceAll('_', '-');
    return 'rekap-peminjaman-$status.pdf';
  }
}

class GoodsLoanRecapPdfBuilder {
  Future<Uint8List> build(GoodsLoanRecapPage page) async {
    final document = pw.Document();
    document.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.all(28),
        header: (context) => pw.Container(
          margin: const pw.EdgeInsets.only(bottom: 12),
          padding: const pw.EdgeInsets.only(bottom: 9),
          decoration: const pw.BoxDecoration(
            border: pw.Border(bottom: pw.BorderSide(color: _accent, width: 2)),
          ),
          child: pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
            crossAxisAlignment: pw.CrossAxisAlignment.end,
            children: [
              pw.Column(
                crossAxisAlignment: pw.CrossAxisAlignment.start,
                children: [
                  pw.Text(
                    'REKAP PEMINJAMAN BARANG',
                    style: pw.TextStyle(
                      color: _primary,
                      fontSize: 17,
                      fontWeight: pw.FontWeight.bold,
                    ),
                  ),
                  pw.Text(
                    'SMP Negeri 2 Padang Panjang',
                    style: pw.TextStyle(
                      fontSize: 9,
                      fontWeight: pw.FontWeight.bold,
                    ),
                  ),
                  pw.Text(
                    'Pemantauan: ${_statusLabel(page)}',
                    style: const pw.TextStyle(fontSize: 8, color: _muted),
                  ),
                ],
              ),
              pw.Text(
                'Dicetak: ${page.printedAt ?? '-'}',
                style: const pw.TextStyle(fontSize: 8, color: _muted),
              ),
            ],
          ),
        ),
        footer: (context) => pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text(
              'Dokumen dihasilkan oleh NUSA SMP Negeri 2 Padang Panjang.',
              style: const pw.TextStyle(fontSize: 7, color: _muted),
            ),
            pw.Text(
              'Halaman ${context.pageNumber} dari ${context.pagesCount}',
              style: const pw.TextStyle(fontSize: 7, color: _muted),
            ),
          ],
        ),
        build: (context) => [
          pw.Row(
            children: [
              _summary('Masih dipinjam', page.summary.active),
              pw.SizedBox(width: 8),
              _summary('Terlambat', page.summary.overdue, danger: true),
              pw.SizedBox(width: 8),
              _summary('Jatuh tempo 7 hari', page.summary.dueSoon),
              pw.SizedBox(width: 8),
              _summary('Tanpa rencana', page.summary.withoutPlan),
            ],
          ),
          pw.SizedBox(height: 12),
          if (page.items.isEmpty)
            pw.Container(
              width: double.infinity,
              padding: const pw.EdgeInsets.all(16),
              color: _surface,
              child: pw.Text(
                'Belum ada transaksi pada pilihan rekap ini.',
                textAlign: pw.TextAlign.center,
                style: const pw.TextStyle(fontSize: 9),
              ),
            )
          else
            pw.TableHelper.fromTextArray(
              headers: const [
                'No.',
                'Transaksi',
                'Peminjam',
                'Barang belum kembali',
                'Rencana kembali',
                'Pemantauan',
              ],
              data: [
                for (final entry in page.items.indexed)
                  [
                    entry.$1 + 1,
                    '${entry.$2.number}\n${entry.$2.dateLabel}',
                    '${entry.$2.borrowerName}\n${entry.$2.borrowerIdentity}',
                    _outstanding(entry.$2),
                    entry.$2.plannedReturnLabel ?? '-',
                    '${entry.$2.monitoringLabel}\n${entry.$2.statusLabel}',
                  ],
              ],
              headerDecoration: const pw.BoxDecoration(color: _primary),
              headerStyle: pw.TextStyle(
                color: PdfColors.white,
                fontSize: 7,
                fontWeight: pw.FontWeight.bold,
              ),
              cellStyle: const pw.TextStyle(fontSize: 7),
              cellPadding: const pw.EdgeInsets.symmetric(
                horizontal: 4,
                vertical: 5,
              ),
              border: pw.TableBorder.all(color: _border, width: .5),
              oddRowDecoration: const pw.BoxDecoration(color: _surface),
              columnWidths: const {
                0: pw.FixedColumnWidth(24),
                1: pw.FlexColumnWidth(1.25),
                2: pw.FlexColumnWidth(1.6),
                3: pw.FlexColumnWidth(2.2),
                4: pw.FlexColumnWidth(1),
                5: pw.FlexColumnWidth(1.2),
              },
            ),
        ],
      ),
    );
    return document.save();
  }

  pw.Widget _summary(String label, int value, {bool danger = false}) =>
      pw.Expanded(
        child: pw.Container(
          padding: const pw.EdgeInsets.all(9),
          decoration: pw.BoxDecoration(
            color: danger ? _dangerSurface : _surface,
            border: pw.Border.all(color: danger ? _danger : _border),
          ),
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Text(
                label.toUpperCase(),
                style: const pw.TextStyle(fontSize: 6.5, color: _muted),
              ),
              pw.SizedBox(height: 2),
              pw.Text(
                '$value',
                style: pw.TextStyle(
                  color: danger ? _danger : _primary,
                  fontSize: 15,
                  fontWeight: pw.FontWeight.bold,
                ),
              ),
            ],
          ),
        ),
      );

  String _statusLabel(GoodsLoanRecapPage page) =>
      page.monitoringStatuses
          .where((item) => item.value == page.filter.monitoringStatus)
          .firstOrNull
          ?.label ??
      page.filter.monitoringStatus;

  String _outstanding(GoodsLoan loan) {
    final items = loan.items
        .where((item) => item.mustReturn && item.remaining > 0)
        .map(
          (item) =>
              '${item.goodsName}: ${_number(item.remaining)} ${item.unit}',
        )
        .toList();
    return items.isEmpty
        ? 'Tidak ada barang yang perlu kembali.'
        : items.join('\n');
  }
}

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');

const _primary = PdfColor(0.082, 0.278, 0.478);
const _accent = PdfColor(0.945, 0.769, 0.059);
const _surface = PdfColor(0.945, 0.965, 0.99);
const _border = PdfColor(0.79, 0.84, 0.9);
const _muted = PdfColor(0.35, 0.42, 0.52);
const _danger = PdfColor(0.7, 0.137, 0.094);
const _dangerSurface = PdfColor(1, 0.945, 0.949);
