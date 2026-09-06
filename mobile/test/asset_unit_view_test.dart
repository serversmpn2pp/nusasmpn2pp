import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/asset_unit/data/asset_unit_remote_data_source.dart';
import 'package:nusa/features/asset_unit/domain/asset_unit.dart';
import 'package:nusa/features/asset_unit/presentation/asset_unit_view.dart';

void main() {
  test('domain unit aset membaca identitas, peminjaman, dan riwayat', () {
    final page = AssetUnitPage.fromJson(_response());

    expect(page.summary.total, 1);
    expect(page.items.single.inventoryCode, 'AST-2026-000001');
    expect(page.items.single.officialAssetNumber, '12.03.15.08.10.2026.08');
    expect(page.items.single.activeLoan?.borrower, 'Antonius');
    expect(page.items.single.history.single.type, 'pencatatan');
    expect(page.assetNumber.preview(2027), '12.03.15.08.10.2027.08');
  });

  testWidgets(
    'unit aset rapi di layar kecil serta detail dan tambah berfungsi',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeAssetUnitRemoteDataSource();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            assetUnitRemoteDataSourceProvider.overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const AssetUnitView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Unit Aset'), findsOneWidget);
      expect(find.byKey(const Key('asset-unit-1')), findsOneWidget);
      expect(find.byKey(const Key('add-asset-unit')), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('asset-unit-1')));
      await tester.pumpAndSettle();
      expect(find.text('Detail Unit Aset'), findsOneWidget);
      expect(find.textContaining('Antonius'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Riwayat Aset'),
        320,
        scrollable: find.byType(Scrollable).last,
      );
      expect(find.text('Riwayat Aset'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.tap(find.byTooltip('Tutup').last);
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('add-asset-unit')));
      await tester.pumpAndSettle();
      expect(find.text('Tambah Unit Aset'), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('asset-unit-form-quantity')),
        '2',
      );
      tester.testTextInput.hide();
      await tester.pumpAndSettle();
    await tester.drag(
      find.byKey(const Key('asset-unit-form-scroll')),
      const Offset(0, -260),
    );
    await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('asset-unit-form-brand')),
        'Epson',
      );
      await tester.tap(find.byKey(const Key('save-asset-unit')));
      await tester.pumpAndSettle();

      expect(remote.created?.goodsId, 1);
      expect(remote.created?.quantity, 2);
      expect(remote.created?.brand, 'Epson');
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('akun baca saja tidak melihat aksi pengelolaan unit aset', (
    tester,
  ) async {
    final response = _response();
    (response['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = false;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          assetUnitRemoteDataSourceProvider.overrideWithValue(
            _ReadOnlyAssetUnitRemoteDataSource(response),
          ),
        ],
        child: MaterialApp(theme: AppTheme.light, home: const AssetUnitView()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('add-asset-unit')), findsNothing);
    expect(find.byKey(const Key('asset-unit-menu-1')), findsNothing);
  });
}

class _FakeAssetUnitRemoteDataSource implements AssetUnitRemoteDataSource {
  AssetUnitFormValue? created;

  @override
  Future<AssetUnitPage> fetch({
    required String query,
    required String dataStatus,
    required String condition,
    required String unitStatus,
    required int? goodsId,
    required int? locationId,
    required int page,
    int perPage = 15,
  }) async => AssetUnitPage.fromJson(_response());

  @override
  Future<AssetUnit> detail(int id) async =>
      AssetUnitPage.fromJson(_response()).items.single;

  @override
  Future<void> create(AssetUnitFormValue value) async {
    created = value;
  }

