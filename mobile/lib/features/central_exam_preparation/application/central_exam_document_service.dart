import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

abstract interface class CentralExamDocumentService {
  Future<bool> printParticipantList(CentralExamDistributionDetail detail);

  Future<bool> shareParticipantList(CentralExamDistributionDetail detail);

  Future<bool> printDeskLabels(
    CentralExamDistributionDetail detail,
    CentralExamDistributionRoom room,
  );

  Future<bool> shareDeskLabels(
    CentralExamDistributionDetail detail,
    CentralExamDistributionRoom room,
  );
}

final centralExamDocumentServiceProvider = Provider<CentralExamDocumentService>(
  (ref) => PdfCentralExamDocumentService(),
);

final class PdfCentralExamDocumentService
    implements CentralExamDocumentService {
  PdfCentralExamDocumentService({CentralExamPdfBuilder? builder})
    : _builder = builder ?? CentralExamPdfBuilder();

  final CentralExamPdfBuilder _builder;

  @override
  Future<bool> printParticipantList(
    CentralExamDistributionDetail detail,
  ) async {
    final bytes = await _builder.buildParticipantList(detail);
    return Printing.layoutPdf(
      name: _participantFileName(detail),
      format: PdfPageFormat.a4,
      dynamicLayout: false,
      onLayout: (_) async => bytes,
    );
  }

  @override
  Future<bool> shareParticipantList(
    CentralExamDistributionDetail detail,
  ) async {
    final bytes = await _builder.buildParticipantList(detail);
    return Printing.sharePdf(
      bytes: bytes,
      filename: _participantFileName(detail),
      subject: 'Daftar peserta ${detail.eventName}',
      body:
          'Daftar peserta ${detail.eventName}, tingkat ${detail.grade}, '
          '${detail.sessionName}.',
    );
  }

  @override
  Future<bool> printDeskLabels(
    CentralExamDistributionDetail detail,
    CentralExamDistributionRoom room,
  ) async {
    final bytes = await _builder.buildDeskLabels(detail, room);
    return Printing.layoutPdf(
      name: _labelFileName(detail, room),
      format: PdfPageFormat.a4,
      dynamicLayout: false,
      onLayout: (_) async => bytes,
    );
  }

  @override
  Future<bool> shareDeskLabels(
    CentralExamDistributionDetail detail,
    CentralExamDistributionRoom room,
  ) async {
    final bytes = await _builder.buildDeskLabels(detail, room);
    return Printing.sharePdf(
      bytes: bytes,
      filename: _labelFileName(detail, room),
      subject: 'Label meja ${detail.eventName} - ${room.name}',
      body:
          'Label meja peserta ${detail.eventName}, ${detail.sessionName}, '
          '${room.name}.',
    );
  }
}

class CentralExamPdfBuilder {
  CentralExamPdfBuilder({Future<Uint8List> Function()? logoLoader})
    : _logoLoader = logoLoader ?? _loadNusaLogo;

  final Future<Uint8List> Function() _logoLoader;

  Future<Uint8List> buildParticipantList(
    CentralExamDistributionDetail detail,
  ) async {
    final document = pw.Document();
    final logo = pw.MemoryImage(await _logoLoader());

    document.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.fromLTRB(28, 24, 28, 24),
        header: (context) => _documentHeader(
          logo: logo,
          title: 'DAFTAR PESERTA UJIAN',
          subtitle: detail.eventName,
        ),
        footer: _documentFooter,
        build: (context) => [
          _examInformation(detail),
          pw.SizedBox(height: 14),
          for (final room in detail.rooms) ...[
            _roomHeading(room),
            pw.SizedBox(height: 6),
            if (room.participants.isEmpty)
              pw.Container(
                width: double.infinity,
                padding: const pw.EdgeInsets.all(10),
                decoration: pw.BoxDecoration(
                  color: _surfaceBlue,
                  borderRadius: pw.BorderRadius.circular(5),
                ),
                child: pw.Text(
                  'Belum ada peserta di ruang ini.',
                  style: const pw.TextStyle(fontSize: 8),
                ),
              )
            else
              pw.TableHelper.fromTextArray(
                headers: const [
                  'No.',
                  'Kode Meja',
                  'Nama Peserta',
                  'Kelas',
                  'NISN',
                  'No. Peserta',
                ],
                data: [
                  for (final participant in room.participants)
                    [
                      participant.seatNumber,
                      participant.seatCode,
                      participant.name,
                      participant.className,
                      _available(participant.nisn),
                      _available(participant.participantNumber),
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
                border: pw.TableBorder.all(color: _border, width: 0.5),
                oddRowDecoration: const pw.BoxDecoration(color: _surfaceBlue),
                columnWidths: const {
                  0: pw.FixedColumnWidth(24),
                  1: pw.FixedColumnWidth(63),
                  2: pw.FlexColumnWidth(2.1),
                  3: pw.FlexColumnWidth(0.8),
                  4: pw.FlexColumnWidth(1.05),
                  5: pw.FlexColumnWidth(1.05),
                },
              ),
            pw.SizedBox(height: 14),
          ],
        ],
      ),
    );

    return document.save();
  }

