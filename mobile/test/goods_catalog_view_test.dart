import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_catalog/data/goods_catalog_remote_data_source.dart';
import 'package:nusa/features/goods_catalog/domain/goods_catalog.dart';
import 'package:nusa/features/goods_catalog/presentation/goods_catalog_detail_view.dart';
import 'package:nusa/features/goods_catalog/presentation/goods_catalog_view.dart';

void main() {
  test('domain katalog membaca ringkasan, filter, lokasi, dan peminjam', () {
    final page = GoodsCatalogPage.fromJson(_page());
    final detail = GoodsCatalogDetail.fromJson(_detail(canRequest: true));

    expect(page.summary.activeGoods, 12);
    expect(page.summary.outOfStock, 2);
    expect(page.items.single.availableLabel, '2 unit');
    expect(page.categories.single.label, 'Elektronik');
    expect(detail.item.locations.single.name, 'Labor Komputer');
    expect(detail.item.units.length, 2);
    expect(detail.item.activeBorrowers.single.name, 'Budi Santoso');
    expect(detail.access.canRequest, isTrue);
  });

  testWidgets('katalog dan filternya rapi pada layar kecil', (tester) async {
    _smallScreen(tester);
    final remote = _FakeGoodsCatalogRemoteDataSource();
    final router = GoRouter(
      initialLocation: '/katalog-barang',
      routes: [
        GoRoute(
          path: '/katalog-barang',
          builder: (_, _) => const GoodsCatalogView(),
        ),
        GoRoute(
          path: '/katalog-barang/:id',
          builder: (_, state) => GoodsCatalogDetailView(
            goodsId: int.parse(state.pathParameters['id']!),
          ),
        ),
      ],
    );
    await tester.pumpWidget(_app(remote, router));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Katalog Barang'), findsOneWidget);
    expect(find.byKey(const Key('goods-catalog-summary')), findsOneWidget);
    expect(find.byKey(const Key('goods-catalog-list')), findsOneWidget);
    expect(find.text('Laptop Chromebook'), findsOneWidget);

    await tester.tap(find.byKey(const Key('goods-catalog-filter')));
    await tester.pumpAndSettle();
    expect(find.text('Filter Katalog'), findsOneWidget);
    expect(
      find.byKey(const Key('goods-catalog-availability-filter')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('detail pegawai membuka formulir dengan barang terpilih', (
    tester,
  ) async {
    _smallScreen(tester);
    final remote = _FakeGoodsCatalogRemoteDataSource(canRequest: true);
    final router = GoRouter(
      initialLocation: '/katalog-barang/10',
      routes: [
        GoRoute(
          path: '/katalog-barang/:id',
          builder: (_, state) => GoodsCatalogDetailView(
            goodsId: int.parse(state.pathParameters['id']!),
          ),
        ),
        GoRoute(
          path: '/pengajuan-saya/tambah',
          builder: (_, state) => Text(
            'barang-${state.uri.queryParameters['barang_id']}',
            textDirection: TextDirection.ltr,
          ),
        ),
      ],
    );
    await tester.pumpWidget(_app(remote, router));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('goods-catalog-detail')), findsOneWidget);
    expect(find.text('Labor Komputer'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('goods-catalog-request')),
      350,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('goods-catalog-request')));
    await tester.pumpAndSettle();

    expect(find.text('barang-10'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('administrator melihat detail tanpa tombol pengajuan', (
    tester,
  ) async {
    _smallScreen(tester);
    final remote = _FakeGoodsCatalogRemoteDataSource();
    final router = GoRouter(
      initialLocation: '/katalog-barang/10',
      routes: [
        GoRoute(
          path: '/katalog-barang/:id',
          builder: (_, state) => GoodsCatalogDetailView(
            goodsId: int.parse(state.pathParameters['id']!),
          ),
        ),
      ],
    );
    await tester.pumpWidget(_app(remote, router));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('goods-catalog-request')), findsNothing);
    await tester.scrollUntilVisible(
      find.text('Budi Santoso'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Budi Santoso'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _FakeGoodsCatalogRemoteDataSource
    implements GoodsCatalogRemoteDataSource {
  _FakeGoodsCatalogRemoteDataSource({this.canRequest = false});
  final bool canRequest;

  @override
  Future<GoodsCatalogPage> fetch({
    required GoodsCatalogFilter filter,
    required int page,
    int perPage = 12,
  }) async => GoodsCatalogPage.fromJson(_page());

  @override
  Future<GoodsCatalogDetail> detail(int id) async =>
      GoodsCatalogDetail.fromJson(_detail(canRequest: canRequest));
}

Widget _app(GoodsCatalogRemoteDataSource remote, GoRouter router) =>
    ProviderScope(
      overrides: [
        goodsCatalogRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
    );

void _smallScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(360, 640);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Map<String, dynamic> _page() => {
  'ringkasan': {
    'barang_aktif': 12,
    'unit_tersedia': 7,
    'unit_dipinjam': 3,
    'stok_tersedia': 4,
    'stok_habis': 2,
  },
  'filter': {
    'kata_kunci': '',
    'kategori_barang_id': null,
    'jenis_barang': 'semua',
    'ketersediaan': 'semua',
  },
  'pilihan': {
    'kategori': [
      {'id': 1, 'label': 'Elektronik'},
    ],
    'jenis_barang': [
      {'nilai': 'semua', 'label': 'Semua jenis'},
      {'nilai': 'tidak_habis_pakai', 'label': 'Tidak habis pakai'},
    ],
    'ketersediaan': [
      {'nilai': 'semua', 'label': 'Semua ketersediaan'},
      {'nilai': 'tersedia', 'label': 'Tersedia'},
    ],
  },
  'hak_akses': {'dapat_mengajukan': false},
  'items': [_item()],
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
};

Map<String, dynamic> _detail({required bool canRequest}) => {
  'barang': {
    ..._item(),
    'deskripsi': 'Perangkat pembelajaran untuk digunakan di kelas.',
    'lokasi': [
      {'lokasi': 'Labor Komputer', 'jumlah': 3, 'tersedia': 2, 'dipinjam': 1},
    ],
    'unit': [
      {
        'id': 101,
        'kode_inventaris': 'CHR-001',
        'nomor_aset_resmi': 'ASET-001',
        'lokasi': 'Labor Komputer',
        'kondisi': 'Baik',
        'status': 'tersedia',
        'status_label': 'Tersedia',
      },
      {
        'id': 102,
        'kode_inventaris': 'CHR-002',
        'nomor_aset_resmi': 'ASET-002',
        'lokasi': 'Labor Komputer',
        'kondisi': 'Baik',
        'status': 'dipinjam',
        'status_label': 'Dipinjam',
      },
    ],
    'peminjam_aktif': [
      {
        'nama': 'Budi Santoso',
        'unit': 'CHR-002',
        'kode_inventaris': 'CHR-002',
        'rencana_kembali': '2026-09-10',
        'rencana_kembali_label': '10 Sep 2026',
      },
    ],
  },
  'hak_akses': {'dapat_mengajukan': canRequest},
};

Map<String, dynamic> _item() => {
  'id': 10,
  'kode': 'BRG-010',
  'nama': 'Laptop Chromebook',
  'kategori': 'Elektronik',
  'jenis_barang': 'tidak_habis_pakai',
  'jenis_barang_label': 'Tidak habis pakai',
  'tipe_pengelolaan': 'aset_individual',
  'tipe_pengelolaan_label': 'Aset individual',
  'jenis_layanan': 'peminjaman',
  'jumlah_tersedia': 2,
  'jumlah_unit': 3,
  'jumlah_dipinjam': 1,
  'satuan': 'unit',
  'tersedia': true,
};