  @override
  Future<void> update({
    required int id,
    required AssetUnitFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

class _ReadOnlyAssetUnitRemoteDataSource implements AssetUnitRemoteDataSource {
  _ReadOnlyAssetUnitRemoteDataSource(this.response);

  final Map<String, dynamic> response;

  @override
  Future<AssetUnitPage> fetch({
    required String query,
    required String dataStatus,
    required String condition,
    required String unitStatus,
    required int? goodsId,
    required int? locationId,
    required int page,
    int perPage = 15,
  }) async => AssetUnitPage.fromJson(response);

  @override
  Future<AssetUnit> detail(int id) async =>
      AssetUnitPage.fromJson(response).items.single;

  @override
  Future<void> create(AssetUnitFormValue value) async {}

  @override
  Future<void> update({
    required int id,
    required AssetUnitFormValue value,
  }) async {}

  @override
  Future<void> deactivate(int id) async {}
}

Map<String, dynamic> _response() => {
  'ringkasan': {'total': 1, 'aktif': 1, 'tersedia': 0, 'perlu_perhatian': 0},
  'filter': {
    'cari': '',
    'status': 'semua',
    'kondisi': 'semua',
    'status_unit': 'semua',
    'barang_id': null,
    'lokasi_barang_id': null,
  },
  'hak_akses': {'dapat_kelola': true},
  'pilihan': {
    'barang': [
      {
        'id': 1,
        'nama': 'Printer Epson',
        'kode': '02.06.01.05.40',
        'kategori': 'Elektronik',
        'satuan': 'Unit',
        'aktif': true,
      },
    ],
    'lokasi': [
      {'id': 1, 'nama': 'Labor Komputer', 'kode': 'LAB', 'aktif': true},
    ],
    'sumber_perolehan': [
      {'id': 1, 'nama': 'Dana BOS', 'kode': 'BOS', 'aktif': true},
    ],
    'kondisi': [
      {'nilai': 'baik', 'label': 'Baik'},
      {'nilai': 'rusak_ringan', 'label': 'Rusak ringan'},
      {'nilai': 'rusak_berat', 'label': 'Rusak berat'},
    ],
    'status_unit': [
      {'nilai': 'tersedia', 'label': 'Tersedia'},
      {'nilai': 'dipinjam', 'label': 'Dipinjam'},
      {'nilai': 'dalam_perbaikan', 'label': 'Dalam perbaikan'},
      {'nilai': 'hilang', 'label': 'Hilang'},
      {'nilai': 'dihapuskan', 'label': 'Dihapuskan'},
    ],
    'nomor_aset': {
      'awalan': '12.03.15.08.10',
      'akhiran': '08',
      'contoh': '12.03.15.08.10.2026.08',
    },
  },
  'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
  'items': [
    {
      'id': 1,
      'barang': {
        'id': 1,
        'nama': 'Printer Epson',
        'kode': '02.06.01.05.40',
        'kategori': 'Elektronik',
        'satuan': 'Unit',
        'aktif': true,
      },
      'nomor_unit': 1,
      'kode_barang_unit': '02.06.01.05.40.01',
      'kode_inventaris': 'AST-2026-000001',
      'nomor_aset_resmi': '12.03.15.08.10.2026.08',
      'lokasi': {
        'id': 1,
        'nama': 'Labor Komputer',
        'kode': 'LAB',
        'aktif': true,
      },
      'nomor_seri': 'SN-001',
      'merek': 'Epson',
      'tipe': 'L3110',
      'kondisi': 'baik',
      'label_kondisi': 'Baik',
      'status_unit': 'dipinjam',
      'label_status_unit': 'Dipinjam',
      'tanggal_perolehan': '2026-07-15',
      'tahun_perolehan': 2026,
      'sumber_perolehan': {
        'id': 1,
        'nama': 'Dana BOS',
        'kode': 'BOS',
        'aktif': true,
      },
      'sumber_perolehan_lama': null,
      'harga_perolehan': 4500000,
      'keterangan': 'Printer tata usaha.',
      'aktif': true,
      'peminjaman_aktif': {
        'nomor': 'PJM-2026-0001',
        'peminjam': 'Antonius',
        'identitas': '19870101',
        'rencana_kembali': '2026-09-10',
        'pemantauan': 'Tepat waktu',
      },
      'riwayat': [
        {
          'jenis': 'pencatatan',
          'label': 'Pencatatan',
          'judul': 'Unit dicatat',
          'keterangan': 'Unit masuk inventaris NUSA.',
          'tanggal': '2026-07-15',
        },
      ],
    },
  ],
};
