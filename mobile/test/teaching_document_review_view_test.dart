import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teaching_document/domain/teaching_document.dart';
import 'package:nusa/features/teaching_document_review/data/teaching_document_download_saver.dart';
import 'package:nusa/features/teaching_document_review/data/teaching_document_review_remote_data_source.dart';
import 'package:nusa/features/teaching_document_review/domain/teaching_document_review.dart';
import 'package:nusa/features/teaching_document_review/presentation/teaching_document_review_detail_view.dart';
import 'package:nusa/features/teaching_document_review/presentation/teaching_document_review_view.dart';
import 'package:nusa/features/teaching_document_review/presentation/teaching_document_teacher_detail_view.dart';

void main() {
  testWidgets('monitoring pemeriksaan rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeTeachingDocumentReviewRemoteDataSource();

    await _pump(
      tester,
      remote: remote,
      child: const TeachingDocumentReviewView(),
    );

    expect(find.text('Pemeriksaan Perangkat Ajar'), findsOneWidget);
    expect(find.text('Guru Pemeriksaan Mobile'), findsOneWidget);
    expect(
      find.byKey(const Key('teaching-document-review-year')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('filter status memuat ulang monitoring guru', (tester) async {
    final remote = _FakeTeachingDocumentReviewRemoteDataSource();
    await _pump(
      tester,
      remote: remote,
      child: const TeachingDocumentReviewView(),
    );

    await tester.tap(find.byKey(const Key('teaching-document-review-status')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Menunggu pemeriksaan').last);
    await tester.pumpAndSettle();

    expect(remote.requestedStatuses.last, 'menunggu_pemeriksaan');
  });

  testWidgets('rincian guru menampilkan dokumen dan kekurangan per tingkat', (
    tester,
  ) async {
    final remote = _FakeTeachingDocumentReviewRemoteDataSource();
    await _pump(
      tester,
      remote: remote,
      child: const TeachingDocumentTeacherDetailView(
        teacherId: 41,
        initialAcademicYearId: 7,
      ),
    );

    expect(find.text('Guru Pemeriksaan Mobile'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Belum diunggah'),
      220,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Modul Ajar'), findsWidgets);
    expect(find.text('Menunggu pemeriksaan'), findsOneWidget);
    expect(find.text('Belum diunggah'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('pemeriksa mengunduh PDF dan meminta perbaikan dengan catatan', (
    tester,
  ) async {
    final remote = _FakeTeachingDocumentReviewRemoteDataSource();
    final saver = _FakeTeachingDocumentDownloadSaver();
    await _pump(
      tester,
      remote: remote,
      saver: saver,
      child: const TeachingDocumentReviewDetailView(documentId: 81),
    );

    await tester.tap(find.byKey(const Key('download-review-document')));
    await tester.pumpAndSettle();
    expect(remote.downloadCalls, 1);
    expect(saver.saveCalls, 1);

    await tester.pump(const Duration(seconds: 5));
    await tester.ensureVisible(
      find.byKey(const Key('open-document-review-form')),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('open-document-review-form')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('save-document-review')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('review-document-error')), findsOneWidget);

    await tester.enterText(
      find.byKey(const Key('review-document-note')),
      'Lengkapi langkah pembelajaran dan asesmen.',
    );
    await tester.tap(find.byKey(const Key('save-document-review')));
    await tester.pumpAndSettle();

    expect(remote.reviewCalls, 1);
    expect(remote.lastReview?.status, 'perlu_perbaikan');
    expect(
      remote.lastReview?.reviewerNote,
      'Lengkapi langkah pembelajaran dan asesmen.',
    );
    await tester.drag(
      find.byKey(
        const PageStorageKey<String>('teaching-document-review-detail'),
      ),
      const Offset(0, 700),
    );
    await tester.pumpAndSettle();
    expect(find.text('Perlu perbaikan'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pump(
  WidgetTester tester, {
  required TeachingDocumentReviewRemoteDataSource remote,
  required Widget child,
  TeachingDocumentDownloadSaver? saver,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        teachingDocumentReviewRemoteDataSourceProvider.overrideWithValue(
          remote,
        ),
        if (saver != null)
          teachingDocumentDownloadSaverProvider.overrideWithValue(saver),
      ],
      child: MaterialApp(theme: AppTheme.light, home: child),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeTeachingDocumentDownloadSaver
    implements TeachingDocumentDownloadSaver {
  int saveCalls = 0;

  @override
  Future<bool> save(TeachingDocumentDownload download) async {
    saveCalls++;
    return true;
  }
}

final class _FakeTeachingDocumentReviewRemoteDataSource
    implements TeachingDocumentReviewRemoteDataSource {
  final requestedStatuses = <String>[];
  int downloadCalls = 0;
  int reviewCalls = 0;
  TeachingDocumentReviewValue? lastReview;

  static const _year = TeachingDocumentAcademicYear(
    id: 7,
    name: '2026/2027',
    active: true,
  );
  static const _employee = TeachingDocumentEmployee(
    id: 41,
    name: 'Guru Pemeriksaan Mobile',
    nip: '198001012010011991',
  );
  static const _subject = TeachingDocumentSubject(
    id: 8,
    name: 'Informatika Review',
  );
  static const _type = TeachingDocumentType(
    id: 11,
    code: 'MODUL',
    name: 'Modul Ajar',
    required: true,
  );

  TeachingDocument get _document => TeachingDocument(
    id: 81,
    title: 'Modul Informatika Review VII',
    grade: 7,
    gradeLabel: 'VII',
    fileName: 'modul-informatika-vii.pdf',
    fileSize: 2048,
    status: lastReview?.status ?? 'menunggu_pemeriksaan',
    statusLabel: switch (lastReview?.status) {
      'perlu_perbaikan' => 'Perlu perbaikan',
      'sudah_diperiksa' => 'Sudah diperiksa',
      _ => 'Menunggu pemeriksaan',
    },
    uploadedAt: DateTime.utc(2026, 8, 28, 1),
    teacherNote: 'Mohon diperiksa.',
    reviewerNote: lastReview?.reviewerNote,
    academicYear: _year,
    subject: _subject,
    type: _type,
  );

  @override
  Future<TeachingDocumentReviewPage> fetch({
    required String query,
    required int? academicYearId,
    required int semester,
    required String completeness,
    required String documentStatus,
    required int page,
    int perPage = 15,
  }) async {
    requestedStatuses.add(documentStatus);
    return TeachingDocumentReviewPage(
      items: const [
        TeachingDocumentTeacherReview(
          employee: _employee,
          subjects: [_subject],
          grades: ['VII', 'VIII'],
          requiredCount: 2,
          uploadedCount: 1,
          percentage: 50,
          complete: false,
          waitingCount: 1,
          revisionCount: 0,
          reviewedCount: 0,
        ),
      ],
      summary: const TeachingDocumentReviewSummary(
        teacherCount: 1,
        completeCount: 0,
        incompleteCount: 1,
        waitingCount: 1,
        revisionCount: 0,
      ),
      academicYears: const [_year],
      filter: TeachingDocumentReviewFilter(
        academicYearId: academicYearId ?? 7,
        semester: semester,
        completeness: completeness,
        documentStatus: documentStatus,
        query: query,
      ),
      pagination: const TeachingDocumentReviewPagination(
        page: 1,
        total: 1,
        hasNextPage: false,
      ),
      canReview: true,
    );
  }

  @override
  Future<TeachingDocumentTeacherDetail> fetchTeacher(
    TeachingDocumentTeacherQuery query,
  ) async => TeachingDocumentTeacherDetail(
    employee: _employee,
    academicYears: const [_year],
    filter: TeachingDocumentFilter(
      academicYearId: query.academicYearId ?? 7,
      semester: query.semester,
    ),
    summary: const TeachingDocumentTeacherSummary(
      requiredCount: 2,
      uploadedCount: 1,
      completeness: 50,
      waitingCount: 1,
      revisionCount: 0,
      reviewedCount: 0,
    ),
    assignments: [
      TeachingDocumentAssignment(
        subject: _subject,
        grade: 7,
        gradeLabel: 'VII',
        slots: [TeachingDocumentSlot(type: _type, document: _document)],
      ),
      const TeachingDocumentAssignment(
        subject: _subject,
        grade: 8,
        gradeLabel: 'VIII',
        slots: [TeachingDocumentSlot(type: _type, document: null)],
      ),
    ],
    legacyDocuments: const [],
    canReview: true,
  );

  @override
  Future<TeachingDocumentReviewDetail> fetchDocument(int id) async =>
      TeachingDocumentReviewDetail(
        document: _document,
        employee: _employee,
        histories: [
          TeachingDocumentHistory(
            id: 1,
            fileName: 'modul-informatika-vii.pdf',
            fileSize: 2048,
            note: 'Mohon diperiksa.',
            uploadedAt: DateTime.utc(2026, 8, 28, 1),
            uploader: _employee.name,
          ),
        ],
        canReview: true,
      );

  @override
  Future<TeachingDocumentDownload> download({
    required int id,
    required String fileName,
  }) async {
    downloadCalls++;
    return TeachingDocumentDownload(
      fileName: fileName,
      bytes: Uint8List.fromList([37, 80, 68, 70]),
    );
  }

  @override
  Future<void> review({
    required int id,
    required TeachingDocumentReviewValue value,
  }) async {
    reviewCalls++;
    lastReview = value;
  }
}
