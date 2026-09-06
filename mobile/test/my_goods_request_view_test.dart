import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_goods_request/data/my_goods_request_remote_data_source.dart';
import 'package:nusa/features/my_goods_request/domain/my_goods_request.dart';
import 'package:nusa/features/my_goods_request/presentation/my_goods_request_create_view.dart';
import 'package:nusa/features/my_goods_request/presentation/my_goods_request_detail_view.dart';
import 'package:nusa/features/my_goods_request/presentation/my_goods_request_view.dart';

void main() {
  test('domain pengajuan saya membaca katalog, ringkasan, dan hak batal', () {
    final page = MyGoodsRequestPage.fromJson(_page());
    final catalog = MyGoodsCatalogPage.fromJson(_catalog());
    final detail = MyGoodsRequestDetail.fromJson(_detail());

    expect(page.summary.pending, 1);
    expect(page.items.single.status, 'menunggu');
    expect(catalog.items.first.availableQuantity, 2);
    expect(catalog.items.first.mustReturn, isTrue);
    expect(detail.canCancel, isTrue);
  });

  testWidgets('riwayat pengajuan saya rapi di layar kecil', (tester) async {
    _smallScreen(tester);
    await tester.pumpWidget(
      _app(_FakeMyGoodsRequestRemoteDataSource(), const MyGoodsRequestView()),
    );
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Pengajuan Saya'), findsOneWidget);
    expect(find.byKey(const Key('my-goods-request-list')), findsOneWidget);
    expect(find.byKey(const Key('my-goods-request-1')), findsOneWidget);
    expect(find.text('Menunggu petugas'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('pegawai memilih katalog dan mengirim peminjaman aset', (
    tester,
  ) async {
    _smallScreen(tester);
    final remote = _FakeMyGoodsRequestRemoteDataSource();
    final router = GoRouter(
      initialLocation: '/pengajuan-saya/tambah',
      routes: [
        GoRoute(
          path: '/pengajuan-saya/tambah',
          builder: (_, _) => const MyGoodsRequestCreateView(),
        ),
        GoRoute(
          path: '/pengajuan-saya/:id',
          builder: (_, state) => Text(
            'detail-${state.pathParameters['id']}',
            textDirection: TextDirection.ltr,
          ),
        ),
      ],
    );
    await tester.pumpWidget(_routerApp(remote, router));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('my-goods-catalog-list')), findsOneWidget);
    await tester.tap(find.byKey(const Key('my-goods-catalog-10')));
    await tester.pumpAndSettle();
    expect(find.text('Buat Pengajuan'), findsOneWidget);
    expect(
      find.byKey(const Key('my-goods-request-return-date')),
      findsOneWidget,
    );

    await tester.enterText(
      find.byKey(const Key('my-goods-request-purpose')),
      'Pembelajaran Informatika kelas VIII.A.',
    );
    tester.testTextInput.hide();
    await tester.pumpAndSettle();
    await tester.ensureVisible(
      find.byKey(const Key('submit-my-goods-request')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('submit-my-goods-request')));
    await tester.pumpAndSettle();

    expect(remote.created?.goodsId, 10);
    expect(remote.created?.quantity, 1);
    expect(remote.created?.plannedReturn, isNotNull);
    expect(find.text('detail-1'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('pengajuan menunggu dapat dibatalkan dengan konfirmasi', (
    tester,
  ) async {
    _smallScreen(tester);
    final remote = _FakeMyGoodsRequestRemoteDataSource();
    await tester.pumpWidget(
      _app(remote, const MyGoodsRequestDetailView(requestId: 1)),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('cancel-my-goods-request')));
    await tester.pumpAndSettle();
    expect(find.text('Batalkan pengajuan?'), findsOneWidget);
    await tester.tap(find.text('Ya, Batalkan'));
    await tester.pumpAndSettle();

    expect(remote.cancelledId, 1);
    expect(find.text('Dibatalkan'), findsWidgets);
    expect(find.byKey(const Key('cancel-my-goods-request')), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

void _smallScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(320, 640);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Widget _app(MyGoodsRequestRemoteDataSource remote, Widget home) =>
    ProviderScope(
      overrides: [
        myGoodsRequestRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp(theme: AppTheme.light, home: home),
    );

Widget _routerApp(MyGoodsRequestRemoteDataSource remote, GoRouter router) =>
    ProviderScope(
      overrides: [
        myGoodsRequestRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
    );

class _FakeMyGoodsRequestRemoteDataSource
    implements MyGoodsRequestRemoteDataSource {
  MyGoodsRequestFormValue? created;
  int? cancelledId;

  @override
  Future<MyGoodsRequestPage> fetch({
    required String status,
    required int page,
    int perPage = 12,
  }) async => MyGoodsRequestPage.fromJson(_page());

  @override
  Future<MyGoodsCatalogPage> catalog({
    required String query,
    required int page,
    int perPage = 20,
  }) async => MyGoodsCatalogPage.fromJson(_catalog());

  @override
  Future<MyGoodsRequestDetail> detail(int id) async =>
      MyGoodsRequestDetail.fromJson(_detail());

  @override
  Future<MyGoodsRequestDetail> create(MyGoodsRequestFormValue value) async {
    created = value;
    return MyGoodsRequestDetail.fromJson(_detail());
  }

  @override
  Future<MyGoodsRequestDetail> cancel(int id) async {
    cancelledId = id;
    return MyGoodsRequestDetail.fromJson(
      _detail(status: 'dibatalkan', statusLabel: 'Dibatalkan'),
    );
  }
}

Map<String, dynamic> _page() => {
  'ringkasan': {'semua': 1, 'menunggu': 1, 'dipenuhi': 0, 'selesai': 0},
  'filter': {'status': 'semua'},
  'pilihan': {
    'status': [
      {'nilai': 'semua', 'label': 'Semua status'},
      {'nilai': 'menunggu', 'label': 'Menunggu petugas'},
      {'nilai': 'dipenuhi', 'label': 'Dipenuhi'},
      {'nilai': 'ditolak', 'label': 'Ditolak'},
      {'nilai': 'dibatalkan', 'label': 'Dibatalkan'},
    ],
  },
  'items': [_request()],
  'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
};

Map<String, dynamic> _catalog() => {
  'filter': {'kata_kunci': ''},
  'items': [
    {
      'id': 10,
      'kode': 'AST-LAPTOP',
      'nama': 'Laptop Chromebook',
      'kategori': 'Peralatan TIK',
      'jenis_barang': 'tidak_habis_pakai',
      'jenis_barang_label': 'Tidak habis pakai',
      'tipe_pengelolaan': 'aset_individual',
      'jenis_layanan': 'peminjaman',
      'jenis_layanan_label': 'Peminjaman aset',
      'jumlah_tersedia': 2,
      'satuan': 'unit',
      'tersedia': true,
    },
    {
      'id': 11,
      'kode': 'BHP-SPIDOL',
      'nama': 'Spidol Papan Tulis',
      'kategori': 'ATK',
      'jenis_barang': 'habis_pakai',
      'jenis_barang_label': 'Habis pakai',
      'tipe_pengelolaan': 'habis_pakai',
      'jenis_layanan': 'permintaan',
      'jenis_layanan_label': 'Permintaan barang habis pakai',
      'jumlah_tersedia': 0,
      'satuan': 'Buah',
      'tersedia': false,
    },
  ],
  'paginasi': {'halaman': 1, 'total': 2, 'ada_halaman_berikutnya': false},
};

Map<String, dynamic> _detail({
  String status = 'menunggu',
  String statusLabel = 'Menunggu petugas',
}) => {
  'pengajuan': {
    ..._request(),
    'status': status,
    'status_label': statusLabel,
    'kategori_barang': 'Peralatan TIK',
    'tujuan': 'Pembelajaran Informatika kelas VIII.A.',
    'catatan_petugas': status == 'dibatalkan' ? null : 'Belum ada catatan.',
    'peminjaman_barang_id': null,
    'nomor_peminjaman': null,
  },
  'hak_akses': {'dapat_membatalkan': status == 'menunggu'},
};

Map<String, dynamic> _request() => {
  'id': 1,
  'nomor': 'PGJ-20260907-000001',
  'barang_id': 10,
  'kode_barang': 'AST-LAPTOP',
  'nama_barang': 'Laptop Chromebook',
  'jenis': 'peminjaman',
  'jenis_label': 'Peminjaman aset',
  'jumlah': 1,
  'satuan': 'unit',
  'tanggal_pengajuan_label': '07 Sep 2026',
  'tanggal_dibutuhkan_label': '08 Sep 2026',
  'rencana_kembali_label': '14 Sep 2026',
  'status': 'menunggu',
  'status_label': 'Menunggu petugas',
};
