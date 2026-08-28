import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_scan_status/data/student_scan_status_remote_data_source.dart';
import 'package:nusa/features/student_scan_status/domain/student_scan_status.dart';
import 'package:nusa/features/student_scan_status/presentation/student_scan_status_view.dart';

void main() {
  testWidgets('status scan siswa rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentScanStatusRemoteDataSource();

    await _pumpView(tester, remote);

    expect(find.text('Status Scan Presensi Siswa'), findsOneWidget);
    expect(find.text('Monitoring dari server sekolah'), findsOneWidget);
    expect(find.text('Hanya monitoring'), findsOneWidget);
    expect(
      find.byKey(const Key('student-scan-status-class-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('student-scan-status-result-filter')),
      findsOneWidget,
    );
    await tester.fling(
      find.byType(CustomScrollView),
      const Offset(0, -1100),
      2200,
    );
    await tester.pumpAndSettle();
    expect(find.text('Ananda Scan Mobile'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('status scan mencari data dan memperbarui otomatis dari server', (
    tester,
  ) async {
    final remote = _FakeStudentScanStatusRemoteDataSource();
    await _pumpView(tester, remote);
    expect(remote.fetchCalls, 1);

    await tester.enterText(
      find.byKey(const Key('student-scan-status-search')),
      'Ananda',
    );
    await tester.pump(const Duration(milliseconds: 451));
    await tester.pumpAndSettle();

    expect(remote.lastQuery, 'Ananda');
    expect(remote.fetchCalls, 2);

    await tester.pump(const Duration(seconds: 15));
    await tester.pump();
    expect(remote.fetchCalls, greaterThanOrEqualTo(3));

    await tester.pumpWidget(const SizedBox());
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  StudentScanStatusRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        studentScanStatusRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const StudentScanStatusView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeStudentScanStatusRemoteDataSource
    implements StudentScanStatusRemoteDataSource {
  int fetchCalls = 0;
  int? lastClassId;
  String lastStatus = 'semua';
  String lastQuery = '';

  @override
  Future<StudentScanStatusDashboard> fetch({
    required int? classId,
    required String status,
    required String query,
  }) async {
    fetchCalls++;
    lastClassId = classId;
    lastStatus = status;
    lastQuery = query;

    return StudentScanStatusDashboard(
      date: '2026-08-27',
      dateLabel: 'Kamis, 27 Agustus 2026',
      serverTime: DateTime(2026, 8, 27, 6, 35),
      nextRefreshSeconds: 15,
      academicYear: const ScanAcademicYear(id: 1, name: '2026/2027'),
      schedule: const ScanScheduleStatus(
        available: true,
        day: 'kamis',
        dayLabel: 'Kamis',
        phase: 'scan_masuk',
        phaseLabel: 'Scan masuk berlangsung',
        checkInScanStart: '06:00',
        checkInTime: '07:00',
        checkInScanEnd: '07:30',
        checkOutScanStart: '14:00',
        checkOutTime: '14:10',
        checkOutScanEnd: '15:00',
      ),
      summary: const StudentScanSummary(
        studentCount: 32,
        checkedIn: 20,
        late: 2,
        checkedOut: 0,
        notCheckedIn: 12,
        notCheckedOut: 20,
        successfulScans: 20,
        alreadyRecorded: 1,
        needsAttention: 1,
      ),
      activities: const [
        StudentScanActivity(
          id: 1,
          successful: true,
          status: 'berhasil_masuk',
          statusLabel: 'Presensi masuk tersimpan',
          message: 'Presensi masuk berhasil dicatat.',
          scanType: 'masuk',
          scanTypeLabel: 'Masuk',
          scannerId: 'S1',
          scanTime: '06:31:10',
          student: ScannedStudent(
            id: 1,
            name: 'Ananda Scan Mobile',
            initials: 'AS',
            studentNumber: '20001',
            nationalStudentNumber: '0011223344',
            className: 'VII.A',
          ),
          attendance: ScanAttendanceResult(
            checkInTime: '06:31:10',
            checkInStatus: 'tepat_waktu',
            lateMinutes: 0,
            earlyLeaveMinutes: 0,
            attendanceStatus: 'hadir',
          ),
        ),
      ],
      classes: const [ScanClassOption(id: 1, name: 'VII.A', level: 7)],
      selectedClassId: classId,
      status: status,
      query: query,
    );
  }
}
