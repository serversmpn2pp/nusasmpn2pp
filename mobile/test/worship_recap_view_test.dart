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

    expect(page.summary.total, 2);
    expect(page.summary.present, 1);
    expect(page.classSummaries.single.percentage, 50);
    expect(page.records.last.present, isFalse);
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
    await tester.scrollUntilVisible(
      find.byKey(const Key('worship-recap-class-1')),
      260,
      scrollable: find.byType(Scrollable).first,
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

Map<String, dynamic> _pageJson({int? classId}) => {
  'tersedia': true,
  'tanggal': '2026-08-13',
  'tanggal_label': 'Kamis, 13 Agustus 2026',
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'kegiatan_dipilih': {
    'id': 1,
    'nama': 'Sholat Duhur Berjamaah',
    'kode': 'sholat_duhur',
    'aktif': true,
  },
  'kelas_dipilih_id': classId,
  'filter': {'status': 'semua', 'cari': ''},
  'referensi': {
    'kegiatan': [
      {'id': 1, 'nama': 'Sholat Duhur Berjamaah', 'aktif': true},
    ],
    'kelas': [
      {'id': 1, 'nama': 'VII.A', 'tingkat': 7, 'jumlah_siswa': 2},
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
  'ringkasan': {'total': 2, 'sudah': 1, 'belum': 1, 'persentase': 50},
  'ringkasan_kelas': [
    {
      'kelas': {'id': 1, 'nama': 'VII.A', 'tingkat': 7},
      'total': 2,
      'sudah': 1,
      'belum': 1,
      'persentase': 50,
    },
  ],
  'items': classId == null
      ? const []
      : [
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
            'status_label': 'Sudah presensi',
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
            'status_label': 'Belum presensi',
            'presensi': null,
          },
        ],
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'per_halaman': 40,
    'total': classId == null ? 0 : 2,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'dapat_koreksi': true, 'dapat_scan_sekarang': true},
  'pesan_privasi': 'Status berhalangan tidak ditampilkan pada rekap umum dan tetap dikelola melalui ruang privat pendamping.',
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
  int updateCalls = 0;
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
  }) async => WorshipRecapPage.fromJson(_pageJson(classId: classId));

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
