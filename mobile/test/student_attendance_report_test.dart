import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_report/data/student_attendance_report_download_saver.dart';
import 'package:nusa/features/student_attendance_report/data/student_attendance_report_remote_data_source.dart';
import 'package:nusa/features/student_attendance_report/domain/student_attendance_report.dart';
import 'package:nusa/features/student_attendance_report/presentation/student_attendance_report_view.dart';

void main() {
  test('mem-parsing laporan dan alfa inferensi', () {
    final detail = StudentAttendanceReportDetail.fromJson({
      'siswa': {
        'anggota_kelas_id': 7,
        'nama': 'Siswa NUSA',
        'inisial': 'SN',
        'kelas': 'VII.A',
      },
      'periode': {'label': 'Agustus 2026'},
      'ringkasan': _summaryJson(),
      'rincian': [
        {
          'tanggal': '2026-08-27',
          'tanggal_label': 'Kam, 27 Agu 2026',
          'status': 'alfa',
          'status_label': 'Alfa',
          'inferensi': true,
        },
      ],
    });
    expect(detail.student.attendancePercentage, 80);
    expect(detail.days.single.inferred, isTrue);
  });

  testWidgets(
    'laporan presensi rapi, membuka rincian, dan export pada layar sempit',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeReportRemote();
      final saver = _FakeSaver();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            studentAttendanceReportRemoteDataSourceProvider.overrideWithValue(
              remote,
            ),
            studentAttendanceReportDownloadSaverProvider.overrideWithValue(
              saver,
            ),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const StudentAttendanceReportView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Laporan Presensi Siswa'), findsWidgets);
      expect(find.byKey(const Key('attendance-report-period')), findsOneWidget);
      expect(find.byKey(const Key('attendance-report-export')), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('attendance-report-export')));
      await tester.pumpAndSettle();
      expect(remote.downloadCalls, 1);
      expect(saver.calls, 1);
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();

      await tester.scrollUntilVisible(
        find.byKey(const Key('attendance-report-student-7')),
        260,
        scrollable: find.byType(Scrollable).first,
      );
      await Scrollable.ensureVisible(
        tester.element(find.byKey(const Key('attendance-report-student-7'))),
        alignment: .5,
        duration: const Duration(milliseconds: 100),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('attendance-report-student-7')));
      await tester.pumpAndSettle();
      expect(find.text('Rincian Harian'), findsOneWidget);
      expect(find.textContaining('Alfa inferensi'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}

Map<String, dynamic> _summaryJson() => {
  'hari_efektif': 5,
  'hadir': 4,
  'izin': 0,
  'sakit': 0,
  'alfa': 1,
  'terlambat': 1,
  'menit_terlambat': 10,
  'pulang_cepat': 0,
  'menit_pulang_cepat': 0,
  'persentase_hadir': 80,
};

StudentAttendanceReportItem _item() => const StudentAttendanceReportItem(
  classMemberId: 7,
  name: 'Siswa NUSA',
  initials: 'SN',
  className: 'VII.A',
  effectiveDays: 5,
  present: 4,
  permitted: 0,
  sick: 0,
  absent: 1,
  late: 1,
  lateMinutes: 10,
  earlyLeave: 0,
  earlyLeaveMinutes: 0,
  attendancePercentage: 80,
  studentNumber: '28001',
  nationalStudentNumber: '0011228801',
);

final class _FakeReportRemote
    implements StudentAttendanceReportRemoteDataSource {
  int downloadCalls = 0;
  @override
  Future<StudentAttendanceReportPage> fetch(Map<String, dynamic> query) async =>
      StudentAttendanceReportPage(
        period: query['periode'] as String? ?? 'bulanan',
        periodLabel: '1–31 Agustus 2026',
        startDate: '2026-08-01',
        endDate: '2026-08-31',
        summary: const StudentAttendanceReportSummary(
          students: 1,
          effectiveDays: 5,
          present: 4,
          permitted: 0,
          sick: 0,
          absent: 1,
          late: 1,
          lateMinutes: 10,
          earlyLeave: 0,
          earlyLeaveMinutes: 0,
          averageAttendance: 80,
        ),
        items: [_item()],
        academicYears: const [
          ReportAcademicYear(id: 1, name: '2026/2027', active: true),
        ],
        classes: const [ReportClassOption(id: 2, name: 'VII.A')],
        academicYearId: 1,
        classId: 2,
        date: '2026-08-27',
        month: '2026-08',
        semester: 'ganjil',
        query: '',
        page: 1,
        hasMore: false,
        guardianScope: false,
        canExport: true,
      );

  @override
  Future<StudentAttendanceReportDetail> detail(
    int classMemberId,
    Map<String, dynamic> query,
  ) async => StudentAttendanceReportDetail(
    student: _item(),
    periodLabel: '1–31 Agustus 2026',
    summary: const StudentAttendanceReportSummary(
      students: 1,
      effectiveDays: 5,
      present: 4,
      permitted: 0,
      sick: 0,
      absent: 1,
      late: 1,
      lateMinutes: 10,
      earlyLeave: 0,
      earlyLeaveMinutes: 0,
      averageAttendance: 80,
    ),
    days: const [
      StudentAttendanceReportDay(
        date: '2026-08-27',
        dateLabel: 'Kam, 27 Agu 2026',
        status: 'alfa',
        statusLabel: 'Alfa',
        inferred: true,
        lateMinutes: 0,
        earlyLeaveMinutes: 0,
      ),
    ],
  );

  @override
  Future<AttendanceReportDownload> download(Map<String, dynamic> query) async {
    downloadCalls++;
    return AttendanceReportDownload(
      fileName: 'laporan.xlsx',
      bytes: Uint8List.fromList([1, 2, 3]),
    );
  }
}

final class _FakeSaver implements StudentAttendanceReportDownloadSaver {
  int calls = 0;
  @override
  Future<bool> save(AttendanceReportDownload download) async {
    calls++;
    return true;
  }
}
