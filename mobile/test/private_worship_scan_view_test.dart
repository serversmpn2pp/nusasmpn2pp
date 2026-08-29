import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/private_worship_scan/data/private_worship_scan_remote_data_source.dart';
import 'package:nusa/features/private_worship_scan/domain/private_worship_scan.dart';
import 'package:nusa/features/private_worship_scan/presentation/private_worship_scan_view.dart';
import 'package:nusa/features/worship_scan/domain/worship_scan.dart';

void main() {
  testWidgets('mode privat rapi dan tidak menampilkan riwayat identitas', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpView(tester, _FakePrivateWorshipScanRemoteDataSource());

    expect(find.text('MODE PRIVAT'), findsOneWidget);
    expect(find.text('Pendamping Ibadah Siswi'), findsOneWidget);
    expect(
      find.byKey(const Key('fake-private-worship-camera')),
      findsOneWidget,
    );
    expect(find.text('Presensi Terbaru'), findsNothing);
    expect(find.text('Siswi Privat Uji'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('identitas hasil scan disembunyikan otomatis', (tester) async {
    final remote = _FakePrivateWorshipScanRemoteDataSource();
    await _pumpView(tester, remote);

    final camera = find.byKey(const Key('fake-private-worship-camera'));
    await tester.ensureVisible(camera);
    await tester.tap(camera);
    await tester.pumpAndSettle();

    expect(remote.submittedValues, ['0131201150']);
    expect(
      find.byKey(const Key('private-worship-scan-result')),
      findsOneWidget,
    );
    expect(find.text('Siswi Privat Uji'), findsOneWidget);
    expect(find.text('Hari ke-1'), findsOneWidget);
    expect(find.text('1 scan'), findsOneWidget);

    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('private-worship-scan-result')), findsNothing);
    expect(find.text('Siswi Privat Uji'), findsNothing);
    expect(
      find.byKey(const Key('fake-private-worship-camera')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('kamera privat tidak dibuka di luar jadwal', (tester) async {
    await _pumpView(
      tester,
      _FakePrivateWorshipScanRemoteDataSource(open: false),
    );

    expect(
      find.byKey(const Key('private-worship-camera-unavailable')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('fake-private-worship-camera')), findsNothing);
    expect(find.text('Belum dibuka'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpView(
  WidgetTester tester,
  PrivateWorshipScanRemoteDataSource remote,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        privateWorshipScanRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(
        theme: AppTheme.light,
        home: PrivateWorshipScanView(
          cameraBuilder: (onDetected, processing) => SizedBox(
            height: 250,
            child: Center(
              child: FilledButton(
                key: const Key('fake-private-worship-camera'),
                onPressed: processing ? null : () => onDetected('0131201150'),
                child: const Text('Pindai kartu privat uji'),
              ),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakePrivateWorshipScanRemoteDataSource
    implements PrivateWorshipScanRemoteDataSource {
  _FakePrivateWorshipScanRemoteDataSource({this.open = true});

  final bool open;
  int todayCount = 0;
  final List<String> submittedValues = [];

  @override
  Future<PrivateWorshipScanDashboard> fetch({int? scheduleId}) async =>
      PrivateWorshipScanDashboard(
        privateMode: true,
        academicYearName: '2026/2027',
        dateLabel: 'Senin, 10 Agustus 2026',
        serverTime: '2026-08-10T12:10:00+07:00',
        scanOpen: open,
        scheduleStatus: WorshipScanScheduleStatus(
          code: open ? 'aktif' : 'belum',
          label: open ? 'Scan privat aktif' : 'Belum dibuka',
          message: open
              ? 'Kamera siap digunakan oleh petugas pendamping.'
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
          ),
        ],
        todayCount: todayCount,
        classScope: const [PrivateWorshipClass(id: 1, name: 'VII.A')],
        confirmationDayLimit: 7,
        settingsActive: true,
        privacyMessage: 'Identitas hasil scan hanya ditampilkan sesaat dan tidak disimpan sebagai riwayat terbuka di perangkat.',
      );

  @override
  Future<PrivateWorshipScanResult> submit({
    required int scheduleId,
    required String rawValue,
  }) async {
    submittedValues.add(rawValue);
    todayCount = 1;
    return const PrivateWorshipScanResult(
      success: true,
      isNew: true,
      status: 'berhasil',
      message: 'Presensi berhalangan berhasil dicatat dan periode dimulai.',
      serverTime: '12:10:02',
      todayCount: 1,
      student: PrivateWorshipScanStudent(
        name: 'Siswi Privat Uji',
        nisn: '0131201150',
        className: 'VII.A',
        dayNumber: 1,
      ),
    );
  }
}
