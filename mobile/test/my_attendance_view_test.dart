import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_attendance/data/my_attendance_remote_data_source.dart';
import 'package:nusa/features/my_attendance/domain/my_attendance.dart';
import 'package:nusa/features/my_attendance/presentation/my_attendance_view.dart';

void main() {
  testWidgets('Kehadiranku tampil responsif beserta rekap dan riwayat', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 700));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final remote = _FakeMyAttendanceRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          myAttendanceRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const MyAttendanceView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Kehadiranku'), findsOneWidget);
    expect(find.text('Kehadiran Anak'), findsOneWidget);
    expect(find.text('Alya Kehadiran'), findsWidgets);
    expect(find.byKey(const Key('my-attendance-today')), findsOneWidget);
    expect(find.text('Hadir'), findsWidgets);

    await tester.scrollUntilVisible(
      find.byKey(const Key('my-attendance-summary')),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('66,7% hadir'), findsOneWidget);
    expect(find.text('3 hari tercatat'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('my-attendance-record-501')),
      280,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.textContaining('Masuk 06:54'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('orang tua dapat mengganti anak dan bulan rekap', (tester) async {
    final remote = _FakeMyAttendanceRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          myAttendanceRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const MyAttendanceView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('my-attendance-student-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bima Kehadiran').last);
    await tester.pumpAndSettle();
    expect(remote.studentIds.last, 102);
    expect(find.text('Bima Kehadiran'), findsWidgets);

    await tester.tap(find.byKey(const Key('my-attendance-previous-month')));
    await tester.pumpAndSettle();
    expect(remote.months.last, '2026-08');
    expect(tester.takeException(), isNull);
  });
}

final class _FakeMyAttendanceRemoteDataSource
    implements MyAttendanceRemoteDataSource {
  final List<int?> studentIds = [];
  final List<String?> months = [];

  @override
  Future<MyAttendancePage> fetch({
    required int? studentId,
    required int? academicYearId,
    required String? month,
  }) async {
    studentIds.add(studentId);
    months.add(month);
    const students = [
      MyAttendanceStudent(
        id: 101,
        name: 'Alya Kehadiran',
        nis: '26001',
        nisn: '0011223301',
      ),
      MyAttendanceStudent(
        id: 102,
        name: 'Bima Kehadiran',
        nis: '26002',
        nisn: '0011223302',
      ),
    ];
    const years = [
      MyAttendanceAcademicYear(id: 9, name: '2026/2027', active: true),
    ];
    final selectedStudent = studentId == 102 ? students[1] : students[0];
    final selectedMonth = month ?? '2026-09';

    return MyAttendancePage(
      mode: 'orang_tua',
      student: selectedStudent,
      students: students,
      academicYears: years,
      selectedAcademicYear: years.first,
      schoolClass: const MyAttendanceClass(
        name: 'VIII A',
        grade: 8,
        attendanceNumber: 1,
        membershipStatus: 'aktif',
      ),
      filter: MyAttendanceFilter(
        studentId: selectedStudent.id,
        academicYearId: academicYearId ?? 9,
        month: selectedMonth,
      ),
      monthLabel: selectedMonth == '2026-08'
          ? 'Agustus 2026'
          : 'September 2026',
      today: const MyAttendanceToday(
        recorded: true,
        status: 'hadir',
        statusLabel: 'Hadir',
        checkIn: '06:54',
        checkOut: '14:05',
        lateMinutes: 0,
        earlyLeaveMinutes: 0,
      ),
      summary: const MyAttendanceSummary(
        total: 3,
        present: 2,
        sick: 1,
        permitted: 0,
        absent: 0,
        late: 1,
        lateMinutes: 5,
        earlyLeave: 0,
        earlyLeaveMinutes: 0,
        presentPercentage: 66.7,
      ),
      records: const [
        MyAttendanceRecord(
          id: 501,
          date: '2026-09-08',
          dateLabel: 'Selasa, 08 September 2026',
          status: 'hadir',
          statusLabel: 'Hadir',
          checkIn: '06:54',
          checkOut: '14:05',
          lateMinutes: 0,
          earlyLeaveMinutes: 0,
          sourceLabel: 'Mesin scanner',
        ),
      ],
      emptyMessage: 'Belum ada catatan kehadiran pada bulan ini.',
    );
  }
}
