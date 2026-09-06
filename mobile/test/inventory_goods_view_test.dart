import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_goods/data/inventory_goods_remote_data_source.dart';
import 'package:nusa/features/inventory_goods/domain/inventory_goods.dart';
import 'package:nusa/features/inventory_goods/presentation/inventory_goods_view.dart';

void main() {
  test('domain inventaris membaca ringkasan relasi dan kuantitas', () {
    final page = InventoryGoodsPage.fromJson(_response());

    expect(page.summary.total, 2);
    expect(page.summary.nonConsumable, 1);
    expect(page.items.first.category.name, 'Elektronik');
    expect(page.items.first.assetUnitsCount, 12);
    expect(page.items.last.stockBalance, 8);
    expect(page.access.canManage, isTrue);
  });

  testWidgets(
    'inventaris rapi di layar kecil dan barang dapat ditambah secara native',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeInventoryGoodsRemoteDataSource();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            inventoryGoodsRemoteDataSourceProvider.overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const InventoryGoodsView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Inventaris Barang'), findsOneWidget);
      expect(find.byKey(const Key('inventory-goods-1')), findsOneWidget);
      expect(find.byKey(const Key('add-inventory-goods')), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('inventory-goods-1')));
      await tester.pumpAndSettle();
      expect(find.text('Detail Inventaris Barang'), findsOneWidget);
      expect(find.text('12 unit aset'), findsWidgets);
      await tester.tap(find.byTooltip('Tutup').last);
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('add-inventory-goods')));
      await tester.pumpAndSettle();
      expect(find.text('Tambah Inventaris Barang'), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('inventory-goods-form-name')),
        'Proyektor',
      );
      await tester.enterText(
        find.byKey(const Key('inventory-goods-form-code')),
        '0206010541',
      );
      await tester.ensureVisible(find.byKey(const Key('save-inventory-goods')));
      await tester.tap(find.byKey(const Key('save-inventory-goods')));
      await tester.pumpAndSettle();

      expect(remote.created?.name, 'Proyektor');
      expect(remote.created?.code, '02.06.01.05.41');
      expect(find.text('Proyektor'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('akun baca saja tidak melihat tombol pengelolaan', (
    tester,
  ) async {
    final response = _response();
    (response['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = false;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          inventoryGoodsRemoteDataSourceProvider.overrideWithValue(
            _ReadOnlyInventoryGoodsRemoteDataSource(response),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const InventoryGoodsView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('add-inventory-goods')), findsNothing);
    expect(find.byKey(const Key('inventory-goods-menu-1')), findsNothing);
  });
}

class _FakeInventoryGoodsRemoteDataSource
    implements InventoryGoodsRemoteDataSource {
  final List<InventoryGoods> _items = InventoryGoodsPage.fromJson(_response())
      .items
      .toList();

  InventoryGoodsFormValue? created;

  @override
  Future<InventoryGoodsPage> fetch({
    required String query,
    required String status,
    required String type,
    required int? categoryId,
    required int page,
    int perPage = 15,
  }) async {
    final source = InventoryGoodsPage.fromJson(_response());
    final filtered = _items.where((item) {
      final keyword = query.toLowerCase();
      return (keyword.isEmpty ||
              item.name.toLowerCase().contains(keyword) ||
              item.code.toLowerCase().contains(keyword)) &&
          (status == 'semua' ||
              (status == 'aktif' ? item.active : !item.active)) &&
          (type == 'semua' || item.type == type) &&
          (categoryId == null || item.category.id == categoryId);
    }).toList();
    return InventoryGoodsPage(
      items: filtered,
      summary: InventoryGoodsSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        nonConsumable: _items.where((item) => !item.isConsumable).length,
        consumable: _items.where((item) => item.isConsumable).length,
      ),
      access: source.access,
      pagination: InventoryGoodsPagination(
        page: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      types: source.types,
      categories: source.categories,
      units: source.units,
      locations: source.locations,
      query: query,
      status: status,
      type: type,
      categoryId: categoryId,
    );
  }

  @override
  Future<void> create(InventoryGoodsFormValue value) async {
    created = value;
    _items.add(
      InventoryGoods(
        id: 3,
        code: value.code ?? 'BHP-000002',
        name: value.name,
        category: const InventoryGoodsOption(
          id: 1,
          name: 'Elektronik',
          code: 'ELEKTRONIK',
        ),
        unit: const InventoryGoodsOption(id: 1, name: 'Unit', code: 'UNIT'),
        location: const InventoryGoodsOption(
          id: 1,
          name: 'Gudang Utama',
          code: 'GUDANG',
        ),
        type: value.type,
        typeLabel: value.type == 'habis_pakai'
            ? 'Barang habis pakai'
            : 'Barang tidak habis pakai',
        managementType: value.type == 'habis_pakai'
            ? 'habis_pakai'
            : 'aset_individual',
        managementTypeLabel: value.type == 'habis_pakai'
            ? 'Barang habis pakai'
            : 'Aset individual',
        minimumStock: value.minimumStock,
        stockBalance: 0,
        assetUnitsCount: 0,
        quantitySummary: value.type == 'habis_pakai' ? '0 Unit' : '0 unit aset',
        active: value.active,
        typeCanChange: true,
        description: value.description,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required InventoryGoodsFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

class _ReadOnlyInventoryGoodsRemoteDataSource
    implements InventoryGoodsRemoteDataSource {
  _ReadOnlyInventoryGoodsRemoteDataSource(this.response);

  final Map<String, dynamic> response;

  @override
  Future<InventoryGoodsPage> fetch({
    required String query,
    required String status,
    required String type,
    required int? categoryId,
    required int page,
    int perPage = 15,
  }) async => InventoryGoodsPage.fromJson(response);

  @override
  Future<void> create(InventoryGoodsFormValue value) async {}

  @override
  Future<void> update({
    required int id,
    required InventoryGoodsFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

Map<String, dynamic> _response() => {
  'ringkasan': {
    'total': 2,
    'aktif': 2,
    'tidak_habis_pakai': 1,
    'habis_pakai': 1,
  },
  'filter': {
    'cari': '',
    'status': 'semua',
    'jenis_barang': 'semua',
    'kategori_barang_id': null,
  },
  'hak_akses': {'dapat_kelola': true},
  'pilihan': {
    'jenis_barang': [
      {'nilai': 'habis_pakai', 'label': 'Barang habis pakai'},
      {'nilai': 'tidak_habis_pakai', 'label': 'Barang tidak habis pakai'},
    ],
    'kategori': [
      {'id': 1, 'nama': 'Elektronik', 'kode': 'ELEKTRONIK', 'aktif': true},
    ],
    'satuan': [
      {'id': 1, 'nama': 'Unit', 'kode': 'UNIT', 'aktif': true},
    ],
    'lokasi': [
      {'id': 1, 'nama': 'Gudang Utama', 'kode': 'GUDANG', 'aktif': true},
    ],
  },
  'items': [
    {
      'id': 1,
      'kode': '02.06.01.05.40',
      'nama': 'Laptop Chromebook',
      'kategori': {'id': 1, 'nama': 'Elektronik', 'kode': 'ELEKTRONIK'},
      'satuan': {'id': 1, 'nama': 'Unit', 'kode': 'UNIT'},
      'lokasi_penyimpanan': {'id': 1, 'nama': 'Gudang Utama', 'kode': 'GUDANG'},
      'jenis_barang': 'tidak_habis_pakai',
      'label_jenis_barang': 'Barang tidak habis pakai',
      'tipe_pengelolaan': 'aset_individual',
      'label_tipe_pengelolaan': 'Aset individual',
      'stok_minimum': 0,
      'saldo_stok': 0,
      'jumlah_unit_aset': 12,
      'ringkasan_kuantitas': '12 unit aset',
      'deskripsi': 'Perangkat pembelajaran.',
      'aktif': true,
      'jenis_dapat_diubah': false,
    },
    {
      'id': 2,
      'kode': 'BHP-000001',
      'nama': 'Kertas A4',
      'kategori': {'id': 1, 'nama': 'Elektronik', 'kode': 'ELEKTRONIK'},
      'satuan': {'id': 1, 'nama': 'Unit', 'kode': 'UNIT'},
      'lokasi_penyimpanan': null,
      'jenis_barang': 'habis_pakai',
      'label_jenis_barang': 'Barang habis pakai',
      'tipe_pengelolaan': 'habis_pakai',
      'label_tipe_pengelolaan': 'Barang habis pakai',
      'stok_minimum': 10,
      'saldo_stok': 8,
      'jumlah_unit_aset': 0,
      'ringkasan_kuantitas': '8 Unit',
      'deskripsi': null,
      'aktif': true,
      'jenis_dapat_diubah': false,
    },
  ],
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'per_halaman': 15,
    'total': 2,
    'ada_halaman_berikutnya': false,
  },
};
