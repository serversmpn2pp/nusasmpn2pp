import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_recap/data/worship_recap_remote_data_source.dart';
import 'package:nusa/features/worship_recap/domain/worship_recap.dart';
import 'package:nusa/features/worship_recap/presentation/worship_correction_view.dart';
import 'package:nusa/features/worship_recap/presentation/worship_recap_view.dart';

void main() {
  test('domain rekap membaca ringkasan tanpa data privat', () {
    final page = WorshipRecapPage.fromJson(_pageJson(classId: 1));

    expect(page.summary.total, 5);
    expect(page.summary.atSchool, 4);
    expect(page.summary.notAtSchool, 1);
    expect(page.summary.excused, 1);
    expect(page.summary.notRequired, 1);
    expect(page.summary.requiredToPray, 2);
    expect(page.summary.present, 1);
    expect(page.classSummaries.single.percentage, 50);
    expect(page.records.map((record) => record.status), [
      'sudah',
      'belum',
      'berhalangan',
      'tidak_wajib',
      'tidak_hadir',
    ]);
    expect(page.records[2].canBeCorrected, isFalse);
    expect(page.records.last.schoolAttendanceLabel, 'Sakit');
    expect(_pageJson(classId: 1).toString(), isNot(contains('catatan_privat')));
  });

  testWidgets('rekap per kelas rapi pada layar Android sempit', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeWorshipRecapRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          worshipRecapRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const WorshipRecapView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Rekap Ibadah Siswa'), findsOneWidget);
    expect(find.byKey(const Key('worship-recap-date')), findsOneWidget);
    expect(find.byKey(const Key('worship-recap-activity')), findsOneWidget);
    await _dragUntilBuilt(
      tester,
      find.byKey(const Key('worship-recap-class-1')),
    );
    await tester.tap(find.byKey(const Key('worship-recap-class-1')));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('worship-recap-student-1')),
      280,
      scrollable: find.byType(Scrollable).first,
    );

    expect(find.textContaining('Siswa Sudah Ibadah'), findsOneWidget);
    expect(find.text('Kamera HP'), findsOneWidget);
    expect(find.text('Rahasia berhalangan'), findsNothing);
    expect(find.byKey(const Key('worship-recap-correct-1')), findsOneWidget);
    await _dragUntilBuilt(tester, find.textContaining('Siswa Sakit'));
    expect(find.textContaining('Siswa Sakit'), findsOneWidget);
    expect(find.text('Tidak hadir sekolah'), findsOneWidget);
    expect(find.text('Sakit'), findsOneWidget);
    expect(find.byKey(const Key('worship-recap-correct-4')), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('rekap Sholat Jumat menjelaskan siswi tidak wajib', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          worshipRecapRemoteDataSourceProvider.overrideWithValue(
            _FakeWorshipRecapRemoteDataSource(fridayPrayer: true),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const WorshipRecapView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.byKey(const Key('worship-recap-friday-notice')),
      findsOneWidget,
    );
    expect(
      find.textContaining('tidak masuk perhitungan capaian'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('filter rekap menyediakan lima status sesuai desktop', (
    tester,
  ) async {
    final remote = _FakeWorshipRecapRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          worshipRecapRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const WorshipRecapView(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await _dragUntilBuilt(
      tester,
      find.byKey(const Key('worship-recap-class-1')),
    );
    await tester.tap(find.byKey(const Key('worship-recap-class-1')));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('worship-recap-status')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('worship-recap-status')));
    await tester.pumpAndSettle();

    expect(find.text('Sudah salat'), findsWidgets);
    expect(find.text('Belum salat'), findsWidgets);
    expect(find.text('Berhalangan'), findsWidgets);
    expect(find.text('Tidak wajib (pulang)'), findsWidgets);
    expect(find.text('Tidak hadir sekolah'), findsWidgets);
    await tester.tap(find.text('Berhalangan').last);
    await tester.pumpAndSettle();

    expect(remote.lastFetchStatus, 'berhalangan');
    await _dragUntilBuilt(tester, find.textContaining('Siswi Berhalangan'));
    expect(find.byKey(const Key('worship-recap-student-3')), findsOneWidget);
    expect(find.byKey(const Key('worship-recap-correct-3')), findsNothing);
    expect(find.text('Rahasia berhalangan'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('input manual mengirim waktu dan alasan dengan konfirmasi', (
    tester,
  ) async {
    final remote = _FakeWorshipRecapRemoteDataSource();
    const query = WorshipCorrectionQuery(
      memberId: 2,
      date: '2026-08-13',
      activityId: 1,
    );
    final router = GoRouter(
      initialLocation: '/koreksi',
      routes: [
        GoRoute(
          path: '/',
          builder: (context, state) => const Scaffold(body: Text('Rekap')),
        ),
        GoRoute(
          path: '/koreksi',
          builder: (context, state) =>
              const WorshipCorrectionView(query: query),
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          worshipRecapRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Siswa Belum Ibadah'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('worship-correction-reason')),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.enterText(
      find.byKey(const Key('worship-correction-reason')),
      'Siswa lupa kartu dan telah dikonfirmasi oleh guru piket.',
    );
    await tester.scrollUntilVisible(
      find.text('Simpan Perubahan'),
      180,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Simpan Perubahan'));
    await tester.pumpAndSettle();

    expect(find.text('Simpan perubahan?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('worship-correction-confirm')));
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(remote.lastStatus, 'sudah');
    expect(remote.lastTime, '12:00');
    expect(remote.lastReason, contains('guru piket'));
    expect(tester.takeException(), isNull);
  });
}

Future<void> _dragUntilBuilt(WidgetTester tester, Finder finder) async {
  final scrollable = find.byKey(
    const PageStorageKey<String>('worship-recap-scroll'),
  );
  for (var attempt = 0; attempt < 12 && finder.evaluate().isEmpty; attempt++) {
    await tester.drag(scrollable, const Offset(0, -180));
    await tester.pumpAndSettle();
  }
  expect(finder, findsOneWidget);
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
}

Map<String, dynamic> _pageJson({
  int? classId,
  String status = 'semua',
  bool fridayPrayer = false,
}) => {
  'tersedia': true,
  'tanggal': '2026-08-13',
  'tanggal_label': 'Kamis, 13 Agustus 2026',
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'kegiatan_dipilih': {
    'id': 1,
    'nama': fridayPrayer ? 'Sholat Jumat' : 'Sholat Duhur Berjamaah',
    'kode': fridayPrayer ? 'sholat_jumat' : 'sholat_duhur',
    'aktif': true,
  },
  'kelas_dipilih_id': classId,
  'filter': {'status': status, 'cari': ''},
  'referensi': {
    'kegiatan': [
      {'id': 1, 'nama': 'Sholat Duhur Berjamaah', 'aktif': true},
    ],
    'kelas': [
      {'id': 1, 'nama': 'VII.A', 'tingkat': 7, 'jumlah_siswa': 5},
    ],
  },
  'jadwal': {
    'id': 1,
    'aktif': true,
    'jam_pelaksanaan': '12:00',
    'jam_scan_mulai': '11:30',
    'jam_scan_selesai': '13:00',
    'rentang_scan': '11:30 - 13:00',
    'keterangan': null,
  },
  'ringkasan': {
    'total': 5,
    'hadir': 4,
    'tidak_hadir': 1,
    'berhalangan': 1,
    'tidak_wajib': 1,
    'wajib': 2,
    'sudah': 1,
    'belum': 1,
    'persentase': 50,
  },
  'ringkasan_kelas': [
    {
      'kelas': {'id': 1, 'nama': 'VII.A', 'tingkat': 7},
      'total': 5,
      'hadir': 4,
      'tidak_hadir': 1,
      'berhalangan': 1,
      'tidak_wajib': 1,
      'wajib': 2,
      'sudah': 1,
      'belum': 1,
      'persentase': 50,
    },
  ],
  'items': classId == null
      ? const []
      : [
          if (status == 'semua' || status == 'sudah')
            {
              'anggota_kelas_id': 1,
              'nomor_absen': 1,
              'siswa': {
                'id': 1,
                'nama': 'Siswa Sudah Ibadah',
                'nis': '26001',
                'nisn': '0131201150',
                'foto_url': null,
              },
              'kelas': {'id': 1, 'nama': 'VII.A'},
              'status': 'sudah',
              'status_label': 'Sudah salat',
              'status_kehadiran': 'hadir',
              'status_kehadiran_label': 'Hadir di sekolah',
              'presensi': {
                'id': 1,
                'waktu': '12:05',
                'sumber': 'kamera',
                'sumber_label': 'Kamera HP',
                'dicatat_oleh': 'Administrator',
                'dikoreksi_oleh': null,
                'dikoreksi_pada': null,
                'catatan_koreksi': null,
              },
            },
          if (status == 'semua' || status == 'belum')
            {
              'anggota_kelas_id': 2,
              'nomor_absen': 2,
              'siswa': {
                'id': 2,
                'nama': 'Siswa Belum Ibadah',
                'nis': '26002',
                'nisn': '0131201151',
                'foto_url': null,
              },
              'kelas': {'id': 1, 'nama': 'VII.A'},
              'status': 'belum',
              'status_label': 'Belum salat',
              'status_kehadiran': 'hadir',
              'status_kehadiran_label': 'Hadir di sekolah',
              'presensi': null,
            },
          if (status == 'semua' || status == 'berhalangan')
            {
              'anggota_kelas_id': 3,
              'nomor_absen': 3,
              'siswa': {
                'id': 3,
                'nama': 'Siswi Berhalangan',
                'nis': '26003',
                'nisn': '0131201152',
                'foto_url': null,
              },
              'kelas': {'id': 1, 'nama': 'VII.A'},
              'status': 'berhalangan',
              'status_label': 'Berhalangan',
              'status_kehadiran': 'hadir',
              'status_kehadiran_label': 'Hadir di sekolah',
              'presensi': null,
            },
          if (status == 'semua' || status == 'tidak_wajib')
            {
              'anggota_kelas_id': 5,
              'nomor_absen': 5,
              'siswa': {
                'id': 5,
                'nama': 'Siswi Tidak Wajib Jumat',
                'nis': '26005',
                'nisn': '0131201154',
                'foto_url': null,
              },
              'kelas': {'id': 1, 'nama': 'VII.A'},
              'status': 'tidak_wajib',
              'status_label': 'Tidak wajib (pulang)',
              'status_kehadiran': 'hadir',
              'status_kehadiran_label': 'Hadir di sekolah',
              'presensi': null,
            },
          if (status == 'semua' || status == 'tidak_hadir')
            {
              'anggota_kelas_id': 4,
              'nomor_absen': 4,
              'siswa': {
                'id': 4,
                'nama': 'Siswa Sakit',
                'nis': '26004',
                'nisn': '0131201153',
                'foto_url': null,
              },
              'kelas': {'id': 1, 'nama': 'VII.A'},
              'status': 'tidak_hadir',
              'status_label': 'Tidak hadir sekolah',
              'status_kehadiran': 'sakit',
              'status_kehadiran_label': 'Sakit',
              'presensi': null,
            },
        ],
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'per_halaman': 40,
    'total': classId == null
        ? 0
        : status == 'semua'
        ? 5
        : 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'dapat_koreksi': true, 'dapat_scan_sekarang': true},
  'pesan_privasi': 'Rekap umum hanya menampilkan status berhalangan. Catatan privat dan rincian konfirmasi tetap hanya tersedia bagi pendamping yang berwenang.',
};

Map<String, dynamic> _correctionJson({bool present = false}) => {
  'tanggal': '2026-08-13',
  'tanggal_label': 'Kamis, 13 Agustus 2026',
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'kegiatan': {'id': 1, 'nama': 'Sholat Duhur Berjamaah'},
  'jadwal': {
    'id': 1,
    'aktif': true,
    'jam_pelaksanaan': '12:00',
    'jam_scan_mulai': '11:30',
    'jam_scan_selesai': '13:00',
    'rentang_scan': '11:30 - 13:00',
  },
  'dapat_input_baru': true,
  'anggota_kelas': {
    'id': 2,
    'nomor_absen': 2,
    'kelas': {'id': 1, 'nama': 'VII.A'},
    'siswa': {
      'id': 2,
      'nama': 'Siswa Belum Ibadah',
      'nis': '26002',
      'nisn': '0131201151',
      'foto_url': null,
    },
  },
  'presensi': present
      ? {
          'id': 2,
          'waktu': '12:00',
          'sumber': 'manual',
          'sumber_label': 'Input manual',
          'dicatat_oleh': 'Administrator',
          'dikoreksi_oleh': 'Administrator',
          'dikoreksi_pada': '2026-08-13T12:30:00+07:00',
          'catatan_koreksi': 'Siswa lupa kartu.',
        }
      : null,
  'nilai_awal': {'status': present ? 'sudah' : 'belum', 'waktu': '12:00'},
  'riwayat': const [],
};

final class _FakeWorshipRecapRemoteDataSource
    implements WorshipRecapRemoteDataSource {
  _FakeWorshipRecapRemoteDataSource({this.fridayPrayer = false});

  final bool fridayPrayer;
  int updateCalls = 0;
  String? lastFetchStatus;
  String? lastStatus;
  String? lastTime;
  String? lastReason;

  @override
  Future<WorshipRecapPage> fetch({
    required String? date,
    required int? activityId,
    required int? classId,
    required String status,
    required String query,
    required int page,
  }) async {
    lastFetchStatus = status;
    return WorshipRecapPage.fromJson(
      _pageJson(classId: classId, status: status, fridayPrayer: fridayPrayer),
    );
  }

  @override
  Future<WorshipCorrectionDetail> fetchCorrection(
    WorshipCorrectionQuery query,
  ) async => WorshipCorrectionDetail.fromJson(_correctionJson());

  @override
  Future<WorshipCorrectionResult> updateCorrection({
    required WorshipCorrectionQuery query,
    required String status,
    required String? time,
    required String reason,
  }) async {
    updateCalls++;
    lastStatus = status;
    lastTime = time;
    lastReason = reason;
    return WorshipCorrectionResult(
      message: 'Presensi manual/koreksi berhasil disimpan.',
      detail: WorshipCorrectionDetail.fromJson(_correctionJson(present: true)),
    );
  }
}
