import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/stock/data/stock_remote_data_source.dart';
import 'package:nusa/features/stock/domain/stock.dart';
import 'package:nusa/features/stock/presentation/stock_balance_view.dart';
import 'package:nusa/features/stock/presentation/stock_movement_view.dart';

void main() {
  test('domain stok membaca status saldo dan jejak audit mutasi', () {
    final balance = StockBalancePage.fromJson(_balanceResponse());
    final movement = StockMovementPage.fromJson(_movementResponse());

    expect(balance.summary.low, 1);
    expect(balance.items.single.status, 'menipis');
    expect(balance.items.single.goods.unit, 'Kotak');
    expect(movement.summary.inToday, 5);
    expect(movement.items.single.before, 3);
    expect(movement.items.single.after, 8);
    expect(movement.categoriesByType['keluar'], contains('rusak'));
  });

  testWidgets('saldo stok rapi pada layar kecil dan menampilkan status', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          stockRemoteDataSourceProvider.overrideWithValue(
            _FakeStockRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StockBalanceView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Saldo Stok'), findsOneWidget);
    expect(find.byKey(const Key('stock-balance-1')), findsOneWidget);
    expect(find.text('Menipis'), findsWidgets);
    expect(
      find.byKey(const Key('add-stock-movement-from-balance')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('mutasi stok menampilkan audit dan dapat mencatat transaksi', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStockRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [stockRemoteDataSourceProvider.overrideWithValue(remote)],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StockMovementView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Mutasi Stok'), findsOneWidget);
    expect(find.byKey(const Key('stock-movement-1')), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.byKey(const Key('stock-movement-1')));
    await tester.pumpAndSettle();
    expect(find.text('Detail Mutasi Stok'), findsOneWidget);
    expect(find.text('3 → 8 Kotak'), findsWidgets);
    Navigator.of(tester.element(find.text('Detail Mutasi Stok'))).pop();
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('add-stock-movement')));
    await tester.pumpAndSettle();
    expect(find.text('Catat Mutasi Stok'), findsOneWidget);
    await tester.drag(
      find.byKey(const Key('stock-movement-form-scroll')),
      const Offset(0, -520),
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('stock-movement-form-quantity')),
      '2.5',
    );
    await tester.ensureVisible(find.byKey(const Key('save-stock-movement')));
    await tester.tap(find.byKey(const Key('save-stock-movement')));
    await tester.pumpAndSettle();

    expect(remote.created?.goodsId, 1);
    expect(remote.created?.locationId, 1);
    expect(remote.created?.quantity, 2.5);
    expect(remote.created?.category, 'stok_awal');
    expect(tester.takeException(), isNull);
  });

  testWidgets('akun baca stok tidak melihat tombol pencatatan', (tester) async {
    final remote = _FakeStockRemoteDataSource(canManage: false);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [stockRemoteDataSourceProvider.overrideWithValue(remote)],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StockMovementView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('add-stock-movement')), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

class _FakeStockRemoteDataSource implements StockRemoteDataSource {
  _FakeStockRemoteDataSource({this.canManage = true});
  final bool canManage;
  StockMovementFormValue? created;

  @override
  Future<StockBalancePage> fetchBalances({
    required String query,
    required String status,
    required int? categoryId,
    required int? locationId,
    required int page,
    int perPage = 15,
  }) async {
    final response = _balanceResponse();
    (response['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = canManage;
    (response['filter'] as Map<String, dynamic>)
      ..['cari'] = query
      ..['status_stok'] = status
      ..['kategori_barang_id'] = categoryId
      ..['lokasi_barang_id'] = locationId;
    return StockBalancePage.fromJson(response);
  }

  @override
  Future<StockMovementPage> fetchMovements({
    required String query,
    required String type,
    required int? goodsId,
    required int? locationId,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  }) async {
    final response = _movementResponse();
    (response['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = canManage;
    (response['filter'] as Map<String, dynamic>)
      ..['cari'] = query
      ..['jenis_mutasi'] = type
      ..['barang_id'] = goodsId
      ..['lokasi_barang_id'] = locationId;
    return StockMovementPage.fromJson(response);
  }

  @override
  Future<StockMovement> movementDetail(int id) async =>
      StockMovement.fromJson(_movementItem(includeDetail: true));

  @override
  Future<StockMovement> createMovement(StockMovementFormValue value) async {
    created = value;
    return StockMovement.fromJson(_movementItem(includeDetail: true));
  }
}

Map<String, dynamic> _balanceResponse() => {
  'ringkasan': {'baris_saldo': 1, 'lokasi_stok': 1, 'menipis': 1, 'habis': 0},
  'filter': {
    'cari': '',
    'status_stok': 'semua',
    'kategori_barang_id': null,
    'lokasi_barang_id': null,
  },
  'hak_akses': {'dapat_kelola': true},
  'pilihan': {
    'status_stok': [
      {'nilai': 'semua', 'label': 'Semua status'},
      {'nilai': 'aman', 'label': 'Aman'},
      {'nilai': 'menipis', 'label': 'Menipis'},
      {'nilai': 'habis', 'label': 'Habis'},
    ],
    'kategori': [
      {'id': 1, 'nama': 'Alat Tulis', 'kode': 'ATK', 'aktif': true},
    ],
    'lokasi': [
      {'id': 1, 'nama': 'Gudang Utama', 'kode': 'GUDANG', 'aktif': true},
    ],
  },
  'items': [
    {
      'id': 1,
      'barang': {
        'id': 1,
        'nama': 'Spidol Hitam',
        'kode': 'BHP-000001',
        'satuan': 'Kotak',
        'aktif': true,
        'kategori': 'Alat Tulis',
      },
      'lokasi': {
        'id': 1,
        'nama': 'Gudang Utama',
        'kode': 'GUDANG',
        'aktif': true,
      },
      'jumlah': 3,
      'stok_minimum': 5,
      'status': 'menipis',
      'status_label': 'Menipis',
    },
  ],
  'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
};

Map<String, dynamic> _movementResponse() => {
  'ringkasan': {
    'total': 1,
    'hari_ini': 1,
    'masuk_hari_ini': 5,
    'keluar_hari_ini': 0,
  },
  'filter': {
    'cari': '',
    'jenis_mutasi': 'semua',
    'barang_id': null,
    'lokasi_barang_id': null,
    'tanggal_mulai': null,
    'tanggal_selesai': null,
  },
  'hak_akses': {'dapat_kelola': true},
  'pilihan': {
    'jenis_mutasi': [
      {'nilai': 'masuk', 'label': 'Stok masuk'},
      {'nilai': 'keluar', 'label': 'Stok keluar'},
      {'nilai': 'penyesuaian', 'label': 'Penyesuaian stok'},
    ],
    'kategori_mutasi': [
      {'nilai': 'stok_awal', 'label': 'Stok awal'},
      {'nilai': 'pembelian', 'label': 'Pembelian'},
      {'nilai': 'rusak', 'label': 'Barang rusak'},
      {'nilai': 'penyesuaian_fisik', 'label': 'Penyesuaian hasil cek fisik'},
    ],
    'kategori_per_jenis': {
      'masuk': ['stok_awal', 'pembelian'],
      'keluar': ['rusak'],
      'penyesuaian': ['penyesuaian_fisik'],
    },
    'barang': [
      {
        'id': 1,
        'nama': 'Spidol Hitam',
        'kode': 'BHP-000001',
        'satuan': 'Kotak',
        'aktif': true,
      },
    ],
    'lokasi': [
      {'id': 1, 'nama': 'Gudang Utama', 'kode': 'GUDANG', 'aktif': true},
    ],
  },
  'items': [_movementItem()],
  'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
};

Map<String, dynamic> _movementItem({bool includeDetail = false}) => {
  'id': 1,
  'tanggal': '2026-09-07',
  'tanggal_label': '07 Sep 2026',
  'barang': {
    'id': 1,
    'nama': 'Spidol Hitam',
    'kode': 'BHP-000001',
    'satuan': 'Kotak',
    'aktif': true,
    if (includeDetail) 'kategori': 'Alat Tulis',
  },
  'lokasi': {'id': 1, 'nama': 'Gudang Utama', 'kode': 'GUDANG', 'aktif': true},
  'jenis_mutasi': 'masuk',
  'jenis_label': 'Stok masuk',
  'kategori_mutasi': 'stok_awal',
  'kategori_label': 'Stok awal',
  'jumlah_perubahan': 5,
  'saldo_sebelum': 3,
  'saldo_sesudah': 8,
  'referensi': 'STOK-AWAL-01',
  'dibuat_oleh': 'Administrator NUSA',
  if (includeDetail) 'keterangan': 'Hasil opname awal.',
  if (includeDetail) 'dibuat_pada': '2026-09-07T08:00:00+07:00',
};
