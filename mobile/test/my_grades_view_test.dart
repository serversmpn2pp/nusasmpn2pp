import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_grades/data/my_grades_remote_data_source.dart';
import 'package:nusa/features/my_grades/domain/my_grades.dart';
import 'package:nusa/features/my_grades/presentation/my_grades_view.dart';

void main() {
  testWidgets('Nilai Saya menampilkan nilai terbuka dan status survei', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 700));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final remote = _FakeMyGradesRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [myGradesRemoteDataSourceProvider.overrideWithValue(remote)],
        child: MaterialApp(theme: AppTheme.light, home: const MyGradesView()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Nilai Saya'), findsOneWidget);
    expect(find.text('Alya Nilai Saya'), findsOneWidget);
    expect(find.text('VIII.A'), findsOneWidget);
    expect(find.byKey(const Key('my-grades-summary')), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('my-grade-subject-91')),
      280,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Matematika Mobile'), findsOneWidget);
    expect(find.text('85'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.text('Kuis Aljabar'),
      250,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('80'), findsWidgets);

    await tester.scrollUntilVisible(
      find.byKey(const Key('my-grade-subject-92')),
      300,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('Ilmu Pengetahuan Alam'), findsOneWidget);
    expect(find.text('Isi Survei dan Buka Nilai'), findsOneWidget);
    expect(find.text('99'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('filter Nilai Saya memuat ulang tahun dan semester pilihan', (
    tester,
  ) async {
    final remote = _FakeMyGradesRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [myGradesRemoteDataSourceProvider.overrideWithValue(remote)],
        child: MaterialApp(theme: AppTheme.light, home: const MyGradesView()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('my-grades-academic-year-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('2025/2026').last);
    await tester.pumpAndSettle();
    expect(remote.requestedAcademicYearIds.last, 4);

    await tester.tap(find.byKey(const Key('my-grades-semester-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Genap').last);
    await tester.pumpAndSettle();
    expect(remote.requestedSemesters.last, 'genap');
  });
}

final class _FakeMyGradesRemoteDataSource implements MyGradesRemoteDataSource {
  final List<int?> requestedAcademicYearIds = [];
  final List<String> requestedSemesters = [];

  @override
  Future<MyGradesPage> fetch({
    required int? academicYearId,
    required String semester,
  }) async {
    requestedAcademicYearIds.add(academicYearId);
    requestedSemesters.add(semester);
    const years = [
      MyGradesAcademicYear(id: 5, name: '2026/2027', active: true),
      MyGradesAcademicYear(id: 4, name: '2025/2026', active: false),
    ];

    return MyGradesPage(
      student: const MyGradesStudent(
        id: 101,
        name: 'Alya Nilai Saya',
        nis: '20260091',
        nisn: '0011223391',
      ),
      academicYears: years,
      selectedAcademicYear: academicYearId == 4 ? years[1] : years[0],
      schoolClass: const MyGradesClass(
        id: 8,
        name: 'VIII.A',
        grade: 8,
        attendanceNumber: 1,
        membershipStatus: 'aktif',
      ),
      filter: MyGradesFilter(
        academicYearId: academicYearId ?? 5,
        semester: semester,
      ),
      summary: const MyGradesSummary(
        subjectCount: 2,
        openCount: 1,
        surveyRequiredCount: 1,
      ),
      subjects: const [
        MyGradesSubject(
          assignmentId: 91,
          subjectId: 11,
          subjectCode: 'MTK',
          subjectName: 'Matematika Mobile',
          teacherName: 'Guru Mobile Uji',
          publishedAtLabel: '27 Agustus 2026',
          open: true,
          surveyRequired: false,
          surveySemester: 'ganjil',
          usesPredicate: false,
          finalGradeLabel: 'SAS',
          finalGrade: 85,
          complete: true,
          minimumGrade: 75,
          passed: true,
          status: 'tuntas',
          categories: [
            MyGradeCategory(
              code: 'formatif',
              label: 'Formatif',
              average: 80,
              filledCount: 1,
              targetCount: 1,
              weight: 30,
            ),
            MyGradeCategory(
              code: 'sumatif',
              label: 'Sumatif',
              average: 90,
              filledCount: 1,
              targetCount: 1,
              weight: 30,
            ),
            MyGradeCategory(
              code: 'sts',
              label: 'STS',
              average: 70,
              filledCount: 1,
              targetCount: 1,
              weight: 20,
            ),
            MyGradeCategory(
              code: 'sas_saj',
              label: 'SAS',
              average: 100,
              filledCount: 1,
              targetCount: 1,
              weight: 20,
            ),
          ],
          components: [
            MyGradeComponent(
              id: 601,
              name: 'Kuis Aljabar',
              type: 'formatif',
              typeLabel: 'Formatif',
              dateLabel: '20 Agustus 2026',
              score: 80,
              notes: 'Pertahankan hasil belajar.',
            ),
          ],
        ),
        MyGradesSubject(
          assignmentId: 92,
          subjectId: 12,
          subjectCode: 'IPA',
          subjectName: 'Ilmu Pengetahuan Alam',
          teacherName: 'Guru IPA Uji',
          publishedAtLabel: '27 Agustus 2026',
          open: false,
          surveyRequired: true,
          surveySemester: 'ganjil',
          usesPredicate: false,
          finalGradeLabel: 'SAS',
          complete: false,
          status: 'survei_diperlukan',
          categories: [],
          components: [],
        ),
      ],
      finalGradeLabel: 'SAS',
    );
  }
}
