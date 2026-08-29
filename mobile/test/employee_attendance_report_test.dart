import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_attendance_report/data/employee_attendance_report_remote_data_source.dart';
import 'package:nusa/features/employee_attendance_report/domain/employee_attendance_report.dart';
import 'package:nusa/features/employee_attendance_report/presentation/employee_attendance_report_view.dart';

void main() {
  test('mem-parsing laporan pegawai dan alfa inferensi', () {
    final detail = EmployeeAttendanceReportDetail.fromJson({
      'pegawai': {'id': 7, 'nama': 'Guru NUSA', 'inisial': 'GN', 'aktif': true},
      'periode': {'bulan': '2026-08', 'label': 'Agustus 2026'},
      'ringkasan': _summaryJson(),
      'rincian': [
        {
          'tanggal': '2026-08-27',
          'tanggal_label': 'Kam, 27 Agu 2026',
          'hari': 'Kamis',
          'status': 'alfa',
          'status_label': 'Alfa',
          'inferensi': true,
          'menit_terlambat': 0,
          'menit_pulang_cepat': 0,
          'keterangan': 'Belum ada scan atau koreksi.',
        },
      ],
      'hak_akses': {'cakupan_pribadi': true},
    });

    expect(detail.employee.summary.averageAttendance, 80);
    expect(detail.days.single.inferred, isTrue);
    expect(detail.privateScope, isTrue);
  });

  testWidgets('laporan pegawai dan rincian rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 720);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeEmployeeAttendanceReportRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          employeeAttendanceReportRemoteDataSourceProvider.overrideWithValue(
            remote,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const EmployeeAttendanceReportView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Laporan Presensi Pegawai'), findsOneWidget);
    expect(find.byKey(const Key('employee-report-month')), findsOneWidget);
    expect(find.byKey(const Key('employee-report-type')), findsOneWidget);
    expect(tester.takeException(), isNull);

    final item = find.byKey(const Key('employee-report-item-7'));
    await tester.scrollUntilVisible(
      item,
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -150));
    await tester.pumpAndSettle();
    await tester.tap(item);
    await tester.pumpAndSettle();

    expect(find.text('Rincian Harian'), findsOneWidget);
    expect(find.textContaining('Alfa inferensi'), findsOneWidget);
    expect(find.textContaining('Jadwal Guru'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Map<String, dynamic> _summaryJson() => {
  'hari_efektif': 5,
  'hadir': 4,
  'izin': 0,
  'sakit': 0,
  'dinas_luar': 0,
  'cuti': 0,
  'alfa': 1,
  'terlambat': 1,
  'menit_terlambat': 10,
  'pulang_cepat': 0,
  'menit_pulang_cepat': 0,
  'belum_pulang': 0,
  'manual': 0,
  'persentase_hadir': 80,
};

EmployeeAttendanceReportSummary _summary() =>
    const EmployeeAttendanceReportSummary(
      employees: 1,
      effectiveDays: 5,
      present: 4,
      permitted: 0,
      sick: 0,
      officialDuty: 0,
      leave: 0,
      absent: 1,
      late: 1,
      lateMinutes: 10,
      earlyLeave: 0,
      earlyLeaveMinutes: 0,
      notCheckedOut: 0,
      manual: 0,
      averageAttendance: 80,
    );

EmployeeAttendanceReportItem _item() => EmployeeAttendanceReportItem(
  employeeId: 7,
  name: 'Guru NUSA',
  initials: 'GN',
  active: true,
  summary: _summary(),
  nip: '19860101',
  employeeType: 'Guru',
  position: 'Guru Mata Pelajaran',
);

final class _FakeEmployeeAttendanceReportRemoteDataSource
    implements EmployeeAttendanceReportRemoteDataSource {
  @override
  Future<EmployeeAttendanceReportPage> fetch(
    Map<String, dynamic> query,
  ) async => EmployeeAttendanceReportPage(
    month: query['bulan'] as String? ?? '2026-08',
    periodLabel: 'Agustus 2026',
    startDate: '2026-08-01',
    endDate: '2026-08-31',
    summary: _summary(),
    items: [_item()],
    employeeTypes: const ['Guru'],
    employees: const [
      EmployeeReportOption(id: 7, name: 'Guru NUSA', nip: '19860101'),
    ],
    employeeStatus: query['status_pegawai'] as String? ?? 'aktif',
    query: query['cari'] as String? ?? '',
    page: query['halaman'] as int? ?? 1,
    hasMore: false,
    privateScope: false,
  );

  @override
  Future<EmployeeAttendanceReportDetail> detail(
    int employeeId,
    Map<String, dynamic> query,
  ) async => EmployeeAttendanceReportDetail(
    employee: _item(),
    month: query['bulan'] as String? ?? '2026-08',
    periodLabel: 'Agustus 2026',
    summary: _summary(),
    days: const [
      EmployeeAttendanceReportDay(
        date: '2026-08-27',
        dateLabel: 'Kam, 27 Agu 2026',
        day: 'Kamis',
        status: 'alfa',
        statusLabel: 'Alfa',
        inferred: true,
        lateMinutes: 0,
        earlyLeaveMinutes: 0,
        description: 'Belum ada scan atau koreksi.',
        scheduleName: 'Jadwal Guru',
        scheduledCheckIn: '07:00',
        scheduledCheckOut: '14:00',
      ),
    ],
    privateScope: false,
  );
}
