import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_acquisition_source/data/inventory_acquisition_source_remote_data_source.dart';
import 'package:nusa/features/inventory_acquisition_source/domain/inventory_acquisition_source.dart';
import 'package:nusa/features/inventory_acquisition_source/presentation/inventory_acquisition_source_view.dart';

void main() {
  test('domain sumber perolehan membaca ringkasan dan hak akses', () {
    final page = InventoryAcquisitionSourcePage.fromJson(_response());

    expect(page.summary.total, 2);
    expect(page.summary.active, 1);
    expect(page.access.canManage, isTrue);
    expect(page.items.first.assetUnitsCount, 12);
    expect(page.pagination.hasNextPage, isFalse);
  });

  testWidgets(
    'sumber perolehan rapi di layar kecil dan dapat ditambah secara native',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeInventoryAcquisitionSourceRemoteDataSource();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            inventoryAcquisitionSourceRemoteDataSourceProvider
                .overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const InventoryAcquisitionSourceView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Sumber Perolehan'), findsOneWidget);
      expect(
        find.byKey(const Key('inventory-acquisition-source-1')),
        findsOneWidget,
      );
      expect(
        find.byKey(const Key('add-inventory-acquisition-source')),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);

      await tester.tap(
        find.byKey(const Key('add-inventory-acquisition-source')),
      );
      await tester.pumpAndSettle();
      expect(find.text('Tambah Sumber Perolehan'), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('inventory-acquisition-source-form-name')),
        'BOS Daerah',
      );
      await tester.enterText(
        find.byKey(const Key('inventory-acquisition-source-form-code')),
        'bos daerah',
      );
      await tester.ensureVisible(
        find.byKey(const Key('save-inventory-acquisition-source')),
      );
      await tester.tap(
        find.byKey(const Key('save-inventory-acquisition-source')),
      );
      await tester.pumpAndSettle();

      expect(remote.created?.name, 'BOS Daerah');
      expect(remote.created?.code, 'bos daerah');
      expect(find.text('BOS Daerah'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('akun baca saja tidak melihat tombol mutasi', (tester) async {
    final response = _response();
    (response['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = false;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          inventoryAcquisitionSourceRemoteDataSourceProvider.overrideWithValue(
            _ReadOnlyInventoryAcquisitionSourceRemoteDataSource(response),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const InventoryAcquisitionSourceView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.byKey(const Key('add-inventory-acquisition-source')),
      findsNothing,
    );
    expect(
      find.byKey(const Key('inventory-acquisition-source-menu-1')),
      findsNothing,
    );
  });
}

class _FakeInventoryAcquisitionSourceRemoteDataSource
    implements InventoryAcquisitionSourceRemoteDataSource {
  final List<InventoryAcquisitionSource> _items = [
    const InventoryAcquisitionSource(
      id: 1,
      name: 'DAK',
      code: 'DAK',
      description: 'Dana Alokasi Khusus.',
      active: true,
      assetUnitsCount: 12,
    ),
    const InventoryAcquisitionSource(
      id: 2,
      name: 'Hibah Lama',
      code: 'HIBAH_LAMA',
      active: false,
      assetUnitsCount: 2,
    ),
  ];

  InventoryAcquisitionSourceFormValue? created;

  @override
  Future<InventoryAcquisitionSourcePage> fetch({
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
    return InventoryAcquisitionSourcePage(
      items: filtered,
      summary: InventoryAcquisitionSourceSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        inactive: _items.where((item) => !item.active).length,
      ),
      access: const InventoryAcquisitionSourceAccess(canManage: true),
      pagination: InventoryAcquisitionSourcePagination(
        page: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      query: query,
      status: status,
    );
  }

  @override
  Future<void> create(InventoryAcquisitionSourceFormValue value) async {
    created = value;
    _items.add(
      InventoryAcquisitionSource(
        id: 3,
        name: value.name,
        code: value.code.toUpperCase().replaceAll(' ', '_'),
        description: value.description,
        active: value.active,
        assetUnitsCount: 0,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required InventoryAcquisitionSourceFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

class _ReadOnlyInventoryAcquisitionSourceRemoteDataSource
    implements InventoryAcquisitionSourceRemoteDataSource {
  _ReadOnlyInventoryAcquisitionSourceRemoteDataSource(this.response);

  final Map<String, dynamic> response;

  @override
  Future<InventoryAcquisitionSourcePage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async => InventoryAcquisitionSourcePage.fromJson(response);

  @override
  Future<void> create(InventoryAcquisitionSourceFormValue value) async {}

  @override
  Future<void> update({
    required int id,
    required InventoryAcquisitionSourceFormValue value,
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
      'nama': 'DAK',
      'kode': 'DAK',
      'deskripsi': 'Dana Alokasi Khusus.',
      'aktif': true,
      'jumlah_unit_aset': 12,
    },
    {
      'id': 2,
      'nama': 'Hibah Lama',
      'kode': 'HIBAH_LAMA',
      'aktif': false,
      'jumlah_unit_aset': 2,
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
