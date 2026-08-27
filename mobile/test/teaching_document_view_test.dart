import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document/data/teaching_document_file_picker.dart';
import 'package:nusa/features/teaching_document/data/teaching_document_remote_data_source.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';
import 'package:nusa/features/teaching_document/presentation/teaching_document_detail_view.dart';
import 'package:nusa/features/teaching_document/presentation/teaching_document_view.dart';

void main() {
  testWidgets('daftar perangkat ajar rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pump(
      tester,
      remote: _FakeTeachingDocumentRemoteDataSource(),
      child: const TeachingDocumentView(),
    );

    expect(find.text('Perangkat Ajar Saya'), findsOneWidget);
    expect(find.text('Guru Informatika Mobile'), findsOneWidget);
    expect(
      find.byKey(const Key('teaching-document-year-filter')),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.byKey(const Key('teaching-document-slot-11')),
      250,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Modul Ajar'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('guru memilih PDF dan mengunggah perangkat ajar', (tester) async {
    final remote = _FakeTeachingDocumentRemoteDataSource();
    await _pump(tester, remote: remote, child: const TeachingDocumentView());

    await tester.scrollUntilVisible(
      find.byKey(const Key('teaching-document-slot-11')),
      250,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(find.byKey(const Key('teaching-document-slot-11')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('pick-teaching-document-pdf')));
    await tester.pumpAndSettle();
    expect(find.text('modul-informatika.pdf'), findsOneWidget);
    await tester.drag(
      find.byKey(const Key('teaching-document-form-scroll')),
      const Offset(0, -420),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('save-teaching-document')));
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(remote.lastCreated?.file?.name, 'modul-informatika.pdf');
    expect(find.text('Menunggu pemeriksaan'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('detail menampilkan catatan pemeriksa dan dapat diperbarui', (
    tester,
  ) async {
    final remote = _FakeTeachingDocumentRemoteDataSource(uploaded: true);
    await _pump(
      tester,
      remote: remote,
      child: const TeachingDocumentDetailView(documentId: 81),
    );

    expect(find.text('Modul Informatika Tingkat VII'), findsOneWidget);
    expect(find.text('Lengkapi langkah pembelajaran.'), findsOneWidget);
    expect(find.text('modul-informatika.pdf'), findsWidgets);

    await tester.tap(find.byKey(const Key('revise-teaching-document')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('teaching-document-title')),
      'Modul Informatika Revisi',
    );
    await tester.scrollUntilVisible(
      find.byKey(const Key('save-teaching-document')),
      220,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(find.byKey(const Key('save-teaching-document')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(remote.lastUpdated?.title, 'Modul Informatika Revisi');
    expect(find.text('Modul Informatika Revisi'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pump(
  WidgetTester tester, {
  required TeachingDocumentRemoteDataSource remote,
  required Widget child,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        teachingDocumentRemoteDataSourceProvider.overrideWithValue(remote),
        teachingDocumentFilePickerProvider.overrideWithValue(
          const _FakeTeachingDocumentFilePicker(),
        ),
      ],
      child: MaterialApp(theme: AppTheme.light, home: child),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeTeachingDocumentFilePicker
    implements TeachingDocumentFilePicker {
  const _FakeTeachingDocumentFilePicker();

  @override
  Future<TeachingDocumentPickedFile?> pickPdf() async =>
      TeachingDocumentPickedFile(
        name: 'modul-informatika.pdf',
        bytes: Uint8List.fromList(List<int>.filled(1024, 1)),
      );
}

final class _FakeTeachingDocumentRemoteDataSource
    implements TeachingDocumentRemoteDataSource {
  _FakeTeachingDocumentRemoteDataSource({this.uploaded = false});

  bool uploaded;
  int createCalls = 0;
  int updateCalls = 0;
  TeachingDocumentFormValue? lastCreated;
  TeachingDocumentFormValue? lastUpdated;

  static const _year = TeachingDocumentAcademicYear(
    id: 7,
    name: '2026/2027',
    active: true,
  );
  static const _employee = TeachingDocumentEmployee(
    id: 4,
    name: 'Guru Informatika Mobile',
    nip: '198001012010011001',
  );
  static const _subject = TeachingDocumentSubject(
    id: 8,
    name: 'Informatika Mobile',
  );
  static const _requiredType = TeachingDocumentType(
    id: 11,
    code: 'MODUL',
    name: 'Modul Ajar',
    required: true,
  );
  static const _optionalType = TeachingDocumentType(
    id: 12,
    code: 'MEDIA',
    name: 'Media Pembelajaran',
    required: false,
  );
  static const _limit = TeachingDocumentUploadLimit(
    bytes: 10 * 1024 * 1024,
    label: '10 MB',
    serverLimited: false,
  );

  TeachingDocument get _document => TeachingDocument(
    id: 81,
    title: lastUpdated?.title ?? 'Modul Informatika Tingkat VII',
    grade: 7,
    gradeLabel: 'VII',
    fileName: 'modul-informatika.pdf',
    fileSize: 1024,
    status: 'perlu_perbaikan',
    statusLabel: 'Perlu perbaikan',
    uploadedAt: DateTime.utc(2026, 8, 27, 3, 30),
    teacherNote: lastUpdated?.teacherNote ?? 'Unggahan dari Android.',
    reviewerNote: 'Lengkapi langkah pembelajaran.',
    reviewer: 'Wakil Kurikulum',
    reviewedAt: DateTime.utc(2026, 8, 27, 4),
    academicYear: _year,
    subject: _subject,
    type: _requiredType,
  );

  @override
  Future<TeachingDocumentPage> fetch({
    int? academicYearId,
    required int semester,
  }) async => TeachingDocumentPage(
    employee: _employee,
    academicYears: const [_year],
    filter: TeachingDocumentFilter(
      academicYearId: academicYearId ?? 7,
      semester: semester,
    ),
    summary: TeachingDocumentSummary(
      requiredCount: 1,
      uploadedCount: uploaded ? 1 : 0,
      completeness: uploaded ? 100 : 0,
      waitingCount: uploaded ? 1 : 0,
      revisionCount: 0,
    ),
    assignments: [
      TeachingDocumentAssignment(
        subject: _subject,
        grade: 7,
        gradeLabel: 'VII',
        slots: [
          TeachingDocumentSlot(
            type: _requiredType,
            document: uploaded
                ? TeachingDocument(
                    id: 81,
                    title: 'Modul Informatika Tingkat VII',
                    grade: 7,
                    gradeLabel: 'VII',
                    fileName: 'modul-informatika.pdf',
                    fileSize: 1024,
                    status: 'menunggu_pemeriksaan',
                    statusLabel: 'Menunggu pemeriksaan',
                  )
                : null,
          ),
          const TeachingDocumentSlot(type: _optionalType, document: null),
        ],
      ),
    ],
    legacyDocuments: const [],
    types: const [_requiredType, _optionalType],
    uploadLimit: _limit,
  );

  @override
  Future<TeachingDocumentDetail> fetchDetail(int id) async =>
      TeachingDocumentDetail(
        document: _document,
        availableGrades: const [7, 8],
        histories: [
          TeachingDocumentHistory(
            id: 1,
            fileName: 'modul-informatika.pdf',
            fileSize: 1024,
            note: 'Unggahan dari Android.',
            uploadedAt: DateTime.utc(2026, 8, 27, 3, 30),
            uploader: 'Guru Informatika Mobile',
          ),
        ],
        uploadLimit: _limit,
      );

  @override
  Future<void> create(TeachingDocumentFormValue value) async {
    createCalls++;
    lastCreated = value;
    uploaded = true;
  }

  @override
  Future<void> update({
    required int id,
    required TeachingDocumentFormValue value,
  }) async {
    updateCalls++;
    lastUpdated = value;
  }
}
