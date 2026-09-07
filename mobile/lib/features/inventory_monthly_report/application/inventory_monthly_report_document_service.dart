import 'dart:typed_data';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/inventory_monthly_report/domain/inventory_monthly_report.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

abstract interface class InventoryMonthlyReportDocumentService {
  Future<bool> printReport(InventoryMonthlyReport report);
  Future<bool> shareReport(InventoryMonthlyReport report);
}

final inventoryMonthlyReportDocumentServiceProvider =
    Provider<InventoryMonthlyReportDocumentService>(
      (ref) => PdfInventoryMonthlyReportDocumentService(),
    );

final class PdfInventoryMonthlyReportDocumentService
    implements InventoryMonthlyReportDocumentService {
  PdfInventoryMonthlyReportDocumentService({
    InventoryMonthlyReportPdfBuilder? builder,
  }) : _builder = builder ?? InventoryMonthlyReportPdfBuilder();

  final InventoryMonthlyReportPdfBuilder _builder;

  @override
  Future<bool> printReport(InventoryMonthlyReport report) async {
    final bytes = await _builder.build(report);
    return Printing.layoutPdf(
      name: _fileName(report),
      format: PdfPageFormat.a4.landscape,
      dynamicLayout: false,
      onLayout: (_) async => bytes,
    );
  }

  @override
  Future<bool> shareReport(InventoryMonthlyReport report) async {
    final bytes = await _builder.build(report);
    return Printing.sharePdf(
      bytes: bytes,
      filename: _fileName(report),
      subject: 'Laporan inventaris ${report.periodLabel}',
      body:
          'Laporan inventaris NUSA untuk ${report.periodLabel} · '
          '${report.locationLabel}.',
    );
  }

  String _fileName(InventoryMonthlyReport report) =>
      'laporan-inventaris-${report.period}.pdf';
}

