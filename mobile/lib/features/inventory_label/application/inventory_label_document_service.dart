import 'dart:typed_data';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/inventory_label/domain/inventory_label.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

abstract interface class InventoryLabelDocumentService {
  Future<bool> printLabels({
    required List<InventoryLabelItem> items,
    required InventoryLabelSize size,
    required InventoryLabelPrintRules rules,
    required int copies,
  });

  Future<bool> shareLabels({
    required List<InventoryLabelItem> items,
    required InventoryLabelSize size,
    required InventoryLabelPrintRules rules,
    required int copies,
  });
}

final inventoryLabelDocumentServiceProvider =
    Provider<InventoryLabelDocumentService>(
      (ref) => PdfInventoryLabelDocumentService(),
    );

final class PdfInventoryLabelDocumentService
    implements InventoryLabelDocumentService {
  PdfInventoryLabelDocumentService({InventoryLabelPdfBuilder? builder})
    : _builder = builder ?? InventoryLabelPdfBuilder();

  final InventoryLabelPdfBuilder _builder;

  @override
  Future<bool> printLabels({
    required List<InventoryLabelItem> items,
    required InventoryLabelSize size,
    required InventoryLabelPrintRules rules,
    required int copies,
  }) async {
    final bytes = await _builder.build(
      items: items,
      size: size,
      rules: rules,
      copies: copies,
    );
    return Printing.layoutPdf(
      name: _fileName(items, size),
      format: PdfPageFormat.a4,
      dynamicLayout: false,
      onLayout: (_) async => bytes,
    );
  }

  @override
  Future<bool> shareLabels({
    required List<InventoryLabelItem> items,
    required InventoryLabelSize size,
    required InventoryLabelPrintRules rules,
    required int copies,
  }) async {
    final bytes = await _builder.build(
      items: items,
      size: size,
      rules: rules,
      copies: copies,
    );
    return Printing.sharePdf(
      bytes: bytes,
      filename: _fileName(items, size),
      subject: 'Label inventaris NUSA',
      body: '${items.length * copies} label ukuran ${size.label}.',
    );
  }
}

class InventoryLabelPdfBuilder {
  Future<Uint8List> build({
    required List<InventoryLabelItem> items,
    required InventoryLabelSize size,
    required InventoryLabelPrintRules rules,
    required int copies,
  }) async {
    if (items.isEmpty) {
      throw ArgumentError.value(items, 'items', 'Minimal satu label dipilih.');
    }
    if (items.length > rules.maximumSelection) {
      throw ArgumentError.value(
        items.length,
        'items',
        'Maksimal ${rules.maximumSelection} pilihan.',
      );
    }
    if (copies < 1 || copies > rules.maximumCopies) {
      throw ArgumentError.value(
        copies,
        'copies',
        'Salinan harus 1 sampai ${rules.maximumCopies}.',
      );
    }

    final document = pw.Document();
    final labels = <InventoryLabelItem>[
      for (final item in items)
        for (var copy = 0; copy < copies; copy++) item,
    ];
    final availableWidth = 210 - (rules.marginMm * 2);
    final availableHeight = 297 - (rules.marginMm * 2);
    final columns =
        ((availableWidth + rules.gapMm) / (size.widthMm + rules.gapMm))
            .floor()
            .clamp(1, 20);
    final rows =
        ((availableHeight + rules.gapMm) / (size.heightMm + rules.gapMm))
            .floor()
            .clamp(1, 30);
    final labelsPerPage = columns * rows;

    for (var offset = 0; offset < labels.length; offset += labelsPerPage) {
      final end = (offset + labelsPerPage).clamp(0, labels.length);
      final pageItems = labels.sublist(offset, end);
      document.addPage(
        pw.Page(
          pageFormat: PdfPageFormat.a4,
          margin: pw.EdgeInsets.all(rules.marginMm * PdfPageFormat.mm),
          build: (context) => pw.Wrap(
            spacing: rules.gapMm * PdfPageFormat.mm,
            runSpacing: rules.gapMm * PdfPageFormat.mm,
            children: [for (final item in pageItems) _label(item, size)],
          ),
        ),
      );
    }

    return document.save();
  }

  pw.Widget _label(InventoryLabelItem item, InventoryLabelSize size) {
    final horizontalPadding = _value(size, small: 1.6, medium: 2, large: 2.6);
    final verticalPadding = _value(size, small: 1.2, medium: 1.5, large: 2);
    return pw.Container(
      width: size.widthMm * PdfPageFormat.mm,
      height: size.heightMm * PdfPageFormat.mm,
      padding: pw.EdgeInsets.symmetric(
        horizontal: horizontalPadding * PdfPageFormat.mm,
        vertical: verticalPadding * PdfPageFormat.mm,
      ),
      decoration: pw.BoxDecoration(
        color: PdfColors.white,
        border: pw.Border.all(color: _primary, width: 0.35 * PdfPageFormat.mm),
        borderRadius: pw.BorderRadius.circular(1.5 * PdfPageFormat.mm),
      ),
      child: item.isAsset
          ? _assetContent(item, size)
          : _stockContent(item, size),
    );
  }

