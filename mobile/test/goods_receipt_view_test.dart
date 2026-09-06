import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_receipt/data/goods_receipt_remote_data_source.dart';
import 'package:nusa/features/goods_receipt/domain/goods_receipt.dart';
import 'package:nusa/features/goods_receipt/presentation/goods_receipt_view.dart';

void main() {
  test('domain barang datang membaca hasil stok dan Unit Aset', () {
    final page = GoodsReceiptPage.fromJson(_response());
    final detail = GoodsReceipt.fromJson(_detail());

    expect(page.summary.total, 1);
    expect(page.summary.assetUnitsCreated, 2);
    expect(page.items.single.totalValue, 7300000);
    expect(detail.details.first.stockMutationId, 10);
    expect(detail.details.last.assetUnits.length, 2);
    expect(
      detail.details.last.assetUnits.first.goodsUnitCode,
      '02.06.01.05.40.01',
    );
  });

  testWidgets(
    'barang datang rapi pada layar kecil dan dapat mencatat beberapa rincian',
    (tester) async {
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeGoodsReceiptRemoteDataSource();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            goodsReceiptRemoteDataSourceProvider.overrideWithValue(remote),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const GoodsReceiptView(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Barang Datang'), findsOneWidget);
      expect(find.byKey(const Key('goods-receipt-1')), findsOneWidget);
      expect(find.byKey(const Key('add-goods-receipt')), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('goods-receipt-1')));
      await tester.pumpAndSettle();
      expect(find.text('Rincian Barang Datang'), findsOneWidget);
      await tester.drag(
        find.byKey(const Key('goods-receipt-detail-scroll')),
        const Offset(0, -500),
      );
      await tester.pumpAndSettle();
      expect(find.text('2 Unit Aset dibuat'), findsOneWidget);
      await tester.tap(find.byIcon(Icons.close_rounded).last);
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('add-goods-receipt')));
      await tester.pumpAndSettle();
      expect(find.text('Catat Barang Datang'), findsOneWidget);
      await tester.drag(
        find.byKey(const Key('goods-receipt-form-scroll')),
        const Offset(0, -700),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(
        find.byKey(const Key('add-goods-receipt-line')),
      );
      await tester.tap(find.byKey(const Key('add-goods-receipt-line')));
      await tester.pumpAndSettle();
      expect(find.text('Tambah Rincian'), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('goods-receipt-line-quantity')),
        '3',
      );
      await tester.ensureVisible(
        find.byKey(const Key('save-goods-receipt-line')),
      );
      await tester.tap(find.byKey(const Key('save-goods-receipt-line')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('save-goods-receipt')));
      await tester.tap(find.byKey(const Key('save-goods-receipt')));
      await tester.pumpAndSettle();

      expect(remote.created, isNotNull);
      expect(remote.created!.lines.single.quantity, 3);
      expect(remote.created!.storageToken, matches(RegExp(r'^[0-9a-f-]{36}$')));
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('akun baca saja tidak melihat tombol pencatatan', (tester) async {
    final response = _response();
    (response['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = false;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          goodsReceiptRemoteDataSourceProvider.overrideWithValue(
            _ReadOnlyGoodsReceiptRemoteDataSource(response),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const GoodsReceiptView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('add-goods-receipt')), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

class _FakeGoodsReceiptRemoteDataSource
    implements GoodsReceiptRemoteDataSource {
  final List<GoodsReceipt> _items = GoodsReceiptPage.fromJson(_response()).items
      .toList();
  GoodsReceiptFormValue? created;

  @override
  Future<GoodsReceiptPage> fetch({
    required String query,
    required int? sourceId,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  }) async {
    final source = GoodsReceiptPage.fromJson(_response());
    return GoodsReceiptPage(
      items: _items,
      summary: source.summary,
      access: source.access,
      pagination: GoodsReceiptPagination(
        page: 1,
        total: _items.length,
        hasNextPage: false,
      ),
      sources: source.sources,
      goods: source.goods,
      locations: source.locations,
      acquisitionMethods: source.acquisitionMethods,
      conditions: source.conditions,
      query: query,
      sourceId: sourceId,
      startDate: startDate,
      endDate: endDate,
    );
  }

  @override
  Future<({GoodsReceipt receipt, GoodsReceiptAccess access})> detail(
    int id,
  ) async => (
    receipt: GoodsReceipt.fromJson(_detail()),
    access: const GoodsReceiptAccess(canManage: true, canCancel: true),
  );

  @override
  Future<GoodsReceipt> create(GoodsReceiptFormValue value) async {
    created = value;
    final receipt = GoodsReceipt(
      id: 2,
      number: 'BRG-MSK-2026-000002',
      date: value.date,
      dateLabel: '07 Sep 2026',
      source: const GoodsReceiptOption(id: 1, name: 'Dana BOS', code: 'BOS'),
      acquisitionMethod: value.acquisitionMethod,
      acquisitionMethodLabel: 'Pembelian',
      status: 'aktif',
      statusLabel: 'Aktif',
      detailCount: value.lines.length,
      totalValue: 0,
      details: const [],
    );
    _items.add(receipt);
    return receipt;
  }

  @override
  Future<GoodsReceipt> cancel({
    required int id,
    required String reason,
  }) async => GoodsReceipt.fromJson(_detail(cancelled: true));
}

class _ReadOnlyGoodsReceiptRemoteDataSource
    implements GoodsReceiptRemoteDataSource {
  _ReadOnlyGoodsReceiptRemoteDataSource(this.response);
  final Map<String, dynamic> response;

  @override
  Future<GoodsReceiptPage> fetch({
    required String query,
    required int? sourceId,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  }) async => GoodsReceiptPage.fromJson(response);

  @override
  Future<({GoodsReceipt receipt, GoodsReceiptAccess access})> detail(
    int id,
  ) async => (
    receipt: GoodsReceipt.fromJson(_detail()),
    access: const GoodsReceiptAccess(canManage: false),
  );

  @override
  Future<GoodsReceipt> create(GoodsReceiptFormValue value) async =>
      GoodsReceipt.fromJson(_detail());

  @override
  Future<GoodsReceipt> cancel({
    required int id,
    required String reason,
  }) async => GoodsReceipt.fromJson(_detail());
}

Map<String, dynamic> _response() => {
  'ringkasan': {
    'total': 1,
    'hari_ini': 1,
    'unit_aset_dibuat': 2,
    'jenis_stok_masuk': 1,
  },
  'filter': {
    'cari': '',
    'sumber_perolehan_barang_id': null,
    'tanggal_mulai': null,
    'tanggal_selesai': null,
  },
  'hak_akses': {'dapat_kelola': true},
  'pilihan': {
    'sumber_perolehan': [
      {'id': 1, 'nama': 'Dana BOS', 'kode': 'BOS', 'aktif': true},
    ],
    'barang': [
      {
        'id': 1,
        'nama': 'Spidol Hitam',
        'kode': 'BHP-000001',
        'jenis_barang': 'habis_pakai',
        'jenis_label': 'Barang habis pakai',
        'tipe_pengelolaan': 'habis_pakai',
        'satuan': 'Kotak',
      },
      {
        'id': 2,
        'nama': 'Printer Epson',
        'kode': '02.06.01.05.40',
        'jenis_barang': 'tidak_habis_pakai',
        'jenis_label': 'Barang tidak habis pakai',
        'tipe_pengelolaan': 'aset_individual',
        'satuan': 'Unit',
      },
    ],
    'lokasi': [
      {'id': 1, 'nama': 'Gudang Utama', 'kode': 'GUDANG', 'aktif': true},
    ],
    'cara_perolehan': [
      {'nilai': 'pembelian', 'label': 'Pembelian'},
      {'nilai': 'hibah', 'label': 'Hibah/bantuan'},
      {'nilai': 'lainnya', 'label': 'Lainnya'},
    ],
    'kondisi': [
      {'nilai': 'baik', 'label': 'Baik'},
      {'nilai': 'rusak_ringan', 'label': 'Rusak ringan'},
      {'nilai': 'rusak_berat', 'label': 'Rusak berat'},
    ],
  },
  'items': [_detail()..remove('rincian')],
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
};

Map<String, dynamic> _detail({bool cancelled = false}) => {
  'id': 1,
  'nomor': 'BRG-MSK-2026-000001',
  'tanggal': '2026-09-07',
  'tanggal_label': '07 Sep 2026',
  'sumber_perolehan': {
    'id': 1,
    'nama': 'Dana BOS',
    'kode': 'BOS',
    'aktif': true,
  },
  'cara_perolehan': 'pembelian',
  'cara_perolehan_label': 'Pembelian',
  'status': cancelled ? 'dibatalkan' : 'aktif',
  'status_label': cancelled ? 'Dibatalkan' : 'Aktif',
  'nomor_dokumen': 'BAST-001/2026',
  'asal_barang': 'CV Maju Bersama',
  'catatan': 'Diterima dalam keadaan baik.',
  'dibuat_oleh': 'Administrator NUSA',
  'jumlah_rincian': 2,
  'nilai_total': 7300000,
  'alasan_pembatalan': cancelled ? 'Penerimaan tercatat ganda.' : null,
  'dibatalkan_pada_label': cancelled ? '07 Sep 2026 10.00' : null,
  'dibatalkan_oleh': cancelled ? 'Administrator NUSA' : null,
  'rincian': [
    {
      'id': 1,
      'barang': {
        'id': 1,
        'nama': 'Spidol Hitam',
        'kode': 'BHP-000001',
        'jenis_barang': 'habis_pakai',
        'jenis_label': 'Barang habis pakai',
        'satuan': 'Kotak',
      },
      'lokasi': {'id': 1, 'nama': 'Gudang Utama', 'kode': 'GUDANG'},
      'jumlah': 25,
      'harga_satuan': 12000,
      'nilai_subtotal': 300000,
      'mutasi_stok_id': 10,
      'mutasi_pembatalan_id': cancelled ? 11 : null,
      'unit_aset': <dynamic>[],
    },
    {
      'id': 2,
      'barang': {
        'id': 2,
        'nama': 'Printer Epson',
        'kode': '02.06.01.05.40',
        'jenis_barang': 'tidak_habis_pakai',
        'jenis_label': 'Barang tidak habis pakai',
        'satuan': 'Unit',
      },
      'lokasi': {'id': 1, 'nama': 'Gudang Utama', 'kode': 'GUDANG'},
      'jumlah': 2,
      'harga_satuan': 3500000,
      'nilai_subtotal': 7000000,
      'merek': 'Epson',
      'tipe': 'L3110',
      'kondisi': 'baik',
      'kondisi_label': 'Baik',
      'unit_aset': [
        {
          'id': 1,
          'kode_barang_unit': '02.06.01.05.40.01',
          'kode_inventaris': 'AST-2026-000001',
          'nomor_aset_resmi': '12.03.15.08.10.2026.08',
          'aktif': !cancelled,
        },
        {
          'id': 2,
          'kode_barang_unit': '02.06.01.05.40.02',
          'kode_inventaris': 'AST-2026-000002',
          'nomor_aset_resmi': '12.03.15.08.10.2026.08',
          'aktif': !cancelled,
        },
      ],
    },
  ],
};