  Future<Uint8List> buildDeskLabels(
    CentralExamDistributionDetail detail,
    CentralExamDistributionRoom room,
  ) async {
    final document = pw.Document();
    final logo = pw.MemoryImage(await _logoLoader());
    final pages = _chunks(room.participants, 8);

    for (final participants in pages) {
      document.addPage(
        pw.Page(
          pageFormat: PdfPageFormat.a4,
          margin: const pw.EdgeInsets.all(20),
          build: (context) => pw.GridView(
            crossAxisCount: 2,
            crossAxisSpacing: 8,
            mainAxisSpacing: 8,
            childAspectRatio: 0.69,
            children: [
              for (final participant in participants)
                _deskLabel(
                  detail: detail,
                  room: room,
                  participant: participant,
                  logo: logo,
                ),
            ],
          ),
        ),
      );
    }

    return document.save();
  }
}

pw.Widget _documentHeader({
  required pw.MemoryImage logo,
  required String title,
  required String subtitle,
}) => pw.Container(
  margin: const pw.EdgeInsets.only(bottom: 12),
  padding: const pw.EdgeInsets.only(bottom: 9),
  decoration: const pw.BoxDecoration(
    border: pw.Border(bottom: pw.BorderSide(color: _accent, width: 2)),
  ),
  child: pw.Row(
    children: [
      pw.Container(
        width: 37,
        height: 37,
        padding: const pw.EdgeInsets.all(3),
        child: pw.Image(logo, fit: pw.BoxFit.contain),
      ),
      pw.SizedBox(width: 9),
      pw.Expanded(
        child: pw.Column(
          crossAxisAlignment: pw.CrossAxisAlignment.start,
          children: [
            pw.Text(
              title,
              style: pw.TextStyle(
                color: _primary,
                fontSize: 14,
                fontWeight: pw.FontWeight.bold,
              ),
            ),
            pw.SizedBox(height: 2),
            pw.Text(subtitle, style: const pw.TextStyle(fontSize: 8)),
          ],
        ),
      ),
      pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.end,
        children: [
          pw.Text(
            'NUSA',
            style: pw.TextStyle(
              color: _primary,
              fontSize: 12,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
          pw.Text(
            'SMP NEGERI 2 PADANG PANJANG',
            style: const pw.TextStyle(color: _muted, fontSize: 6.5),
          ),
        ],
      ),
    ],
  ),
);

pw.Widget _documentFooter(pw.Context context) => pw.Container(
  padding: const pw.EdgeInsets.only(top: 7),
  decoration: const pw.BoxDecoration(
    border: pw.Border(top: pw.BorderSide(color: _border, width: 0.5)),
  ),
  child: pw.Row(
    mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
    children: [
      pw.Text(
        'Dokumen dibuat melalui NUSA Mobile',
        style: const pw.TextStyle(color: _muted, fontSize: 6.5),
      ),
      pw.Text(
        'Halaman ${context.pageNumber} dari ${context.pagesCount}',
        style: const pw.TextStyle(color: _muted, fontSize: 6.5),
      ),
    ],
  ),
);

pw.Widget _examInformation(CentralExamDistributionDetail detail) =>
    pw.Container(
      width: double.infinity,
      padding: const pw.EdgeInsets.all(10),
      decoration: pw.BoxDecoration(
        color: _surfaceBlue,
        borderRadius: pw.BorderRadius.circular(6),
        border: pw.Border.all(color: _border, width: 0.5),
      ),
      child: pw.Row(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Expanded(
            child: _informationColumn([
              ('Kode kegiatan', detail.eventCode),
              ('Tingkat', '${detail.grade}'),
              ('Kelas', detail.classNames.join(', ')),
            ]),
          ),
          pw.SizedBox(width: 16),
          pw.Expanded(
            child: _informationColumn([
              ('Sesi', detail.sessionName),
              ('Waktu', detail.timeLabel),
              (
                'Peserta',
                '${detail.participantCount}/${detail.totalCapacity} kursi',
              ),
            ]),
          ),
        ],
      ),
    );