  pw.Widget _assetContent(InventoryLabelItem item, InventoryLabelSize size) {
    final heading = _value(size, small: 5.2, medium: 6.2, large: 7.4);
    final identity = _value(size, small: 4.2, medium: 5.2, large: 6.3);
    final barcodeHeight = _value(size, small: 7, medium: 9, large: 13);
    final code = _value(size, small: 4.5, medium: 5.6, large: 6.8);
    final identityGap = _value(size, small: 0.25, medium: 0.35, large: 0.45);
    return pw.Column(
      crossAxisAlignment: pw.CrossAxisAlignment.stretch,
      children: [
        _heading(item.name, 'ASET NUSA', heading),
        pw.SizedBox(height: 0.7 * PdfPageFormat.mm),
        _centerLine(item.officialAssetNumber ?? '-', identity, heavy: true),
        pw.SizedBox(height: identityGap * PdfPageFormat.mm),
        _centerLine(item.goodsCode ?? '-', identity, heavy: true),
        pw.SizedBox(height: identityGap * PdfPageFormat.mm),
        _centerLine(item.sourceYear ?? '-', identity, heavy: true),
        pw.SizedBox(height: identityGap * PdfPageFormat.mm),
        _centerLine(item.owner ?? '-', identity, heavy: true),
        pw.Spacer(),
        _barcode(item.code, barcodeHeight),
        pw.SizedBox(height: 0.45 * PdfPageFormat.mm),
        _centerLine(item.code, code, heavy: true),
      ],
    );
  }

  pw.Widget _stockContent(InventoryLabelItem item, InventoryLabelSize size) {
    final heading = _value(size, small: 5.2, medium: 6.2, large: 7.4);
    final name = _value(size, small: 6, medium: 7.4, large: 9);
    final meta = _value(size, small: 4.2, medium: 5.2, large: 6.3);
    final barcodeHeight = _value(size, small: 7, medium: 9, large: 13);
    final code = _value(size, small: 4.5, medium: 5.6, large: 6.8);
    return pw.Column(
      crossAxisAlignment: pw.CrossAxisAlignment.stretch,
      children: [
        _heading(item.title ?? 'BARANG BERBASIS STOK', 'NUSA', heading),
        pw.SizedBox(height: 1.3 * PdfPageFormat.mm),
        _centerLine(item.name, name, heavy: true),
        pw.SizedBox(height: 0.5 * PdfPageFormat.mm),
        pw.Row(
          children: [
            pw.Expanded(child: _meta(item.location, meta, pw.TextAlign.left)),
            pw.Expanded(
              child: _meta(item.unit ?? '-', meta, pw.TextAlign.right),
            ),
          ],
        ),
        pw.Spacer(),
        _barcode(item.code, barcodeHeight),
        pw.SizedBox(height: 0.45 * PdfPageFormat.mm),
        _centerLine(item.code, code, heavy: true),
      ],
    );
  }

  pw.Widget _heading(String left, String right, double size) => pw.Column(
    children: [
      pw.Row(
        children: [
          pw.Expanded(
            child: pw.Text(
              left.toUpperCase(),
              maxLines: 1,
              style: pw.TextStyle(
                color: _primary,
                fontSize: size,
                fontWeight: pw.FontWeight.bold,
              ),
            ),
          ),
          pw.SizedBox(width: 2 * PdfPageFormat.mm),
          pw.Text(
            right,
            style: pw.TextStyle(
              color: _primary,
              fontSize: size,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
        ],
      ),
      pw.SizedBox(height: 0.7 * PdfPageFormat.mm),
      pw.Container(height: 0.25 * PdfPageFormat.mm, color: _accent),
    ],
  );

  pw.Widget _barcode(String data, double heightMm) => pw.SizedBox(
    height: heightMm * PdfPageFormat.mm,
    child: pw.BarcodeWidget(
      barcode: pw.Barcode.code128(),
      data: data,
      drawText: false,
      color: _barcodeColor,
    ),
  );

  pw.Widget _centerLine(String value, double size, {bool heavy = false}) =>
      pw.Text(
        value,
        maxLines: 1,
        textAlign: pw.TextAlign.center,
        style: pw.TextStyle(
          color: _text,
          fontSize: size,
          fontWeight: heavy ? pw.FontWeight.bold : pw.FontWeight.normal,
        ),
      );

  pw.Widget _meta(String value, double size, pw.TextAlign align) => pw.Text(
    value,
    maxLines: 1,
    textAlign: align,
    style: pw.TextStyle(
      color: _muted,
      fontSize: size,
      fontWeight: pw.FontWeight.bold,
    ),
  );
}

double _value(
  InventoryLabelSize size, {
  required double small,
  required double medium,
  required double large,
}) => switch (size.value) {
  'kecil' => small,
  'besar' => large,
  _ => medium,
};

String _fileName(List<InventoryLabelItem> items, InventoryLabelSize size) =>
    'label-inventaris-${items.first.type}-${size.value}.pdf';

const _primary = PdfColor(0.082, 0.278, 0.478);
const _accent = PdfColor(0.945, 0.769, 0.059);
const _text = PdfColor(0.051, 0.176, 0.294);
const _muted = PdfColor(0.322, 0.42, 0.514);
const _barcodeColor = PdfColor(0.067, 0.094, 0.153);
