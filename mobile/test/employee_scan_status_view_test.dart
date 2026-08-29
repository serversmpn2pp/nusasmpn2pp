import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_scan_status/data/employee_scan_status_remote_data_source.dart';
import 'package:nusa/features/employee_scan_status/domain/employee_scan_status.dart';
import 'package:nusa/features/employee_scan_status/presentation/employee_scan_status_view.dart';

void main() {
  test('respons status scan pegawai dapat dipetakan', () {
    final dashboard = EmployeeScanStatusDashboard.fromJson({
      'tanggal': '2026-08-27',
      'tanggal_label': 'Kamis, 27 Agustus 2026',
      'waktu_server': '2026-08-27T06:35:00+07:00',
      'pembaruan_berikutnya_detik': 15,
      'jadwal': {
        'tersedia': true,
        'jumlah': 1,
        'hari': 'kamis',
        'hari_label': 'Kamis',
        'fase': 'scan_masuk',
        'fase_label': 'Scan masuk berlangsung',
        'jam_scan_masuk_mulai': '06:00',
        'jam_scan_masuk_selesai': '07:30',
        'jam_scan_pulang_mulai': '14:00',
        'jam_scan_pulang_selesai': '15:00',
        'items': [
          {
            'id': 1,
            'nama': 'Jadwal Guru',
            'cakupan': 'Jenis Pegawai',
            'sasaran': 'Guru',
            'jam_masuk': '07:00',
            'jam_pulang': '14:10',
          },
        ],
      },
      'ringkasan': {
        'jumlah_pegawai': 10,
        'sudah_masuk': 8,
        'terlambat': 1,
        'sudah_pulang': 0,
        'belum_scan_masuk': 2,
        'belum_scan_pulang': 8,
        'scan_berhasil': 8,
        'sudah_tercatat': 1,
        'perlu_perhatian': 1,
      },
      'aktivitas': [
        {
          'id': 1,
          'berhasil': true,
          'status': 'berhasil_masuk',
          'status_label': 'Presensi masuk tersimpan',
          'jenis_scan': 'masuk',
          'jenis_scan_label': 'Masuk',
          'jam_scan': '06:31:10',
          'pegawai': {
            'id': 2,
            'nama': 'Antonius',
            'nip': '19860101',
            'jenis_pegawai': 'Guru',
            'inisial': 'AN',
          },
        },
      ],
      'jenis_pegawai': ['Guru'],
      'filter': {'jenis_pegawai': 'Guru', 'status': 'semua', 'cari': ''},
    });

    expect(dashboard.schedule.count, 1);
    expect(dashboard.schedule.items.single.name, 'Jadwal Guru');
    expect(dashboard.summary.employeeCount, 10);
    expect(dashboard.activities.single.employee?.name, 'Antonius');
    expect(dashboard.selectedEmployeeType, 'Guru');
  });

  testWidgets('status scan pegawai rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeEmployeeScanStatusRemoteDataSource();

    await _pumpView(tester, remote);

    expect(find.text('Status Scan Presensi Pegawai'), findsOneWidget);
    expect(find.text('Monitoring dari server sekolah'), findsOneWidget);
    expect(find.text('Hanya monitoring'), findsOneWidget);
    expect(find.text('1 jadwal aktif'), findsOneWidget);
    expect(
      find.byKey(const Key('employee-scan-status-type-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('employee-scan-status-result-filter')),
      findsOneWidget,
    );
    await tester.fling(
      find.byType(CustomScrollView),
      const Offset(0, -1100),
      2200,
    );
    await tester.pumpAndSettle();
    expect(find.text('Antonius Scan Mobile'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('status scan mencari dan memperbarui otomatis dari server', (
    tester,
  ) async {
    final remote = _FakeEmployeeScanStatusRemoteDataSource();
    await _pumpView(tester, remote);
    expect(remote.fetchCalls, 1);

    await tester.enterText(
      find.byKey(const Key('employee-scan-status-search')),
      'Antonius',
    );
    await tester.pump(const Duration(milliseconds: 451));
    await tester.pumpAndSettle();

    expect(remote.lastQuery, 'Antonius');
    expect(remote.fetchCalls, 2);

    await tester.pump(const Duration(seconds: 15));
    await tester.pump();
    expect(remote.fetchCalls, greaterThanOrEqualTo(3));

    await tester.pumpWidget(const SizedBox());
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  EmployeeScanStatusRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        employeeScanStatusRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: const EmployeeScanStatusView(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeEmployeeScanStatusRemoteDataSource
    implements EmployeeScanStatusRemoteDataSource {
  int fetchCalls = 0;
  String? lastEmployeeType;
  String lastStatus = 'semua';
  String lastQuery = '';

  @override
  Future<EmployeeScanStatusDashboard> fetch({
    required String? employeeType,
    required String status,
    required String query,
  }) async {
    fetchCalls++;
    lastEmployeeType = employeeType;
    lastStatus = status;
    lastQuery = query;

    return EmployeeScanStatusDashboard(
      date: '2026-08-27',
      dateLabel: 'Kamis, 27 Agustus 2026',
      serverTime: DateTime(2026, 8, 27, 6, 35),
      nextRefreshSeconds: 15,
      schedule: const EmployeeScanSchedule(
        available: true,
        count: 1,
        day: 'kamis',
        dayLabel: 'Kamis',
        phase: 'scan_masuk',
        phaseLabel: 'Scan masuk berlangsung',
        checkInScanStart: '06:00',
        checkInScanEnd: '07:30',
        checkOutScanStart: '14:00',
        checkOutScanEnd: '15:00',
        items: [
          EmployeeScanScheduleItem(
            id: 1,
            name: 'Jadwal Guru',
            scope: 'Jenis Pegawai',
            target: 'Guru',
            checkInTime: '07:00',
            checkOutTime: '14:10',
          ),
        ],
      ),
      summary: const EmployeeScanSummary(
        employeeCount: 18,
        checkedIn: 12,
        late: 2,
        checkedOut: 0,
        notCheckedIn: 6,
        notCheckedOut: 12,
        successfulScans: 12,
        alreadyRecorded: 1,
        needsAttention: 1,
      ),
      activities: const [
        EmployeeScanActivity(
          id: 1,
          successful: true,
          status: 'berhasil_masuk',
          statusLabel: 'Presensi masuk tersimpan',
          message: 'Presensi masuk berhasil dicatat.',
          scanType: 'masuk',
          scanTypeLabel: 'Masuk',
          scannerId: 'P1',
          scanTime: '06:31:10',
          employee: ScannedEmployee(
            id: 1,
            name: 'Antonius Scan Mobile',
            initials: 'AS',
            nip: '198601012026081001',
            type: 'Guru',
            position: 'Guru Mata Pelajaran',
          ),
          attendance: EmployeeScanAttendance(
            checkInTime: '06:31:10',
            checkInStatus: 'tepat_waktu',
            lateMinutes: 0,
            earlyLeaveMinutes: 0,
            attendanceStatus: 'hadir',
            scheduleName: 'Jadwal Guru',
          ),
        ),
      ],
      employeeTypes: const ['Guru', 'Tenaga Kependidikan'],
      selectedEmployeeType: employeeType,
      status: status,
      query: query,
    );
  }
}