pw.Widget _informationColumn(List<(String, String)> items) => pw.Column(
  crossAxisAlignment: pw.CrossAxisAlignment.start,
  children: [
    for (final item in items)
      pw.Padding(
        padding: const pw.EdgeInsets.only(bottom: 3),
        child: pw.RichText(
          text: pw.TextSpan(
            style: const pw.TextStyle(fontSize: 7.5),
            children: [
              pw.TextSpan(
                text: '${item.$1}: ',
                style: pw.TextStyle(fontWeight: pw.FontWeight.bold),
              ),
              pw.TextSpan(text: item.$2),
            ],
          ),
        ),
      ),
  ],
);

pw.Widget _roomHeading(CentralExamDistributionRoom room) => pw.Row(
  children: [
    pw.Container(width: 4, height: 22, color: _accent),
    pw.SizedBox(width: 7),
    pw.Expanded(
      child: pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Text(
            '${room.code} - ${room.name}',
            style: pw.TextStyle(
              color: _primary,
              fontSize: 10,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
          pw.Text(
            '${room.occupiedCount}/${room.capacity} kursi'
            '${room.location?.trim().isNotEmpty == true ? ' - ${room.location}' : ''}',
            style: const pw.TextStyle(color: _muted, fontSize: 7),
          ),
        ],
      ),
    ),
  ],
);

