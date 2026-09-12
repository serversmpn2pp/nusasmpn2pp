import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/worship_absence_recap/application/worship_absence_recap_document_service.dart';
import 'package:nusa/features/worship_absence_recap/data/worship_absence_recap_remote_data_source.dart';
import 'package:nusa/features/worship_absence_recap/domain/worship_absence_recap.dart';
import 'package:nusa/features/worship_absence_recap/presentation/worship_absence_recap_view.dart';

void main() {
  test('domain rekap tidak memiliki bidang catatan privat', () {
    final page = WorshipAbsenceRecapPage.fromJson(_pageJson());

    expect(page.privateMode, isTrue);
    expect(page.summary.periods, 3);
    expect(page.items.single.student.name, 'Siswi Rekap Privat');
    expect(
      page.items.single.lastConfirmation?.resultLabel,
      'Masih berhalangan',
    );
    expect(_pageJson().toString(), isNot(contains('catatan_privat')));
  });

  testWidgets('rekap privat responsif dan membuka riwayat konfirmasi', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeWorshipAbsenceRecapRemoteDataSource();
    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          builder: (context, state) => const WorshipAbsenceRecapView(),
        ),
        GoRoute(
          path: '/konfirmasi-berhalangan-ibadah/:id',
          builder: (context, state) => Scaffold(
            body: Text('Riwayat periode ${state.pathParameters['id']}'),
          ),
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          worshipAbsenceRecapRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('REKAP PRIVAT PENDAMPING'), findsOneWidget);
    expect(find.text('RAHASIA'), findsNothing);
    expect(
      find.byKey(const Key('worship-absence-recap-filter')),
      findsOneWidget,
    );
    await tester.drag(find.byType(ListView).first, const Offset(0, -620));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('Buka riwayat privat'));
    await tester.pumpAndSettle();
    expect(find.text('Riwayat periode 17'), findsOneWidget);
  });

  testWidgets('pencarian ditunda dan filter tetap selebar lembar', (
    tester,
  ) async {
    final remote = _FakeWorshipAbsenceRecapRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          worshipAbsenceRecapRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const WorshipAbsenceRecapView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const Key('worship-absence-recap-search')),
      'Alya',
    );
    await tester.pump(const Duration(milliseconds: 699));
    expect(remote.queries, ['']);
    await tester.pump(const Duration(milliseconds: 1));
    await tester.pumpAndSettle();
    expect(remote.queries.last, 'Alya');

    await tester.tap(find.byKey(const Key('worship-absence-recap-filter')));
    await tester.pumpAndSettle();
    expect(
      find.byKey(const Key('worship-absence-recap-class-filter')),
      findsOneWidget,
    );
    expect(
      find.byKey(const Key('worship-absence-recap-status-filter')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  test('dokumen PDF rekap privat dapat dibangun', () async {
    final page = WorshipAbsenceRecapPage.fromJson(_pageJson());
    final bytes = await WorshipAbsenceRecapPdfBuilder().build(page);

    expect(bytes.length, greaterThan(1000));
  });
}

Map<String, dynamic> _pageJson() => {
  'mode_privat': true,
  'pesan_privasi':
      'Dokumen internal dan privat. Catatan percakapan tidak ditampilkan.',
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'bulan': '2026-08',
  'bulan_label': 'Agustus 2026',
  'bulan_minimum': '2026-07',
  'bulan_maksimum': '2026-08',
  'tanggal_cetak': '20 Agustus 2026 14:00',
  'filter': {'kelas_id': null, 'status': 'semua', 'cari': ''},
  'referensi': {
    'kelas': [
      {'id': 1, 'nama': 'VII.A'},
    ],
    'status': [
      {'kode': 'semua', 'label': 'Semua status'},
      {'kode': 'aktif', 'label': 'Sedang dipantau'},
      {'kode': 'perlu_konfirmasi', 'label': 'Perlu konfirmasi'},
      {'kode': 'selesai', 'label': 'Selesai'},
    ],
  },
  'ringkasan': {
    'periode': 3,
    'siswi': 2,
    'aktif': 1,
    'perlu_konfirmasi': 1,
    'selesai': 1,
  },
  'items': [
    {
      'id': 17,
      'siswa': {
        'id': 11,
        'nama': 'Siswi Rekap Privat',
        'nisn': '0131201150',
        'foto_url': null,
      },
      'kelas': {'id': 1, 'nama': 'VII.A'},
      'tanggal_mulai': '2026-08-01',
      'tanggal_mulai_label': '01 Agt 2026',
      'tanggal_selesai': null,
      'tanggal_selesai_label': null,
      'durasi_hari': 20,
      'presensi_bulan': 8,
      'konfirmasi_bulan': 2,
      'konfirmasi_terakhir': {
        'tanggal': '2026-08-15T10:00:00+07:00',
        'tanggal_label': '15 Agt 2026',
        'hasil': 'masih_berhalangan',
        'hasil_label': 'Masih berhalangan',
        'oleh': 'Guru Pendamping',
      },
      'status': 'perlu_konfirmasi',
      'status_label': 'Perlu konfirmasi',
      'cara_selesai': null,
      'cara_selesai_label': null,
    },
  ],
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
};

final class _FakeWorshipAbsenceRecapRemoteDataSource
    implements WorshipAbsenceRecapRemoteDataSource {
  final List<String> queries = [];

  @override
  Future<WorshipAbsenceRecapPage> fetch({
    required String? month,
    required int? classId,
    required String status,
    required String query,
    required int page,
    bool exportAll = false,
  }) async {
    queries.add(query);
    return WorshipAbsenceRecapPage.fromJson(_pageJson());
  }
}
