import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_unit/data/inventory_unit_remote_data_source.dart';
import 'package:nusa/features/inventory_unit/domain/inventory_unit.dart';
import 'package:nusa/features/inventory_unit/presentation/inventory_unit_view.dart';

void main() {
  test('domain satuan barang membaca ringkasan dan hak akses', () {
    final page = InventoryUnitPage.fromJson(_response());

    expect(page.summary.total, 2);
    expect(page.summary.active, 1);
    expect(page.access.canManage, isTrue);
    expect(page.items.first.goodsCount, 12);
    expect(page.pagination.hasNextPage, isFalse);
  });

  testWidgets(
    'satuan barang rapi di layar kecil dan dapat ditambah secara native',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeInventoryUnitRemoteDataSource();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            inventoryUnitRemoteDataSourceProvider.overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const InventoryUnitView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Satuan Barang'), findsOneWidget);
      expect(find.text('Unit'), findsOneWidget);
      expect(find.byKey(const Key('add-inventory-unit')), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('add-inventory-unit')));
      await tester.pumpAndSettle();
      expect(find.text('Tambah Satuan Barang'), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('inventory-unit-form-name')),
        'Buah',
      );
      await tester.enterText(
        find.byKey(const Key('inventory-unit-form-code')),
        'buah barang',
      );
      await tester.ensureVisible(find.byKey(const Key('save-inventory-unit')));
      await tester.tap(find.byKey(const Key('save-inventory-unit')));
      await tester.pumpAndSettle();

      expect(remote.created?.name, 'Buah');
      expect(remote.created?.code, 'buah barang');
      expect(find.text('Buah'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('akun baca saja tidak melihat tombol mutasi', (tester) async {
    final response = _response();
    (response['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = false;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          inventoryUnitRemoteDataSourceProvider.overrideWithValue(
            _ReadOnlyInventoryUnitRemoteDataSource(response),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const InventoryUnitView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('add-inventory-unit')), findsNothing);
    expect(find.byKey(const Key('inventory-unit-menu-1')), findsNothing);
  });
}

class _FakeInventoryUnitRemoteDataSource
    implements InventoryUnitRemoteDataSource {
  final List<InventoryUnit> _items = [
    const InventoryUnit(
      id: 1,
      name: 'Unit',
      code: 'UNIT',
      description: 'Satuan per barang.',
      active: true,
      goodsCount: 12,
    ),
    const InventoryUnit(
      id: 2,
      name: 'Satuan Lama',
      code: 'SATUAN_LAMA',
      active: false,
      goodsCount: 2,
    ),
  ];

  InventoryUnitFormValue? created;

  @override
  Future<InventoryUnitPage> fetch({
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
    return InventoryUnitPage(
      items: filtered,
      summary: InventoryUnitSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      access: const InventoryUnitAccess(canManage: true),
      pagination: InventoryUnitPagination(
        page: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
    );
  }

  @override
  Future<void> create(InventoryUnitFormValue value) async {
    created = value;
    _items.add(
      InventoryUnit(
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
    required InventoryUnitFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

class _ReadOnlyInventoryUnitRemoteDataSource
    implements InventoryUnitRemoteDataSource {
  _ReadOnlyInventoryUnitRemoteDataSource(this.response);

  final Map<String, dynamic> response;

  @override
  Future<InventoryUnitPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async => InventoryUnitPage.fromJson(response);

  @override
  Future<void> create(InventoryUnitFormValue value) async {}

  @override
  Future<void> update({
    required int id,
    required InventoryUnitFormValue value,
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
      'nama': 'Unit',
      'kode': 'UNIT',
      'deskripsi': 'Satuan per barang.',
      'aktif': true,
      'jumlah_barang': 12,
    },
    {
      'id': 2,
      'nama': 'Satuan Lama',
      'kode': 'SATUAN_LAMA',
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
