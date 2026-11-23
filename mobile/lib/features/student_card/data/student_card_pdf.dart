import 'dart:typed_data';

import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

import '../domain/student_card.dart';

Future<Uint8List> buildStudentCardPdf({
  required StudentCardSize size,
  required Uint8List front,
  required Uint8List back,
}) {
  final format = PdfPageFormat(
    size.widthMillimeter * PdfPageFormat.mm,
    size.heightMillimeter * PdfPageFormat.mm,
  );
  final document = pw.Document();
  for (final side in [front, back]) {
    final image = pw.MemoryImage(side);
    document.addPage(
      pw.Page(
        pageFormat: format,
        margin: pw.EdgeInsets.zero,
        build: (_) => pw.Image(
          image,
          width: format.width,
          height: format.height,
          fit: pw.BoxFit.fill,
        ),
      ),
    );
  }
  return document.save();
}