pw.Widget _deskLabel({
  required CentralExamDistributionDetail detail,
  required CentralExamDistributionRoom room,
  required CentralExamDistributedParticipant participant,
  required pw.MemoryImage logo,
}) => pw.Container(
  decoration: pw.BoxDecoration(
    color: PdfColors.white,
    borderRadius: pw.BorderRadius.circular(8),
    border: pw.Border.all(color: _primary, width: 1.2),
  ),
  child: pw.Column(
    crossAxisAlignment: pw.CrossAxisAlignment.stretch,
    children: [
      pw.Container(
        padding: const pw.EdgeInsets.symmetric(horizontal: 9, vertical: 6),
        decoration: const pw.BoxDecoration(
          color: _primary,
          borderRadius: pw.BorderRadius.only(
            topLeft: pw.Radius.circular(6.5),
            topRight: pw.Radius.circular(6.5),
          ),
        ),
        child: pw.Row(
          children: [
            pw.Container(
              width: 24,
              height: 24,
              padding: const pw.EdgeInsets.all(2),
              decoration: const pw.BoxDecoration(
                color: PdfColors.white,
                shape: pw.BoxShape.circle,
              ),
              child: pw.Image(logo, fit: pw.BoxFit.contain),
            ),
            pw.SizedBox(width: 6),
            pw.Expanded(
              child: pw.Column(
                crossAxisAlignment: pw.CrossAxisAlignment.start,
                children: [
                  pw.Text(
                    'NUSA',
                    style: pw.TextStyle(
                      color: PdfColors.white,
                      fontSize: 10,
                      fontWeight: pw.FontWeight.bold,
                    ),
                  ),
                  pw.Text(
                    'SMP NEGERI 2 PADANG PANJANG',
                    style: const pw.TextStyle(
                      color: PdfColors.white,
                      fontSize: 5.5,
                    ),
                  ),
                ],
              ),
            ),
            pw.Container(
              padding: const pw.EdgeInsets.symmetric(
                horizontal: 7,
                vertical: 4,
              ),
              decoration: pw.BoxDecoration(
                color: _accent,
                borderRadius: pw.BorderRadius.circular(10),
              ),
              child: pw.Text(
                room.code,
                style: pw.TextStyle(
                  color: _primaryDark,
                  fontSize: 7,
                  fontWeight: pw.FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
      ),
      pw.Expanded(
        child: pw.Padding(
          padding: const pw.EdgeInsets.fromLTRB(10, 7, 10, 6),
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Row(
                crossAxisAlignment: pw.CrossAxisAlignment.start,
                children: [
                  pw.Expanded(
                    child: pw.Column(
                      crossAxisAlignment: pw.CrossAxisAlignment.start,
                      children: [
                        pw.Text(
                          detail.eventName,
                          maxLines: 2,
                          style: pw.TextStyle(
                            color: _primary,
                            fontSize: 7.5,
                            fontWeight: pw.FontWeight.bold,
                          ),
                        ),
                        pw.SizedBox(height: 2),
                        pw.Text(
                          'Tingkat ${detail.grade} - ${detail.sessionName}',
                          style: const pw.TextStyle(
                            color: _muted,
                            fontSize: 6.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  pw.SizedBox(width: 5),
                  pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.end,
                    children: [
                      pw.Text(
                        'MEJA',
                        style: const pw.TextStyle(color: _muted, fontSize: 6),
                      ),
                      pw.Text(
                        participant.seatNumber.toString().padLeft(2, '0'),
                        style: pw.TextStyle(
                          color: _primary,
                          fontSize: 19,
                          fontWeight: pw.FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              pw.SizedBox(height: 6),
              pw.Container(height: 0.5, color: _border),
              pw.SizedBox(height: 6),
              pw.Text(
                participant.name,
                maxLines: 2,
                style: pw.TextStyle(
                  color: _primaryDark,
                  fontSize: 11,
                  fontWeight: pw.FontWeight.bold,
                ),
              ),
              pw.SizedBox(height: 3),
              pw.Text(
                '${participant.className}  |  NISN ${_available(participant.nisn)}',
                style: const pw.TextStyle(fontSize: 6.7),
              ),
              if (participant.participantNumber?.trim().isNotEmpty == true)
                pw.Text(
                  'No. peserta ${participant.participantNumber}',
                  style: const pw.TextStyle(fontSize: 6.7),
                ),
              pw.Spacer(),
              pw.Container(
                width: double.infinity,
                padding: const pw.EdgeInsets.symmetric(vertical: 4),
                decoration: pw.BoxDecoration(
                  color: _surfaceBlue,
                  borderRadius: pw.BorderRadius.circular(4),
                ),
                child: pw.Text(
                  participant.seatCode,
                  textAlign: pw.TextAlign.center,
                  style: pw.TextStyle(
                    color: _primary,
                    fontSize: 9,
                    fontWeight: pw.FontWeight.bold,
                    letterSpacing: 0.8,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    ],
  ),
);

Future<Uint8List> _loadNusaLogo() async {
  final data = await rootBundle.load('assets/images/logo-nusa.png');
  return data.buffer.asUint8List(data.offsetInBytes, data.lengthInBytes);
}

List<List<T>> _chunks<T>(List<T> values, int size) {
  if (values.isEmpty) return const [];
  return [
    for (var index = 0; index < values.length; index += size)
      values.sublist(
        index,
        index + size > values.length ? values.length : index + size,
      ),
  ];
}

String _available(String? value) =>
    value?.trim().isNotEmpty == true ? value!.trim() : '-';

String _participantFileName(CentralExamDistributionDetail detail) =>
    'daftar-peserta-${_filePart(detail.eventCode)}-tingkat-${detail.grade}.pdf';

String _labelFileName(
  CentralExamDistributionDetail detail,
  CentralExamDistributionRoom room,
) => 'label-meja-${_filePart(detail.eventCode)}-${_filePart(room.code)}.pdf';

String _filePart(String value) => value
    .trim()
    .toLowerCase()
    .replaceAll(RegExp(r'[^a-z0-9]+'), '-')
    .replaceAll(RegExp(r'^-+|-+$'), '');

const _primary = PdfColor(0.082, 0.278, 0.478);
const _primaryDark = PdfColor(0.035, 0.165, 0.31);
const _accent = PdfColor(0.945, 0.769, 0.059);
const _surfaceBlue = PdfColor(0.945, 0.965, 0.99);
const _border = PdfColor(0.79, 0.84, 0.9);
const _muted = PdfColor(0.35, 0.42, 0.52);
