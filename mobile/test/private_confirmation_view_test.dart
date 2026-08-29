import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/private_confirmation/data/private_confirmation_remote_data_source.dart';
import 'package:nusa/features/private_confirmation/domain/private_confirmation.dart';
import 'package:nusa/features/private_confirmation/presentation/private_confirmation_detail_view.dart';
import 'package:nusa/features/private_confirmation/presentation/private_confirmation_list_view.dart';

void main() {
  test('daftar antrean tidak memuat catatan privat', () {
    final page = PrivateConfirmationPage.fromJson(_pageJson());

    expect(page.privateMode, isTrue);
    expect(page.summary.pending, 1);
    expect(page.items.single.student.name, 'Siswi Konfirmasi Uji');
    expect(page.items.single.dayNumber, 8);
    expect(_pageJson().toString(), isNot(contains('Catatan rahasia detail')));
  });

  testWidgets('antrean privat rapi pada layar Android sempit', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          privateConfirmationRemoteDataSourceProvider.overrideWithValue(
            _FakePrivateConfirmationRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const PrivateConfirmationListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Konfirmasi Privat'), findsOneWidget);
    expect(find.text('RUANG PRIVAT PENDAMPING'), findsOneWidget);
    expect(
      find.byKey(const Key('private-confirmation-class-filter')),
      findsOneWidget,
    );
    await tester.ensureVisible(
      find.byKey(const Key('private-confirmation-item-1')),
    );
    expect(find.text('Siswi Konfirmasi Uji'), findsOneWidget);
    expect(find.text('Catatan rahasia detail'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('pendamping menutup periode melalui konfirmasi privat', (
    tester,
  ) async {
    final remote = _FakePrivateConfirmationRemoteDataSource();
    final router = GoRouter(
      initialLocation: '/detail/1',
      routes: [
        GoRoute(
          path: '/',
          builder: (context, state) => const Scaffold(body: Text('Antrean')),
        ),
        GoRoute(
          path: '/detail/:id',
          builder: (context, state) => PrivateConfirmationDetailView(
            periodId: int.parse(state.pathParameters['id']!),
          ),
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          privateConfirmationRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Catatan rahasia detail'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('private-confirmation-result-finished')),
      280,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(
      find.byKey(const Key('private-confirmation-result-finished')),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Simpan Konfirmasi Privat'),
      220,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Simpan Konfirmasi Privat'));
    await tester.pumpAndSettle();

    expect(find.text('Tutup periode berhalangan?'), findsOneWidget);
    await tester.tap(
      find.byKey(const Key('private-confirmation-confirm-submit')),
    );
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(remote.lastResult, 'selesai');
    expect(remote.lastReminderDays, isNull);
    expect(tester.takeException(), isNull);
  });
}

Map<String, dynamic> _pageJson() => {
  'mode_privat': true,
  'pesan_privasi': 'Lakukan percakapan secara pribadi tanpa pemeriksaan fisik.',
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'ringkasan': {'perlu_konfirmasi': 1, 'dipantau': 2, 'selesai_bulan_ini': 3},
  'filter': {'kelas_id': null, 'cari': ''},
  'referensi': {
    'kelas': [
      {'id': 1, 'nama': 'VII.A'},
    ],
  },
  'items': [
    {
      'id': 1,
      'tanggal_mulai': '2026-08-17',
      'tanggal_mulai_label': '17 Agu 2026',
      'perlu_konfirmasi_sejak': '2026-08-24',
      'hari_ke': 8,
      'jumlah_presensi': 7,
      'siswa': {
        'nama_lengkap': 'Siswi Konfirmasi Uji',
        'nisn': '0131201150',
        'foto_url': null,
      },
      'kelas': {'id': 1, 'nama': 'VII.A'},
    },
  ],
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'per_halaman': 12,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
};

Map<String, dynamic> _detailJson({String status = 'perlu_konfirmasi'}) => {
  'mode_privat': true,
  'pesan_privasi':
      'Percakapan privat, bukan pemeriksaan. Hindari detail medis.',
  'dapat_dikonfirmasi': status == 'perlu_konfirmasi',
  'jeda_awal_hari': 3,
  'periode': {
    'id': 1,
    'status': status,
    'status_label': status == 'selesai' ? 'Selesai' : 'Perlu konfirmasi',
    'tanggal_mulai': '2026-08-17',
    'tanggal_mulai_label': '17 Agu 2026',
    'tanggal_selesai': status == 'selesai' ? '2026-08-24' : null,
    'hari_ke': 8,
    'batas_hari_konfirmasi': 7,
    'perlu_konfirmasi_sejak': '2026-08-24',
    'konfirmasi_berikutnya_pada': null,
    'catatan_privat_awal': 'Catatan rahasia detail',
    'siswa': {
      'nama_lengkap': 'Siswi Konfirmasi Uji',
      'nisn': '0131201150',
      'foto_url': null,
    },
    'kelas': {'id': 1, 'nama': 'VII.A'},
  },
  'presensi_harian': [
    {
      'id': 1,
      'tanggal': '2026-08-24',
      'tanggal_label': '24 Agu 2026',
      'waktu_scan': '12:05:00',
      'kegiatan': 'Sholat Duhur Berjamaah',
    },
  ],
  'riwayat_konfirmasi': const [],
};

final class _FakePrivateConfirmationRemoteDataSource
    implements PrivateConfirmationRemoteDataSource {
  int updateCalls = 0;
  String? lastResult;
  int? lastReminderDays;

  @override
  Future<PrivateConfirmationPage> fetch({
    required String query,
    required int? classId,
    required int page,
  }) async => PrivateConfirmationPage.fromJson(_pageJson());

  @override
  Future<PrivateConfirmationDetail> fetchDetail(int periodId) async =>
      PrivateConfirmationDetail.fromJson(_detailJson());

  @override
  Future<PrivateConfirmationUpdateResult> update({
    required int periodId,
    required String result,
    required int? reminderDays,
    required String? privateNote,
  }) async {
    updateCalls++;
    lastResult = result;
    lastReminderDays = reminderDays;
    return PrivateConfirmationUpdateResult(
      message: 'Konfirmasi privat tersimpan dan periode telah ditutup.',
      detail: PrivateConfirmationDetail.fromJson(
        _detailJson(status: 'selesai'),
      ),
    );
  }
}
