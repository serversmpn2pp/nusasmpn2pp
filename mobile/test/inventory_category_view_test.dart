import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_category/data/inventory_category_remote_data_source.dart';
import 'package:nusa/features/inventory_category/domain/inventory_category.dart';
import 'package:nusa/features/inventory_category/presentation/inventory_category_view.dart';

void main() {
  test('domain kategori barang membaca ringkasan dan hak akses', () {
    final page = InventoryCategoryPage.fromJson(_response());

    expect(page.summary.total, 2);
    expect(page.summary.active, 1);
    expect(page.access.canManage, isTrue);
    expect(page.items.first.goodsCount, 12);
    expect(page.pagination.hasNextPage, isFalse);
  });

  testWidgets(
    'kategori barang rapi di layar kecil dan dapat ditambah secara native',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeInventoryCategoryRemoteDataSource();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            inventoryCategoryRemoteDataSourceProvider.overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const InventoryCategoryView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Kategori Barang'), findsOneWidget);
      expect(find.text('Elektronik'), findsOneWidget);
      expect(find.byKey(const Key('add-inventory-category')), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('add-inventory-category')));
      await tester.pumpAndSettle();
      expect(find.text('Tambah Kategori Barang'), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('inventory-category-form-name')),
        'Alat Tulis Kantor',
      );
      await tester.enterText(
        find.byKey(const Key('inventory-category-form-code')),
        'atk sekolah',
      );
      await tester.ensureVisible(
        find.byKey(const Key('save-inventory-category')),
      );
      await tester.tap(find.byKey(const Key('save-inventory-category')));
      await tester.pumpAndSettle();

      expect(remote.created?.name, 'Alat Tulis Kantor');
      expect(remote.created?.code, 'atk sekolah');
      expect(find.text('Alat Tulis Kantor'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('akun baca saja tidak melihat tombol mutasi', (tester) async {
    final response = _response();
    (response['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = false;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          inventoryCategoryRemoteDataSourceProvider.overrideWithValue(
            _ReadOnlyInventoryCategoryRemoteDataSource(response),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const InventoryCategoryView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('add-inventory-category')), findsNothing);
    expect(find.byKey(const Key('inventory-category-menu-1')), findsNothing);
  });
}

class _FakeInventoryCategoryRemoteDataSource
    implements InventoryCategoryRemoteDataSource {
  final List<InventoryCategory> _items = [
    const InventoryCategory(
      id: 1,
      name: 'Elektronik',
      code: 'ELEKTRONIK',
      description: 'Peralatan elektronik sekolah.',
      active: true,
      goodsCount: 12,
    ),
    const InventoryCategory(
      id: 2,
      name: 'Kategori Lama',
      code: 'KATEGORI_LAMA',
      active: false,
      goodsCount: 2,
    ),
  ];

  InventoryCategoryFormValue? created;

  @override
  Future<InventoryCategoryPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    final filtered = _items.where((item) {
      final matchesStatus =
          status == 'semua' || (status == 'aktif' ? item.active : !item.active);
      final keyword = query.toLowerCase();
      final matchesSearch =
          keyword.isEmpty ||
          item.name.toLowerCase().contains(keyword) ||
          item.code.toLowerCase().contains(keyword);
      return matchesStatus && matchesSearch;
    }).toList();
    return InventoryCategoryPage(
      items: filtered,
      summary: InventoryCategorySummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      access: const InventoryCategoryAccess(canManage: true),
      pagination: InventoryCategoryPagination(
        page: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
    );
  }

  @override
  Future<void> create(InventoryCategoryFormValue value) async {
    created = value;
    _items.add(
      InventoryCategory(
        id: 3,
        name: value.name,
        code: value.code.toUpperCase().replaceAll(' ', '_'),
        description: value.description,
        active: value.active,
        goodsCount: 0,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required InventoryCategoryFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

class _ReadOnlyInventoryCategoryRemoteDataSource
    implements InventoryCategoryRemoteDataSource {
  _ReadOnlyInventoryCategoryRemoteDataSource(this.response);

  final Map<String, dynamic> response;

  @override
  Future<InventoryCategoryPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async => InventoryCategoryPage.fromJson(response);

  @override
  Future<void> create(InventoryCategoryFormValue value) async {}

  @override
  Future<void> update({
    required int id,
    required InventoryCategoryFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

Map<String, dynamic> _response() => {
  'ringkasan': {'total': 2, 'aktif': 1, 'nonaktif': 1},
  'filter': {'cari': '', 'status': 'semua'},
  'hak_akses': {'dapat_kelola': true},
  'items': [
    {
      'id': 1,
      'nama': 'Elektronik',
      'kode': 'ELEKTRONIK',
      'deskripsi': 'Peralatan elektronik sekolah.',
      'aktif': true,
      'jumlah_barang': 12,
    },
    {
      'id': 2,
      'nama': 'Kategori Lama',
      'kode': 'KATEGORI_LAMA',
      'aktif': false,
      'jumlah_barang': 2,
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
