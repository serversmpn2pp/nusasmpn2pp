import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_attendance_recap/data/employee_attendance_recap_remote_data_source.dart';
import 'package:nusa/features/employee_attendance_recap/domain/employee_attendance_recap.dart';
import 'package:nusa/features/employee_attendance_recap/presentation/employee_attendance_recap_view.dart';

void main() {
  test('parses employee recap, summary, and private scope', () {
    final page = EmployeeAttendanceRecapPage.fromJson({
      'tanggal': '2026-08-28',
      'tanggal_label': 'Jumat, 28 Agustus 2026',
      'items': [_recordJson()],
      'ringkasan': {
        'total': 2,
        'hadir': 1,
        'izin': 0,
        'sakit': 0,
        'dinas_luar': 0,
        'cuti': 0,
        'alfa': 1,
        'terlambat': 1,
        'pulang_cepat': 0,
        'belum_pulang': 1,
      },
      'jenis_pegawai': ['Guru'],
      'pegawai': [
        {'id': 1, 'nama': 'Guru Rekap NUSA', 'nip': '19860101'},
      ],
      'filter': {
        'jenis_pegawai': 'Guru',
        'pegawai_id': 1,
        'status_pegawai': 'aktif',
        'status': 'semua',
        'cari': '',
      },
      'paginasi': {'halaman': 1, 'ada_halaman_berikutnya': false},
      'hak_akses': {'cakupan_pribadi': false, 'dapat_koreksi': true},
    });

    expect(page.summary.absent, 1);
    expect(page.summary.late, 1);
    expect(page.items.single.sourceLabel, 'Mesin scanner');
    expect(page.items.single.canCorrect, isTrue);
    expect(page.privateScope, isFalse);
  });

  testWidgets('rekap dan koreksi pegawai rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 720);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeEmployeeAttendanceRecapRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          employeeAttendanceRecapRemoteDataSourceProvider.overrideWithValue(
            remote,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const EmployeeAttendanceRecapView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Rekap Presensi Pegawai'), findsOneWidget);
    expect(find.byKey(const Key('employee-attendance-date')), findsOneWidget);
    expect(find.byKey(const Key('employee-attendance-type')), findsOneWidget);
    expect(tester.takeException(), isNull);

    final record = find.byKey(const Key('employee-attendance-record-1'));
    await tester.scrollUntilVisible(
      record,
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -160));
    await tester.pumpAndSettle();
    await tester.tap(record);
    await tester.pumpAndSettle();

    expect(find.text('Mesin scanner'), findsWidgets);
    await tester.ensureVisible(find.text('Koreksi Presensi'));
    await tester.tap(find.text('Koreksi Presensi'));
    await tester.pumpAndSettle();

    await tester.tap(
      find.byKey(const Key('employee-attendance-correction-status')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sakit').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('employee-attendance-correction-notes')),
      'Surat sakit diterima petugas.',
    );
    await tester.ensureVisible(find.text('Simpan Koreksi'));
    await tester.tap(find.text('Simpan Koreksi'));
    await tester.pumpAndSettle();

    expect(remote.correctCalls, 1);
    expect(remote.lastStatus, 'sakit');
    expect(tester.takeException(), isNull);
  });
}

Map<String, dynamic> _recordJson({String status = 'hadir'}) => {
  'pegawai': {
    'id': 1,
    'nama': 'Guru Rekap NUSA',
    'nip': '19860101',
    'inisial': 'GR',
    'jenis_pegawai': 'Guru',
    'jabatan': 'Guru Mata Pelajaran',
    'status_kepegawaian': 'PNS',
    'aktif': true,
  },
  'presensi': {
    'id': 3,
    'status': status,
    'status_label': status == 'sakit' ? 'Sakit' : 'Hadir',
    'sumber': status == 'sakit' ? 'catatan' : 'catatan',
    'sumber_label': status == 'sakit' ? 'Koreksi manual' : 'Mesin scanner',
    'jam_masuk': status == 'hadir' ? '07:10' : null,
    'status_masuk': status == 'hadir' ? 'terlambat' : null,
    'menit_terlambat': status == 'hadir' ? 10 : 0,
    'jam_pulang': null,
    'status_pulang': null,
    'menit_pulang_cepat': 0,
    'belum_pulang': status == 'hadir',
    'nama_jadwal': 'Jadwal Guru',
  },
  'koreksi': {'dapat': true},
};

final class _FakeEmployeeAttendanceRecapRemoteDataSource
    implements EmployeeAttendanceRecapRemoteDataSource {
  int correctCalls = 0;
  String lastStatus = 'hadir';

  EmployeeAttendanceRecord get record =>
      EmployeeAttendanceRecord.fromJson(_recordJson(status: lastStatus));

  @override
  Future<EmployeeAttendanceRecapPage> fetch({
    required String date,
    String? employeeType,
    int? employeeId,
    required String employeeStatus,
    required String status,
    required String query,
    required int page,
  }) async => EmployeeAttendanceRecapPage(
    date: date,
    dateLabel: 'Jumat, 28 Agustus 2026',
    items: [record],
    summary: EmployeeAttendanceSummary(
      total: 1,
      present: lastStatus == 'hadir' ? 1 : 0,
      permitted: 0,
      sick: lastStatus == 'sakit' ? 1 : 0,
      officialDuty: 0,
      leave: 0,
      absent: 0,
      late: lastStatus == 'hadir' ? 1 : 0,
      earlyLeave: 0,
      notCheckedOut: lastStatus == 'hadir' ? 1 : 0,
    ),
    employeeTypes: const ['Guru'],
    employees: const [EmployeeAttendanceOption(id: 1, name: 'Guru Rekap NUSA')],
    employeeStatus: employeeStatus,
    status: status,
    query: query,
    page: page,
    hasMore: false,
    privateScope: false,
    canCorrect: true,
  );

  @override
  Future<EmployeeAttendanceDetail> detail({
    required int employeeId,
    required String date,
  }) async => EmployeeAttendanceDetail(
    date: date,
    dateLabel: 'Jumat, 28 Agustus 2026',
    record: record,
    scheduleAvailable: true,
    scheduleName: 'Jadwal Guru',
    officialCheckIn: '07:00',
    officialCheckOut: '14:00',
    privateScope: false,
    canCorrect: true,
  );

  @override
  Future<void> correct({
    required int employeeId,
    required String date,
    required EmployeeAttendanceCorrectionValue value,
  }) async {
    correctCalls++;
    lastStatus = value.status;
  }
}