class InventoryMonthlyReportPdfBuilder {
  Future<Uint8List> build(InventoryMonthlyReport report) async {
    final document = pw.Document();
    document.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.all(28),
        header: (context) => _header('LAPORAN INVENTARIS BULANAN', report),
        footer: _footer,
        build: (_) => [
          pw.Row(
            children: [
              _summary('Baris stok', report.summary.stockRows),
              pw.SizedBox(width: 6),
              _summary('Mutasi periode', report.summary.movements),
              pw.SizedBox(width: 6),
              _summary('Stok menipis', report.summary.lowStock),
              pw.SizedBox(width: 6),
              _summary('Stok habis', report.summary.outOfStock, danger: true),
              pw.SizedBox(width: 6),
              _summary('Unit aset', report.summary.assetUnits),
              pw.SizedBox(width: 6),
              _summary(
                'Unit perhatian',
                report.summary.unitsNeedingAttention,
                danger: report.summary.unitsNeedingAttention > 0,
              ),
            ],
          ),
          pw.SizedBox(height: 13),
          _title('REKAP STOK BULANAN'),
          if (report.stockRows.isEmpty)
            _empty('Belum ada saldo stok pada pilihan laporan ini.')
          else
            pw.TableHelper.fromTextArray(
              headers: const [
                'No.',
                'Barang',
                'Lokasi',
                'Saldo awal',
                'Masuk',
                'Keluar',
                'Penyesuaian',
                'Saldo akhir',
                'Status',
              ],
              data: [
                for (final entry in report.stockRows.indexed)
                  [
                    entry.$1 + 1,
                    '${entry.$2.goods.name}\n${entry.$2.goods.code}',
                    entry.$2.location.name,
                    '${_number(entry.$2.opening)} ${entry.$2.goods.unit}',
                    '${_number(entry.$2.incoming)} ${entry.$2.goods.unit}',
                    '${_number(entry.$2.outgoing)} ${entry.$2.goods.unit}',
                    '${entry.$2.adjustment > 0 ? '+' : ''}${_number(entry.$2.adjustment)} ${entry.$2.goods.unit}',
                    '${_number(entry.$2.closing)} ${entry.$2.goods.unit}',
                    entry.$2.statusLabel,
                  ],
              ],
              headerDecoration: const pw.BoxDecoration(color: _primary),
              headerStyle: _tableHeaderStyle,
              cellStyle: _tableCellStyle,
              cellPadding: const pw.EdgeInsets.all(4),
              border: pw.TableBorder.all(color: _border, width: .5),
              oddRowDecoration: const pw.BoxDecoration(color: _surface),
            ),
          if (report.unrecordedStock.isNotEmpty) ...[
            pw.SizedBox(height: 8),
            pw.Text(
              'Perhatian: ${report.unrecordedStock.map((item) => item.name).join(', ')} belum memiliki saldo awal.',
              style: const pw.TextStyle(fontSize: 7.5, color: _warning),
            ),
          ],
          pw.SizedBox(height: 14),
          _title('SNAPSHOT UNIT ASET'),
          pw.Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              for (final item in report.unitDistribution)
                pw.Container(
                  padding: const pw.EdgeInsets.symmetric(
                    horizontal: 7,
                    vertical: 5,
                  ),
                  decoration: pw.BoxDecoration(
                    border: pw.Border.all(color: _border),
                  ),
                  child: pw.Text(
                    '${item.label}: ${item.count}',
                    style: const pw.TextStyle(fontSize: 7.5),
                  ),
                ),
            ],
          ),
          pw.SizedBox(height: 8),
          if (report.unitsNeedingAttention.isEmpty)
            _empty('Tidak ada unit aset yang perlu perhatian.')
          else
            pw.TableHelper.fromTextArray(
              headers: const [
                'No.',
                'Kode inventaris',
                'Barang',
                'Lokasi',
                'Kondisi',
                'Status',
              ],
              data: [
                for (final entry in report.unitsNeedingAttention.indexed)
                  [
                    entry.$1 + 1,
                    entry.$2.inventoryCode,
                    entry.$2.goods,
                    entry.$2.location,
                    entry.$2.conditionLabel,
                    entry.$2.statusLabel,
                  ],
              ],
              headerDecoration: const pw.BoxDecoration(color: _primary),
              headerStyle: _tableHeaderStyle,
              cellStyle: _tableCellStyle,
              cellPadding: const pw.EdgeInsets.all(4),
              border: pw.TableBorder.all(color: _border, width: .5),
              oddRowDecoration: const pw.BoxDecoration(color: _warningSurface),
            ),
        ],
      ),
    );

    document.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.all(28),
        header: (context) =>
            _header('RINCIAN MUTASI STOK & LAYANAN BARANG', report),
        footer: _footer,
        build: (_) => [
          pw.Row(
            children: [
              _summary('Total layanan', report.employeeServiceSummary.services),
              pw.SizedBox(width: 6),
              _summary(
                'Pegawai dilayani',
                report.employeeServiceSummary.employees,
              ),
              pw.SizedBox(width: 6),
              _summary(
                'Peminjaman aset',
                report.employeeServiceSummary.assetLoans,
              ),
              pw.SizedBox(width: 6),
              _summary(
                'Barang habis pakai',
                report.employeeServiceSummary.consumables,
              ),
              pw.SizedBox(width: 6),
              _summary(
                'Masih dipinjam',
                report.employeeServiceSummary.activeLoans,
              ),
            ],
          ),
          pw.SizedBox(height: 13),
          _title('LAYANAN BARANG PEGAWAI'),
          if (report.employeeServices.isEmpty)
            _empty('Belum ada layanan barang kepada pegawai pada periode ini.')
          else
            pw.TableHelper.fromTextArray(
              headers: const [
                'No.',
                'Tanggal',
                'Pegawai',
                'Barang',
                'Rencana kembali',
                'Status',
                'Sumber',
              ],
              data: [
                for (final entry in report.employeeServices.indexed)
                  [
                    entry.$1 + 1,
                    '${entry.$2.dateLabel}\n${entry.$2.number}',
                    '${entry.$2.employee.name}\nNIP ${entry.$2.employee.nip ?? '-'}',
                    entry.$2.items
                        .map(
                          (item) =>
                              '${item.goods}: ${_number(item.quantity)} ${item.unit}${item.inventoryCode == null ? '' : ' (${item.inventoryCode})'}',
                        )
                        .join('\n'),
                    entry.$2.plannedReturnLabel ?? '-',
                    entry.$2.statusLabel,
                    entry.$2.source,
                  ],
              ],
              headerDecoration: const pw.BoxDecoration(color: _primary),
              headerStyle: _tableHeaderStyle,
              cellStyle: _tableCellStyle,
              cellPadding: const pw.EdgeInsets.all(4),
              border: pw.TableBorder.all(color: _border, width: .5),
              oddRowDecoration: const pw.BoxDecoration(color: _surface),
            ),
          pw.SizedBox(height: 14),
          _title('TRANSAKSI PERIODE'),
          if (report.movements.isEmpty)
            _empty('Belum ada mutasi stok pada periode terpilih.')
          else
            pw.TableHelper.fromTextArray(
              headers: const [
                'No.',
                'Tanggal',
                'Barang',
                'Lokasi',
                'Jenis',
                'Kategori',
                'Perubahan',
                'Saldo akhir',
                'Referensi',
              ],
              data: [
                for (final entry in report.movements.indexed)
                  [
                    entry.$1 + 1,
                    entry.$2.dateLabel,
                    entry.$2.goods,
                    entry.$2.location,
                    entry.$2.typeLabel,
                    entry.$2.categoryLabel,
                    '${entry.$2.change > 0 ? '+' : ''}${_number(entry.$2.change)} ${entry.$2.unit}',
                    '${_number(entry.$2.closing)} ${entry.$2.unit}',
                    entry.$2.reference ?? '-',
                  ],
              ],
              headerDecoration: const pw.BoxDecoration(color: _primary),
              headerStyle: _tableHeaderStyle,
              cellStyle: _tableCellStyle,
              cellPadding: const pw.EdgeInsets.all(4),
              border: pw.TableBorder.all(color: _border, width: .5),
              oddRowDecoration: const pw.BoxDecoration(color: _surface),
            ),
          pw.SizedBox(height: 24),
          _signatures(report),
        ],
      ),
    );
    return document.save();
  }

  pw.Widget _header(
    String title,
    InventoryMonthlyReport report,
  ) => pw.Container(
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
              title,
              style: pw.TextStyle(
                color: _primary,
                fontSize: 16,
                fontWeight: pw.FontWeight.bold,
              ),
            ),
            pw.Text(
              'SMP Negeri 2 Padang Panjang',
              style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold),
            ),
            pw.Text(
              'Periode: ${report.periodLabel} | Lokasi: ${report.locationLabel}',
              style: const pw.TextStyle(fontSize: 8, color: _muted),
            ),
          ],
        ),
        pw.Text(
          'Dicetak: ${report.printedAt}',
          style: const pw.TextStyle(fontSize: 8, color: _muted),
        ),
      ],
    ),
  );

  pw.Widget _footer(pw.Context context) => pw.Row(
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
  );

  pw.Widget _summary(String label, int value, {bool danger = false}) =>
      pw.Expanded(
        child: pw.Container(
          padding: const pw.EdgeInsets.all(8),
          decoration: pw.BoxDecoration(
            color: danger ? _dangerSurface : _surface,
            border: pw.Border.all(color: danger ? _danger : _border),
          ),
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Text(
                label.toUpperCase(),
                style: const pw.TextStyle(fontSize: 6.3, color: _muted),
              ),
              pw.SizedBox(height: 2),
              pw.Text(
                '$value',
                style: pw.TextStyle(
                  color: danger ? _danger : _primary,
                  fontSize: 14,
                  fontWeight: pw.FontWeight.bold,
                ),
              ),
            ],
          ),
        ),
      );

  pw.Widget _title(String value) => pw.Padding(
    padding: const pw.EdgeInsets.only(bottom: 6),
    child: pw.Text(
      value,
      style: pw.TextStyle(
        color: _primary,
        fontSize: 10,
        fontWeight: pw.FontWeight.bold,
      ),
    ),
  );

  pw.Widget _empty(String value) => pw.Container(
    width: double.infinity,
    padding: const pw.EdgeInsets.all(12),
    color: _surface,
    child: pw.Text(
      value,
      textAlign: pw.TextAlign.center,
      style: const pw.TextStyle(fontSize: 8),
    ),
  );

  pw.Widget _signatures(InventoryMonthlyReport report) => pw.Row(
    mainAxisAlignment: pw.MainAxisAlignment.spaceAround,
    crossAxisAlignment: pw.CrossAxisAlignment.start,
    children: [
      for (final item in report.signatories)
        pw.SizedBox(
          width: 180,
          child: pw.Column(
            children: [
              pw.Text(
                item.position,
                textAlign: pw.TextAlign.center,
                style: const pw.TextStyle(fontSize: 8),
              ),
              pw.SizedBox(height: 45),
              pw.Text(
                item.name,
                textAlign: pw.TextAlign.center,
                style: pw.TextStyle(
                  fontSize: 8,
                  fontWeight: pw.FontWeight.bold,
                  decoration: pw.TextDecoration.underline,
                ),
              ),
              pw.Text(
                'NIP ${item.nip ?? '-'}',
                style: const pw.TextStyle(fontSize: 7),
              ),
            ],
          ),
        ),
    ],
  );
}

String _number(double value) => inventoryReportNumber(value);

final _tableHeaderStyle = pw.TextStyle(
  color: PdfColors.white,
  fontSize: 6.5,
  fontWeight: pw.FontWeight.bold,
);
const _tableCellStyle = pw.TextStyle(fontSize: 6.5);
const _primary = PdfColor(0.082, 0.278, 0.478);
const _accent = PdfColor(0.945, 0.769, 0.059);
const _surface = PdfColor(0.945, 0.965, 0.99);
const _border = PdfColor(0.79, 0.84, 0.9);
const _muted = PdfColor(0.35, 0.42, 0.52);
const _warning = PdfColor(0.55, 0.34, 0.03);
const _warningSurface = PdfColor(1, 0.969, 0.91);
const _danger = PdfColor(0.7, 0.137, 0.094);
const _dangerSurface = PdfColor(1, 0.945, 0.949);
