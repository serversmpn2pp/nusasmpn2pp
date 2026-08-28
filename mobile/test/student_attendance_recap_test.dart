import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_recap/data/student_attendance_recap_remote_data_source.dart';
import 'package:nusa/features/student_attendance_recap/domain/student_attendance_recap.dart';
import 'package:nusa/features/student_attendance_recap/presentation/student_attendance_recap_view.dart';

void main() {
  test('parses recap, source, and correction access', () {
    final page = StudentAttendanceRecapPage.fromJson({
      'tanggal': '2026-08-28',
      'tanggal_label': 'Jumat, 28 Agustus 2026',
      'items': [_recordJson()],
      'ringkasan': {
        'total': 2,
        'hadir': 1,
        'izin': 0,
        'sakit': 0,
        'alfa': 0,
        'belum_scan': 1,
        'terlambat': 1,
        'pulang_cepat': 0,
        'belum_pulang': 1,
      },
      'tahun_pelajaran': [
        {'id': 1, 'nama': '2026/2027', 'aktif': true},
      ],
      'kelas': [
        {'id': 2, 'nama': 'VII.A', 'tingkat': 7},
      ],
      'filter': {
        'tahun_pelajaran_id': 1,
        'kelas_id': 2,
        'status': 'semua',
        'cari': '',
      },
      'paginasi': {'halaman': 1, 'ada_halaman_berikutnya': false},
      'hak_akses': {
        'dapat_koreksi': true,
        'koreksi_hari_ini_terbatas': false,
        'cakupan_wali_kelas': false,
      },
    });

    expect(page.summary.notScanned, 1);
    expect(page.items.single.source, 'scan');
    expect(page.items.single.lateMinutes, 10);
    expect(page.items.single.correction.allowed, isTrue);
  });

  testWidgets('rekap dan koreksi presensi rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentAttendanceRecapRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentAttendanceRecapRemoteDataSourceProvider.overrideWithValue(
            remote,
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentAttendanceRecapView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Rekap & Koreksi Presensi'), findsOneWidget);
    expect(find.byKey(const Key('attendance-recap-date')), findsOneWidget);
    expect(find.byKey(const Key('attendance-recap-year')), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.drag(find.byType(CustomScrollView), const Offset(0, -420));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('attendance-record-1')));
    await tester.tap(find.byKey(const Key('attendance-record-1')));
    await tester.pumpAndSettle();
    expect(find.text('Mesin scanner'), findsWidgets);
    await tester.ensureVisible(find.text('Koreksi Presensi'));
    await tester.tap(find.text('Koreksi Presensi'));
    await tester.pumpAndSettle();

    expect(
      find.byKey(const Key('attendance-correction-status')),
      findsOneWidget,
    );
    await tester.tap(find.byKey(const Key('attendance-correction-status')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sakit').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('attendance-correction-notes')),
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

Map<String, dynamic> _recordJson() => {
  'anggota_kelas_id': 1,
  'nomor_absen': 1,
  'siswa': {
    'id': 1,
    'nama': 'Siswa Rekap NUSA',
    'nis': '28001',
    'nisn': '0011228801',
    'inisial': 'SR',
  },
  'kelas': {'id': 2, 'nama': 'VII.A'},
  'presensi': {
    'id': 3,
    'status': 'hadir',
    'status_label': 'Hadir',
    'sumber': 'scan',
    'sumber_label': 'Mesin scanner',
    'jam_masuk': '07:10',
    'status_masuk': 'terlambat',
    'menit_terlambat': 10,
    'jam_pulang': null,
    'status_pulang': null,
    'menit_pulang_cepat': 0,
    'belum_pulang': true,
  },
  'koreksi': {'dapat': true, 'alasan': null, 'terbatas_hari_ini': false},
};

final class _FakeStudentAttendanceRecapRemoteDataSource
    implements StudentAttendanceRecapRemoteDataSource {
  int correctCalls = 0;
  String lastStatus = 'hadir';

  StudentAttendanceRecord get record => StudentAttendanceRecord.fromJson({
    ..._recordJson(),
    'presensi': {
      ...(_recordJson()['presensi'] as Map<String, dynamic>),
      'status': lastStatus,
      'status_label': lastStatus == 'sakit' ? 'Sakit' : 'Hadir',
      'sumber': lastStatus == 'sakit' ? 'manual' : 'scan',
      'sumber_label': lastStatus == 'sakit'
          ? 'Koreksi petugas'
          : 'Mesin scanner',
    },
  });

  @override
  Future<StudentAttendanceRecapPage> fetch({
    required String date,
    int? academicYearId,
    int? classId,
    required String status,
    required String query,
    required int page,
  }) async => StudentAttendanceRecapPage(
    date: date,
    dateLabel: 'Jumat, 28 Agustus 2026',
    items: [record],
    summary: StudentAttendanceSummary(
      total: 1,
      present: lastStatus == 'hadir' ? 1 : 0,
      permitted: 0,
      sick: lastStatus == 'sakit' ? 1 : 0,
      absent: 0,
      notScanned: 0,
      late: lastStatus == 'hadir' ? 1 : 0,
      earlyLeave: 0,
      notCheckedOut: lastStatus == 'hadir' ? 1 : 0,
    ),
    academicYears: const [
      AttendanceAcademicYear(id: 1, name: '2026/2027', active: true),
    ],
    classes: const [AttendanceClassOption(id: 2, name: 'VII.A', level: 7)],
    academicYearId: 1,
    classId: 2,
    status: status,
    query: query,
    page: page,
    hasMore: false,
    canCorrect: true,
    todayOnly: false,
    guardianScope: false,
  );

  @override
  Future<StudentAttendanceDetail> detail({
    required int classMemberId,
    required String date,
  }) async => StudentAttendanceDetail(
    date: date,
    dateLabel: 'Jumat, 28 Agustus 2026',
    record: record,
    scheduleAvailable: true,
    officialCheckIn: '07:00',
    officialCheckOut: '14:00',
    history: const [
      AttendanceHistoryEntry(
        id: 1,
        beforeStatus: null,
        afterStatus: 'hadir',
        sourceLabel: 'Mesin scanner',
      ),
    ],
    correction: const AttendanceCorrectionAccess(
      allowed: true,
      todayOnly: false,
    ),
  );

  @override
  Future<void> correct({
    required int classMemberId,
    required String date,
    required AttendanceCorrectionValue value,
  }) async {
    correctCalls++;
    lastStatus = value.status;
  }
}
