import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_location/data/inventory_location_remote_data_source.dart';
import 'package:nusa/features/inventory_location/domain/inventory_location.dart';
import 'package:nusa/features/inventory_location/presentation/inventory_location_view.dart';

void main() {
  test(
    'domain lokasi barang membaca jenis, penanggung jawab, dan ringkasan',
    () {
      final page = InventoryLocationPage.fromJson(_response());

      expect(page.summary.total, 2);
      expect(page.summary.withResponsibleEmployee, 1);
      expect(page.access.canManage, isTrue);
      expect(page.types, hasLength(4));
      expect(page.items.first.responsibleEmployee?.name, 'Ibu Ratna');
      expect(page.items.first.typeLabel, 'Gudang');
    },
  );

  testWidgets(
    'lokasi barang rapi di layar kecil dan dapat ditambah secara native',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeInventoryLocationRemoteDataSource();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            inventoryLocationRemoteDataSourceProvider.overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const InventoryLocationView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Lokasi Barang'), findsOneWidget);
      expect(find.text('Gudang Utama'), findsOneWidget);
      expect(find.byKey(const Key('add-inventory-location')), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('add-inventory-location')));
      await tester.pumpAndSettle();
      expect(find.text('Tambah Lokasi Barang'), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('inventory-location-form-name')),
        'Laboratorium Informatika',
      );
      await tester.enterText(
        find.byKey(const Key('inventory-location-form-code')),
        'lab informatika',
      );
      final responsibleField = find.byKey(
        const Key('inventory-location-form-responsible-employee'),
      );
      await tester.ensureVisible(responsibleField);
      await tester.tap(responsibleField);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ibu Ratna · 198001012026092001').last);
      await tester.pumpAndSettle();
      await tester.ensureVisible(
        find.byKey(const Key('save-inventory-location')),
      );
      await tester.tap(find.byKey(const Key('save-inventory-location')));
      await tester.pumpAndSettle();

      expect(remote.created?.name, 'Laboratorium Informatika');
      expect(remote.created?.code, 'lab informatika');
      expect(remote.created?.type, 'gudang');
      expect(remote.created?.responsibleEmployeeId, 10);
      expect(find.text('Tambah Lokasi Barang'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('akun baca saja tidak melihat tombol mutasi', (tester) async {
    final response = _response();
    (response['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = false;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          inventoryLocationRemoteDataSourceProvider.overrideWithValue(
            _ReadOnlyInventoryLocationRemoteDataSource(response),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const InventoryLocationView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('add-inventory-location')), findsNothing);
    expect(find.byKey(const Key('inventory-location-menu-1')), findsNothing);
  });
}

class _FakeInventoryLocationRemoteDataSource
    implements InventoryLocationRemoteDataSource {
  final List<InventoryLocation> _items = [
    const InventoryLocation(
      id: 1,
      name: 'Gudang Utama',
      code: 'GUDANG_UTAMA',
      type: 'gudang',
      typeLabel: 'Gudang',
      responsibleEmployee: InventoryLocationEmployee(
        id: 10,
        name: 'Ibu Ratna',
        nip: '198001012026092001',
      ),
      description: 'Tempat penyimpanan utama.',
      active: true,
      goodsCount: 12,
    ),
    const InventoryLocation(
      id: 2,
      name: 'Kelas Lama',
      code: 'KELAS_LAMA',
      type: 'kelas',
      typeLabel: 'Kelas',
      active: false,
      goodsCount: 2,
    ),
  ];

  InventoryLocationFormValue? created;

  @override
  Future<InventoryLocationPage> fetch({
    required String query,
    required String status,
    required String type,
    required int page,
    int perPage = 15,
  }) async {
    final filtered = _items.where((item) {
      final matchesStatus =
          status == 'semua' || (status == 'aktif' ? item.active : !item.active);
      final matchesType = type == 'semua' || item.type == type;
      final keyword = query.toLowerCase();
      final matchesSearch =
          keyword.isEmpty ||
          item.name.toLowerCase().contains(keyword) ||
          item.code.toLowerCase().contains(keyword);
      return matchesStatus && matchesType && matchesSearch;
    }).toList();
    return InventoryLocationPage(
      items: filtered,
      summary: InventoryLocationSummary(
        total: _items.length,
        active: _items.where((item) => item.active).length,
        withResponsibleEmployee: _items
            .where((item) => item.responsibleEmployee != null)
            .length,
      ),
      access: const InventoryLocationAccess(canManage: true),
      pagination: InventoryLocationPagination(
        page: 1,
        total: filtered.length,
        hasNextPage: false,
      ),
      types: _types,
      employees: _employees,
      query: query,
      status: status,
      type: type,
    );
  }

  @override
  Future<void> create(InventoryLocationFormValue value) async {
    created = value;
    final employee = _employees
        .where((item) => item.id == value.responsibleEmployeeId)
        .firstOrNull;
    _items.add(
      InventoryLocation(
        id: 3,
        name: value.name,
        code: value.code.toUpperCase().replaceAll(' ', '_'),
        type: value.type,
        typeLabel: _types.firstWhere((item) => item.value == value.type).label,
        responsibleEmployee: employee,
        description: value.description,
        active: value.active,
        goodsCount: 0,
      ),
    );
  }

  @override
  Future<void> update({
    required int id,
    required InventoryLocationFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

class _ReadOnlyInventoryLocationRemoteDataSource
    implements InventoryLocationRemoteDataSource {
  _ReadOnlyInventoryLocationRemoteDataSource(this.response);

  final Map<String, dynamic> response;

  @override
  Future<InventoryLocationPage> fetch({
    required String query,
    required String status,
    required String type,
    required int page,
    int perPage = 15,
  }) async => InventoryLocationPage.fromJson(response);

  @override
  Future<void> create(InventoryLocationFormValue value) async {}

  @override
  Future<void> update({
    required int id,
    required InventoryLocationFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

const _types = [
  InventoryLocationType(value: 'gudang', label: 'Gudang'),
  InventoryLocationType(value: 'ruangan', label: 'Ruangan'),
  InventoryLocationType(value: 'kelas', label: 'Kelas'),
  InventoryLocationType(value: 'lainnya', label: 'Lainnya'),
];

const _employees = [
  InventoryLocationEmployee(
    id: 10,
    name: 'Ibu Ratna',
    nip: '198001012026092001',
  ),
];

Map<String, dynamic> _response() => {
  'ringkasan': {'total': 2, 'aktif': 1, 'dengan_penanggung_jawab': 1},
  'filter': {'cari': '', 'status': 'semua', 'jenis': 'semua'},
  'hak_akses': {'dapat_kelola': true},
  'pilihan': {
    'jenis': [
      {'nilai': 'gudang', 'label': 'Gudang'},
      {'nilai': 'ruangan', 'label': 'Ruangan'},
      {'nilai': 'kelas', 'label': 'Kelas'},
      {'nilai': 'lainnya', 'label': 'Lainnya'},
    ],
    'pegawai': [
      {'id': 10, 'nama': 'Ibu Ratna', 'nip': '198001012026092001'},
    ],
  },
  'items': [
    {
      'id': 1,
      'nama': 'Gudang Utama',
      'kode': 'GUDANG_UTAMA',
      'jenis': 'gudang',
      'label_jenis': 'Gudang',
      'penanggung_jawab': {
        'id': 10,
        'nama': 'Ibu Ratna',
        'nip': '198001012026092001',
      },
      'deskripsi': 'Tempat penyimpanan utama.',
      'aktif': true,
      'jumlah_barang': 12,
    },
    {
      'id': 2,
      'nama': 'Kelas Lama',
      'kode': 'KELAS_LAMA',
      'jenis': 'kelas',
      'label_jenis': 'Kelas',
      'penanggung_jawab': null,
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
