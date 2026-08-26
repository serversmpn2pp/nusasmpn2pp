import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_entry/data/grade_entry_remote_data_source.dart';
import 'package:nusa/features/grade_entry/domain/grade_entry.dart';
import 'package:nusa/features/grade_entry/presentation/grade_entry_view.dart';

void main() {
  testWidgets('input nilai menyimpan perubahan dan mempublikasikan nilai', (
    tester,
  ) async {
    final remote = _FakeGradeEntryRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          gradeEntryRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(theme: AppTheme.light, home: const GradeEntryView()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Input Nilai'), findsOneWidget);
    expect(find.byKey(const Key('grade-publication-card')), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('grade-score-101')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Alya Nilai Mobile'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('grade-score-101')), '92.5');
    await tester.tap(find.byKey(const Key('save-grades')));
    await tester.pumpAndSettle();

    expect(remote.savedScore, 92.5);
    expect(find.textContaining('Nilai berhasil disimpan'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('publish-grades')),
      -300,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(find.byKey(const Key('publish-grades')));
    await tester.pumpAndSettle();
    expect(find.text('Publikasikan nilai?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-publish-grades')));
    await tester.pumpAndSettle();

    expect(remote.published, isTrue);
    expect(find.text('Sudah dipublikasikan'), findsOneWidget);
  });

  testWidgets('input nilai predikat menampilkan pilihan yang sesuai', (
    tester,
  ) async {
    final remote = _FakeGradeEntryRemoteDataSource(predicateMode: true);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          gradeEntryRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(theme: AppTheme.light, home: const GradeEntryView()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('grade-predicate-101')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.byKey(const Key('grade-predicate-101')), findsOneWidget);
    expect(find.byKey(const Key('grade-score-101')), findsNothing);
    await tester.tap(find.byKey(const Key('grade-predicate-101')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('SB').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('save-grades')));
    await tester.pumpAndSettle();

    expect(remote.savedPredicate, 'SB');
  });

  testWidgets('mengganti semester mereset komponen sebelum memuat ulang', (
    tester,
  ) async {
    final remote = _FakeGradeEntryRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          gradeEntryRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(theme: AppTheme.light, home: const GradeEntryView()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('grade-entry-semester-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Genap').last);
    await tester.pumpAndSettle();

    expect(remote.requestedSemesters.last, 'genap');
    expect(remote.requestedComponentIds.last, isNull);
  });
}

final class _FakeGradeEntryRemoteDataSource
    implements GradeEntryRemoteDataSource {
  _FakeGradeEntryRemoteDataSource({this.predicateMode = false});

  final bool predicateMode;
  double? savedScore = 80;
  String? savedPredicate;
  String? savedNotes;
  bool published = false;
  final List<String> requestedSemesters = [];
  final List<int?> requestedComponentIds = [];

  @override
  Future<GradeEntryPage> fetch({
    required int? assignmentId,
    required String semester,
    required int? componentId,
  }) async {
    requestedSemesters.add(semester);
    requestedComponentIds.add(componentId);
    final hasValue = predicateMode
        ? savedPredicate != null
        : savedScore != null;
    return GradeEntryPage(
      assignments: [
        GradeEntryAssignment(
          id: 91,
          academicYearId: 5,
          academicYearName: '2026/2027',
          activeAcademicYear: true,
          classId: 8,
          className: 'VIII.A',
          grade: 8,
          subjectId: 11,
          subjectCode: predicateMode ? 'PD' : 'MTK',
          subjectName: predicateMode
              ? 'Pengembangan Diri'
              : 'Matematika Mobile',
          assessmentMode: predicateMode ? 'predikat' : 'angka',
          employeeId: 31,
          employeeName: 'Guru Mobile Uji',
          employeeNip: '198808242026081001',
        ),
      ],
      components: const [
        GradeEntryComponent(
          id: 601,
          assignmentId: 91,
          semester: 'ganjil',
          type: 'formatif',
          typeLabel: 'Formatif',
          name: 'Kuis Aljabar Mobile',
          order: 1,
        ),
      ],
      selectedComponent: const GradeEntryComponent(
        id: 601,
        assignmentId: 91,
        semester: 'ganjil',
        type: 'formatif',
        typeLabel: 'Formatif',
        name: 'Kuis Aljabar Mobile',
        order: 1,
      ),
      students: [
        GradeEntryStudent(
          membershipId: 301,
          attendanceNumber: 1,
          studentId: 101,
          name: 'Alya Nilai Mobile',
          nis: '20260071',
          nisn: '0011223371',
          score: predicateMode ? null : savedScore,
          predicate: predicateMode ? savedPredicate : null,
          notes: savedNotes,
        ),
      ],
      summary: GradeEntrySummary(
        studentCount: 1,
        filledCount: hasValue ? 1 : 0,
        emptyCount: hasValue ? 0 : 1,
        average: predicateMode ? null : savedScore,
      ),
      publication: GradePublication(
        status: published ? 'dipublikasikan' : 'draf',
        published: published,
        publishedAt: published ? '2026-08-27T08:00:00+07:00' : null,
        publishedAtLabel: published ? '27-08-2026 08:00 WIB' : null,
        componentCount: 1,
        valueCount: hasValue ? 1 : 0,
        targetCount: 1,
        canPublish: hasValue,
        canUnpublish: published,
      ),
      filter: GradeEntryFilter(
        assignmentId: assignmentId ?? 91,
        semester: semester,
        componentId: componentId ?? 601,
      ),
      assessmentMode: predicateMode ? 'predikat' : 'angka',
      predicateOptions: const ['SB', 'B', 'C', 'K'],
      canInput: true,
    );
  }

  @override
  Future<String> save(GradeEntryFormValue value) async {
    savedScore = value.scores[101];
    savedPredicate = value.predicates[101];
    savedNotes = value.notes[101];
    published = false;
    return 'Nilai berhasil disimpan sebagai draf.';
  }

  @override
  Future<String> publish({
    required int assignmentId,
    required String semester,
  }) async {
    published = true;
    return 'Nilai berhasil dipublikasikan.';
  }

  @override
  Future<String> unpublish({
    required int assignmentId,
    required String semester,
  }) async {
    published = false;
    return 'Nilai berhasil dikembalikan menjadi draf.';
  }
}
