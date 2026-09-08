import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/teacher_duty/data/teacher_duty_remote_data_source.dart';
import 'package:nusa/features/teacher_duty/domain/teacher_duty.dart';
import 'package:nusa/features/teacher_duty/presentation/my_teacher_duty_view.dart';
import 'package:nusa/features/teacher_duty/presentation/teacher_duty_schedule_view.dart';

void main() {
  testWidgets('jadwal guru piket rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeTeacherDutyRemoteDataSource();

    await _pump(tester, remote, const TeacherDutyScheduleView());

    expect(find.text('Jadwal Guru Piket'), findsOneWidget);
    expect(find.byKey(const Key('duty-year-filter')), findsOneWidget);
    expect(find.text('Guru Piket NUSA'), findsOneWidget);
    expect(find.byKey(const Key('add-duty-schedule')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('guru piket mencatat siswa sakit secara native', (tester) async {
    final remote = _FakeTeacherDutyRemoteDataSource();
    await _pump(tester, remote, const MyTeacherDutyView());

    expect(find.text('Anda bertugas piket hari ini'), findsOneWidget);
    expect(find.text('Siswa Piket NUSA'), findsOneWidget);

    await tester.tap(find.text('Siswa Piket NUSA'));
    await tester.pumpAndSettle();
    expect(find.text('Catat Kehadiran'), findsOneWidget);
    await tester.tap(
      find.descendant(
        of: find.byType(SegmentedButton<String>),
        matching: find.text('Sakit'),
      ),
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'Keterangan'),
      'Demam, informasi dari orang tua.',
    );
    await tester.ensureVisible(find.text('Simpan Kehadiran'));
    await tester.tap(find.text('Simpan Kehadiran'));
    await tester.pumpAndSettle();

    expect(remote.recordCalls, 1);
    expect(remote.lastAttendanceStatus, 'sakit');
    expect(find.text('Sakit'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('guru piket mencatat siswa belum scan sebagai hadir', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeTeacherDutyRemoteDataSource();

    await _pump(tester, remote, const MyTeacherDutyView());
    await tester.tap(find.text('Siswa Piket NUSA'));
    await tester.pumpAndSettle();

    expect(find.text('Catat Kehadiran'), findsOneWidget);
    expect(find.text('Hadir'), findsWidgets);
    await tester.enterText(
      find.widgetWithText(TextField, 'Keterangan'),
      'Lupa membawa kartu, dikonfirmasi hadir oleh guru piket.',
    );
    await tester.ensureVisible(find.text('Simpan Kehadiran'));
    await tester.tap(find.text('Simpan Kehadiran'));
    await tester.pumpAndSettle();

    expect(remote.recordCalls, 1);
    expect(remote.lastAttendanceStatus, 'hadir');
    expect(find.text('Hadir'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pump(
  WidgetTester tester,
  TeacherDutyRemoteDataSource remote,
  Widget child,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        teacherDutyRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(theme: AppTheme.light, home: child),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeTeacherDutyRemoteDataSource
    implements TeacherDutyRemoteDataSource {
  static const year = DutyAcademicYear(id: 1, name: '2026/2027', active: true);
  static const dutyDay = DutyDay(code: 'kamis', label: 'Kamis');
  static const teacher = DutyTeacher(
    id: 3,
    name: 'Guru Piket NUSA',
    employeeNumber: '198001',
  );
  int recordCalls = 0;
  String lastAttendanceStatus = 'belum_scan';

  @override
  Future<DutyScheduleCatalog> fetchSchedules({
    int? academicYearId,
    required String day,
    required String status,
    required String query,
  }) async => DutyScheduleCatalog(
    items: const [
      DutySchedule(
        id: 5,
        academicYear: year,
        teacher: teacher,
        day: 'kamis',
        dayLabel: 'Kamis',
        active: true,
      ),
    ],
    summary: const DutyScheduleSummary(
      activeSchedules: 1,
      teachers: 1,
      filledDays: 1,
    ),
    academicYears: const [year],
    days: const [dutyDay],
    academicYearId: 1,
    day: day,
    status: status,
    query: query,
    canManage: true,
  );

  @override
  Future<DutyScheduleReference> fetchReference([int? academicYearId]) async =>
      const DutyScheduleReference(
        academicYears: [year],
        teachers: [teacher],
        days: [dutyDay],
        academicYearId: 1,
      );

  @override
  Future<MyDutyDashboard> fetchMyDuty({
    int? classId,
    required String status,
    required String query,
    required int page,
  }) async => MyDutyDashboard(
    dateLabel: 'Kamis, 13 Agustus 2026',
    academicYear: year,
    today: dutyDay,
    mySchedules: const [dutyDay],
    activeSubjectTeacher: true,
    canRecordToday: true,
    items: [
      MyDutyStudent(
        classMemberId: 10,
        name: 'Siswa Piket NUSA',
        initials: 'SP',
        schoolClass: 'VII.A',
        status: lastAttendanceStatus,
        statusLabel: switch (lastAttendanceStatus) {
          'hadir' => 'Hadir',
          'sakit' => 'Sakit',
          'izin' => 'Izin',
          _ => 'Belum scan',
        },
        canRecord: true,
        studentNumber: '26001',
        notes: switch (lastAttendanceStatus) {
          'hadir' => 'Lupa membawa kartu',
          'sakit' => 'Demam',
          'izin' => 'Izin dari orang tua',
          _ => null,
        },
      ),
    ],
    summary: MyDutySummary(
      total: 1,
      present: lastAttendanceStatus == 'hadir' ? 1 : 0,
      sick: lastAttendanceStatus == 'sakit' ? 1 : 0,
      permitted: lastAttendanceStatus == 'izin' ? 1 : 0,
      notScanned: lastAttendanceStatus == 'belum_scan' ? 1 : 0,
    ),
    classes: const [DutyClass(id: 2, name: 'VII.A')],
    status: status,
    query: query,
    page: page,
    hasMore: false,
    classId: classId,
  );

  @override
  Future<void> recordAttendance({
    required int classMemberId,
    required String status,
    required String notes,
  }) async {
    recordCalls++;
    lastAttendanceStatus = status;
  }

  @override
  Future<void> createSchedule(DutyScheduleFormValue value) async {}
  @override
  Future<void> updateSchedule(int id, DutyScheduleFormValue value) async {}
  @override
  Future<void> deleteSchedule(int id) async {}
}
