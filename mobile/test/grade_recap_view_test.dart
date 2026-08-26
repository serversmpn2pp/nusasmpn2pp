import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/grade_recap/data/grade_recap_remote_data_source.dart';
import 'package:nusa/features/grade_recap/domain/grade_recap.dart';
import 'package:nusa/features/grade_recap/presentation/grade_recap_view.dart';

void main() {
  testWidgets('rekap rapor menampilkan ringkasan, bobot, dan nilai siswa', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 700));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final remote = _FakeGradeRecapRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          gradeRecapRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(theme: AppTheme.light, home: const GradeRecapView()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Rekap Nilai Rapor'), findsOneWidget);
    expect(find.byKey(const Key('grade-recap-summary')), findsOneWidget);
    expect(find.text('Komponen & Bobot'), findsOneWidget);
    expect(find.textContaining('30%'), findsWidgets);

    await tester.scrollUntilVisible(
      find.byKey(const Key('grade-recap-student-101')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Alya Rekap Mobile'), findsOneWidget);
    expect(find.text('85'), findsWidgets);
    expect(find.text('Lengkap'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('mengganti semester memuat rekap semester yang dipilih', (
    tester,
  ) async {
    final remote = _FakeGradeRecapRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          gradeRecapRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(theme: AppTheme.light, home: const GradeRecapView()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('grade-recap-semester-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Genap').last);
    await tester.pumpAndSettle();

    expect(remote.requestedSemesters.last, 'genap');
    expect(find.text('Genap'), findsOneWidget);
  });
}

final class _FakeGradeRecapRemoteDataSource
    implements GradeRecapRemoteDataSource {
  final List<String> requestedSemesters = [];

  @override
  Future<GradeRecapPage> fetch({
    required int? assignmentId,
    required String semester,
  }) async {
    requestedSemesters.add(semester);
    const assignment = GradeRecapAssignment(
      id: 91,
      academicYearName: '2026/2027',
      activeAcademicYear: true,
      className: 'VIII.A',
      grade: 8,
      subjectCode: 'MTK',
      subjectName: 'Matematika Mobile',
      employeeName: 'Guru Mobile Uji',
      employeeNip: '198808242026081001',
    );
    const categories = [
      GradeRecapCategory(
        code: 'formatif',
        label: 'Formatif',
        componentCount: 1,
        weight: 30,
      ),
      GradeRecapCategory(
        code: 'sumatif',
        label: 'Sumatif',
        componentCount: 1,
        weight: 30,
      ),
      GradeRecapCategory(
        code: 'sts',
        label: 'STS',
        componentCount: 1,
        weight: 20,
      ),
      GradeRecapCategory(
        code: 'sas_saj',
        label: 'SAS',
        componentCount: 1,
        weight: 20,
      ),
    ];
    const results = {
      'formatif': GradeRecapCategoryResult(
        average: 80,
        filled: 1,
        target: 1,
        weight: 30,
      ),
      'sumatif': GradeRecapCategoryResult(
        average: 90,
        filled: 1,
        target: 1,
        weight: 30,
      ),
      'sts': GradeRecapCategoryResult(
        average: 70,
        filled: 1,
        target: 1,
        weight: 20,
      ),
      'sas_saj': GradeRecapCategoryResult(
        average: 100,
        filled: 1,
        target: 1,
        weight: 20,
      ),
    };

    return GradeRecapPage(
      assignments: const [assignment],
      selectedAssignment: assignment,
      scheme: const GradeRecapScheme(
        id: 3,
        gradeLabel: 'Kelas 8',
        finalGradeLabel: 'SAS',
        weights: {'formatif': 30, 'sumatif': 30, 'sts': 20, 'sas_saj': 20},
      ),
      categories: categories,
      students: const [
        GradeRecapStudent(
          membershipId: 301,
          attendanceNumber: 1,
          studentId: 101,
          name: 'Alya Rekap Mobile',
          nis: '20260081',
          nisn: '0011223381',
          categories: results,
          finalGrade: 85,
          complete: true,
          status: 'Lengkap',
        ),
      ],
      summary: const GradeRecapSummary(
        studentCount: 1,
        completeCount: 1,
        incompleteCount: 0,
        finalAverage: 85,
      ),
      filter: GradeRecapFilter(
        assignmentId: assignmentId ?? 91,
        semester: semester,
      ),
      finalGradeLabel: 'SAS',
      warnings: const [],
      canView: true,
    );
  }
}
