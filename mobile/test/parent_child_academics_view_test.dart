import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_grades/domain/my_grades.dart';
import 'package:nusa/features/parent_child_academics/data/parent_child_academics_remote_data_source.dart';
import 'package:nusa/features/parent_child_academics/domain/parent_child_academics.dart';
import 'package:nusa/features/parent_child_academics/presentation/parent_child_academics_view.dart';

void main() {
  testWidgets('Jadwal Pelajaran Anak Saya langsung menampilkan jadwal', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 700));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final remote = _FakeParentChildAcademicsRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          parentChildAcademicsRemoteDataSourceProvider.overrideWithValue(
            remote,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const ParentChildAcademicsView(
            pageTitle: 'Jadwal Pelajaran Anak Saya',
            showTabs: false,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Jadwal Pelajaran Anak Saya'), findsWidgets);
    expect(find.text('Alya Akademik'), findsWidgets);
    expect(find.byKey(const Key('parent-academics-child-filter')), findsOne);
    expect(
      find.byKey(const Key('parent-academics-schedule-tab')),
      findsNothing,
    );
    expect(find.byKey(const Key('parent-academics-grades-tab')), findsNothing);
    expect(find.text('Matematika Mobile'), findsOneWidget);
    expect(find.text('Guru Mobile Uji'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.byKey(const Key('parent-academics-child-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bima Akademik').last);
    await tester.pumpAndSettle();
    expect(remote.studentIds.last, 102);
    expect(find.text('Bima Akademik'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Nilai Anak Saya langsung menampilkan nilai yang aman', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 700));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final remote = _FakeParentChildAcademicsRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          parentChildAcademicsRemoteDataSourceProvider.overrideWithValue(
            remote,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const ParentChildAcademicsView(
            initialTab: ParentChildAcademicsTab.grades,
            pageTitle: 'Nilai Anak Saya',
            showTabs: false,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Nilai Anak Saya'), findsWidgets);
    expect(remote.tabs.last, ParentChildAcademicsTab.grades);
    expect(
      find.byKey(const Key('parent-academics-schedule-tab')),
      findsNothing,
    );
    expect(find.byKey(const Key('parent-academics-grades-tab')), findsNothing);

    await tester.scrollUntilVisible(
      find.byKey(const Key('parent-locked-grade-92')),
      260,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text('85'), findsWidgets);
    expect(find.textContaining('Menunggu survei anak'), findsOneWidget);
    expect(find.text('Isi Survei dan Buka Nilai'), findsNothing);
    expect(find.text('99'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

final class _FakeParentChildAcademicsRemoteDataSource
    implements ParentChildAcademicsRemoteDataSource {
  final List<int?> studentIds = [];
  final List<ParentChildAcademicsTab> tabs = [];

  @override
  Future<ParentChildAcademicsPage> fetch({
    required ParentChildAcademicsTab tab,
    required String semester,
    int? studentId,
    int? academicYearId,
  }) async {
    studentIds.add(studentId);
    tabs.add(tab);
    final selected = studentId == 102 ? _bima : _alya;
    return ParentChildAcademicsPage(
      tab: tab,
      selectedStudentId: selected.id,
      children: const [_alya, _bima],
      schedule: _schedule,
      grades: _grades(selected, academicYearId, semester),
      gradeNotice:
          'Nilai yang masih terkunci hanya dapat dibuka setelah anak '
          'mengisi survei pembelajaran melalui akun siswa.',
    );
  }
}

MyGradesPage _grades(
  MyGradesStudent student,
  int? academicYearId,
  String semester,
) => MyGradesPage(
  student: student,
  academicYears: const [_year],
  selectedAcademicYear: _year,
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
  subjects: const [_openGrade, _lockedGrade],
  finalGradeLabel: 'SAS',
);

const _alya = MyGradesStudent(
  id: 101,
  name: 'Alya Akademik',
  nis: '26001',
  nisn: '0011223301',
);
const _bima = MyGradesStudent(
  id: 102,
  name: 'Bima Akademik',
  nis: '26002',
  nisn: '0011223302',
);
const _year = MyGradesAcademicYear(id: 5, name: '2026/2027', active: true);
const _schedule = ChildSchedule(
  today: 'senin',
  summary: ChildScheduleSummary(
    schoolClass: 'VIII.A',
    scheduledPeriods: 1,
    subjects: 1,
  ),
  days: [
    ChildScheduleDay(
      code: 'senin',
      label: 'Senin',
      isToday: true,
      items: [
        ChildScheduleItem(
          id: 71,
          periodNumber: 1,
          startTime: '07:30',
          endTime: '08:15',
          type: 'pelajaran',
          label: 'Matematika Mobile',
          current: false,
          scheduled: true,
          subjectName: 'Matematika Mobile',
          teacherName: 'Guru Mobile Uji',
        ),
      ],
    ),
    ChildScheduleDay(
      code: 'selasa',
      label: 'Selasa',
      isToday: false,
      items: [],
    ),
  ],
);
const _openGrade = MyGradesSubject(
  assignmentId: 91,
  subjectId: 11,
  subjectName: 'Matematika Mobile',
  teacherName: 'Guru Mobile Uji',
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
  categories: [],
  components: [],
);
const _lockedGrade = MyGradesSubject(
  assignmentId: 92,
  subjectId: 12,
  subjectName: 'Ilmu Pengetahuan Alam',
  teacherName: 'Guru IPA Uji',
  open: false,
  surveyRequired: true,
  surveySemester: 'ganjil',
  usesPredicate: false,
  finalGradeLabel: 'SAS',
  complete: false,
  status: 'survei_diperlukan',
  categories: [],
  components: [],
);
