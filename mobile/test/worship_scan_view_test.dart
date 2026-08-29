import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_scan/data/worship_scan_remote_data_source.dart';
import 'package:nusa/features/worship_scan/domain/worship_scan.dart';
import 'package:nusa/features/worship_scan/presentation/worship_scan_view.dart';

void main() {
  testWidgets('kamera scan ibadah rapi di layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeWorshipScanRemoteDataSource();

    await _pumpView(tester, remote);

    expect(find.text('Scan Ibadah Siswa'), findsOneWidget);
    expect(find.text('Scan aktif'), findsOneWidget);
    expect(find.byKey(const Key('fake-worship-camera')), findsOneWidget);
    expect(find.text('Presensi Terbaru'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('hasil QR berhasil tampil dan daftar terbaru diperbarui', (
    tester,
  ) async {
    final remote = _FakeWorshipScanRemoteDataSource();
    await _pumpView(tester, remote);

    final camera = find.byKey(const Key('fake-worship-camera'));
    await tester.ensureVisible(camera);
    await tester.tap(camera);
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView), const Offset(0, -320));
    await tester.pumpAndSettle();

    expect(remote.submittedValues, ['0131201150']);
    expect(find.byKey(const Key('worship-scan-result')), findsOneWidget);
    expect(find.text('Siswa Scan Kamera'), findsWidgets);
    expect(find.text('Presensi ibadah berhasil dicatat.'), findsOneWidget);
    expect(find.text('1 siswa hari ini'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('kamera tidak dibuka ketika jadwal belum aktif', (tester) async {
    final remote = _FakeWorshipScanRemoteDataSource(open: false);
    await _pumpView(tester, remote);

    expect(
      find.byKey(const Key('worship-scan-camera-unavailable')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('fake-worship-camera')), findsNothing);
    expect(find.text('Belum dibuka'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  WorshipScanRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        worshipScanRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: WorshipScanView(
          cameraBuilder: (onDetected, processing) => SizedBox(
            height: 240,
            child: Center(
              child: FilledButton(
                key: const Key('fake-worship-camera'),
                onPressed: processing ? null : () => onDetected('0131201150'),
                child: const Text('Pindai kartu uji'),
              ),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeWorshipScanRemoteDataSource
    implements WorshipScanRemoteDataSource {
  _FakeWorshipScanRemoteDataSource({this.open = true});

  final bool open;
  final List<String> submittedValues = [];
  int todayCount = 0;
  final List<WorshipScanAttendance> recent = [];

  @override
  Future<WorshipScanDashboard> fetch({int? scheduleId}) async =>
      WorshipScanDashboard(
        academicYearName: '2026/2027',
        dateLabel: 'Senin, 10 Agustus 2026',
        serverTime: '2026-08-10T12:10:00+07:00',
        scanOpen: open,
        scheduleStatus: WorshipScanScheduleStatus(
          code: open ? 'aktif' : 'belum',
          label: open ? 'Scan aktif' : 'Belum dibuka',
          message: open
              ? 'Kamera siap digunakan untuk presensi siswa.'
              : 'Scan dibuka pukul 11:30.',
        ),
        selectedScheduleId: 1,
        schedules: [
          WorshipScanSchedule(
            id: 1,
            activityId: 1,
            activity: 'Sholat Duhur Berjamaah',
            activityCode: 'sholat_duhur',
            scanStart: '11:30',
            eventTime: '12:00',
            scanEnd: '13:00',
            scanRange: '11:30 - 13:00',
            scanOpen: open,
            notes: 'Mushalla sekolah',
          ),
        ],
        todayCount: todayCount,
        recentAttendances: [...recent],
      );

  @override
  Future<WorshipScanResult> submit({
    required int scheduleId,
    required String rawValue,
  }) async {
    submittedValues.add(rawValue);
    todayCount = 1;
    const attendance = WorshipScanAttendance(
      id: 10,
      studentName: 'Siswa Scan Kamera',
      nisn: '0131201150',
      className: 'VII.A',
      scanTime: '12:10:02',
    );
    recent
      ..removeWhere((item) => item.id == attendance.id)
      ..insert(0, attendance);
    return const WorshipScanResult(
      success: true,
      isNew: true,
      status: 'berhasil',
      message: 'Presensi ibadah berhasil dicatat.',
      absencePeriodCompleted: false,
      serverTime: '12:10:02',
      todayCount: 1,
      attendance: attendance,
      student: WorshipScanStudent(
        name: 'Siswa Scan Kamera',
        nisn: '0131201150',
        className: 'VII.A',
      ),
    );
  }
}
