import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_recap/data/student_attendance_recap_remote_data_source.dart';
import 'package:nusa/features/student_attendance_recap/domain/student_attendance_recap.dart';
import 'package:nusa/features/student_attendance_recap/presentation/student_attendance_recap_view.dart';
import 'package:nusa/features/student_attendance_recap/presentation/widgets/student_attendance_recap_sheets.dart';

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
        'dapat_pesan_whatsapp': true,
        'koreksi_hari_ini_terbatas': false,
        'cakupan_wali_kelas': false,
      },
    });

    expect(page.summary.notScanned, 1);
    expect(page.items.single.source, 'scan');
    expect(page.items.single.checkInDisplay, '07:10 · Scanner');
    expect(page.items.single.checkOutDisplay, '-');
    expect(page.items.single.lateMinutes, 10);
    expect(page.items.single.correction.allowed, isTrue);
    expect(page.canCopyWhatsApp, isTrue);
  });

  test('does not infer scanner from the legacy daily source', () {
    final record = _recordJson();
    (record['presensi'] as Map<String, dynamic>).remove('sumber_masuk_label');
    final parsed = StudentAttendanceRecord.fromJson(record);

    expect(parsed.checkInDisplay, '07:10 · Sumber belum diketahui');
    expect(parsed.checkOutDisplay, '-');
  });

  testWidgets('rekap dan koreksi presensi rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          SystemChannels.platform,
          (call) async => null,
        );
    addTearDown(
      () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(SystemChannels.platform, null),
    );
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

    await tester.tap(find.byKey(const Key('attendance-whatsapp-button')));
    await tester.pumpAndSettle();
    expect(remote.whatsAppCalls, 1);
    expect(find.text('Pesan WA Grup Orang Tua'), findsOneWidget);
    expect(
      find.byKey(const Key('attendance-whatsapp-message')),
      findsOneWidget,
    );
    await tester.tap(find.byKey(const Key('attendance-whatsapp-copy')));
    await tester.pumpAndSettle();
    expect(find.text('Pesan Berhasil Disalin'), findsOneWidget);
    await tester.tap(find.byIcon(Icons.close_rounded).last);
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    await tester.drag(find.byType(CustomScrollView), const Offset(0, -420));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('attendance-record-1')));
    await tester.tap(find.byKey(const Key('attendance-record-1')));
    await tester.pumpAndSettle();
    expect(find.text('07:10 · Scanner'), findsOneWidget);
    await tester.ensureVisible(find.text('Koreksi Presensi'));
    await tester.tap(find.text('Koreksi Presensi'));
    await tester.pumpAndSettle();

    expect(
      find.byKey(const Key('attendance-correction-status')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('attendance-correction-manual-reason')),
      findsNothing,
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

  testWidgets('rekap dan rincian native menampilkan sumber per jam dan audit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentAttendanceRecapRemoteDataSourceProvider.overrideWithValue(
            _FakeStudentAttendanceRecapRemoteDataSource(mixedSources: true),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentAttendanceRecapView(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -420));
    await tester.pumpAndSettle();

    final card = find.byKey(const Key('attendance-record-1'));
    expect(find.text('Masuk 06:22 · Input manual'), findsOneWidget);
    expect(find.text('Pulang 11:25 · Scanner'), findsOneWidget);
    await tester.ensureVisible(card);
    await tester.tap(card);
    await tester.pumpAndSettle();

    expect(find.text('06:22 · Input manual'), findsOneWidget);
    expect(find.text('11:25 · Scanner'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('28 Agu 2026, 09:48:21 WIB'),
      100,
      scrollable: find.descendant(
        of: find.byType(DraggableScrollableSheet),
        matching: find.byType(Scrollable),
      ),
    );
    expect(find.text('Koreksi manual · Petugas Presensi Uji'), findsOneWidget);
    expect(find.text('28 Agu 2026, 09:48:21 WIB'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('verifikator native mengirim hasil dan memakai jam bukti scan', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final item = _recordJson();
    (item['presensi'] as Map<String, dynamic>)['perlu_verifikasi_scan'] = true;
    final detail = StudentAttendanceDetail.fromJson({
      'item': item,
      'jam_bukti_scan_masuk': '06:22:03',
      'dapat_memverifikasi_scan': true,
    });
    AttendanceCorrectionValue? submitted;
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: Builder(
            builder: (context) => TextButton(
              onPressed: () async {
                submitted =
                    await showModalBottomSheet<AttendanceCorrectionValue>(
                      context: context,
                      isScrollControlled: true,
                      builder: (_) =>
                          StudentAttendanceCorrectionSheet(detail: detail),
                    );
              },
              child: const Text('Buka'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Buka'));
    await tester.pumpAndSettle();
    expect(find.text('Verifikasi Scan Masuk'), findsOneWidget);
    await tester.tap(find.byKey(const Key('attendance-scan-review-result')));
    await tester.pumpAndSettle();
    expect(find.text('Terkonfirmasi kartu hilang'), findsNothing);
    await tester.tap(find.text('Scan masuk ditemukan').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('attendance-correction-notes')),
      'Log sesuai dengan jam datang.',
    );
    await tester.ensureVisible(find.text('Simpan Hasil Verifikasi'));
    await tester.tap(find.text('Simpan Hasil Verifikasi'));
    await tester.pumpAndSettle();
    expect(submitted?.scanReviewResult, 'scan_ditemukan');
    expect(submitted?.checkInTime, '06:22:03');
    expect(submitted?.manualReason, isNull);
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
    'sumber_masuk_label': 'Scanner',
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
  _FakeStudentAttendanceRecapRemoteDataSource({this.mixedSources = false});

  final bool mixedSources;
  int correctCalls = 0;
  int whatsAppCalls = 0;
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
      if (mixedSources) ...{
        'jam_masuk': '06:22',
        'sumber_masuk_label': 'Input manual',
        'jam_pulang': '11:25',
        'sumber_pulang_label': 'Scanner',
        'ada_scan_masuk': false,
        'belum_pulang': false,
        'menit_terlambat': 5,
      },
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
    canCopyWhatsApp: true,
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
    history: mixedSources
        ? [
            AttendanceHistoryEntry.fromJson({
              'id': 1,
              'status_sesudah': 'hadir',
              'sumber_label': 'Koreksi manual',
              'dibuat_oleh': 'Petugas Presensi Uji',
              'dibuat_pada': '2026-08-28T09:48:21+07:00',
              'dibuat_pada_label': '28 Agu 2026, 09:48:21 WIB',
            }),
          ]
        : const [],
    correction: const AttendanceCorrectionAccess(
      allowed: true,
      todayOnly: false,
    ),
  );

  @override
  Future<StudentAttendanceWhatsAppMessage> whatsAppMessage({
    required String date,
    int? academicYearId,
    int? classId,
  }) async {
    whatsAppCalls++;
    return StudentAttendanceWhatsAppMessage(
      date: date,
      dateLabel: '28 Agustus 2026',
      scope: 'Kelas VII.A',
      studentCount: 1,
      message: '*REKAP KEHADIRAN SISWA*\nSMP Negeri 2 Padang Panjang\nCakupan: Kelas VII.A',
    );
  }

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
