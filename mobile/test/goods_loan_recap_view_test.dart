import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_loan_recap/application/goods_loan_recap_document_service.dart';
import 'package:nusa/features/goods_loan_recap/data/goods_loan_recap_remote_data_source.dart';
import 'package:nusa/features/goods_loan_recap/domain/goods_loan_recap.dart';
import 'package:nusa/features/goods_loan_recap/presentation/goods_loan_recap_view.dart';

void main() {
  test('domain rekap membaca filter, risiko, dan barang belum kembali', () {
    final page = GoodsLoanRecapPage.fromJson(_response());

    expect(page.summary.overdue, 1);
    expect(page.filter.monitoringStatus, 'terlambat');
    expect(page.items.single.items.single.remaining, 2);
    expect(page.overdueReport.count, 1);
    expect(page.monitoringStatuses, hasLength(6));
  });

  test('PDF rekap dibangun dari seluruh transaksi', () async {
    final bytes = await GoodsLoanRecapPdfBuilder().build(
      GoodsLoanRecapPage.fromJson(_response(document: true)),
    );

    expect(bytes, isNotEmpty);
    expect(bytes.take(4), [37, 80, 68, 70]);
  });

  testWidgets(
    'rekap rapi pada layar kecil dan daftar terlambat dapat disalin',
    (tester) async {
      _smallScreen(tester);
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
        SystemChannels.platform,
        (call) async => null,
      );
      addTearDown(
        () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
          SystemChannels.platform,
          null,
        ),
      );
      await tester.pumpWidget(_app(_FakeRemote(), _FakeDocumentService()));
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Rekap Peminjaman'), findsOneWidget);
      expect(find.byKey(const Key('goods-loan-recap-1')), findsOneWidget);
      expect(find.text('Laptop Chromebook · 2 Unit'), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byKey(const Key('copy-overdue-goods-loan')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('goods-loan-overdue-text')), findsOneWidget);
      await tester.tap(find.byKey(const Key('copy-goods-loan-overdue-text')));
      await tester.pumpAndSettle();
      expect(find.text('Daftar berhasil disalin'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('filter pemantauan diterapkan dan PDF lengkap diminta', (
    tester,
  ) async {
    _smallScreen(tester);
    final remote = _FakeRemote();
    final documents = _FakeDocumentService();
    await tester.pumpWidget(_app(remote, documents));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('goods-loan-recap-filter')));
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const Key('goods-loan-recap-monitoring-filter')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Semua transaksi').last);
    await tester.pumpAndSettle();
    await tester.drag(
      find.byKey(const Key('goods-loan-recap-filter-scroll')),
      const Offset(0, -700),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(
      find.byKey(const Key('apply-goods-loan-recap-filter')),
    );
    await tester.tap(find.byKey(const Key('apply-goods-loan-recap-filter')));
    await tester.pumpAndSettle();
    expect(remote.lastFilter?.monitoringStatus, 'semua');

    await tester.tap(find.byKey(const Key('goods-loan-recap-document-menu')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cetak rekap'));
    await tester.pumpAndSettle();
    expect(remote.documentCalls, 1);
    expect(documents.printCalls, 1);
    expect(tester.takeException(), isNull);
  });

  testWidgets('akun baca rekap tidak melihat aksi pengembalian', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(_FakeRemote(canReturn: false), _FakeDocumentService()),
    );
    await tester.pumpAndSettle();

    expect(find.text('Kembalikan'), findsNothing);
    expect(find.text('Detail'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

void _smallScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(320, 640);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Widget _app(
  GoodsLoanRecapRemoteDataSource remote,
  GoodsLoanRecapDocumentService documents,
) => ProviderScope(
  overrides: [
    goodsLoanRecapRemoteDataSourceProvider.overrideWithValue(remote),
    goodsLoanRecapDocumentServiceProvider.overrideWithValue(documents),
  ],
  child: MaterialApp(theme: AppTheme.light, home: const GoodsLoanRecapView()),
);

class _FakeRemote implements GoodsLoanRecapRemoteDataSource {
  _FakeRemote({this.canReturn = true});
  final bool canReturn;
  GoodsLoanRecapFilter? lastFilter;
  int documentCalls = 0;

  @override
  Future<GoodsLoanRecapPage> fetch({
    required GoodsLoanRecapFilter filter,
    required int page,
    int perPage = 15,
  }) async {
    lastFilter = filter;
    final json = _response();
    (json['filter'] as Map<String, dynamic>)
      ..['kata_kunci'] = filter.query
      ..['status_pemantauan'] = filter.monitoringStatus
      ..['jenis_peminjam'] = filter.borrowerType
      ..['peminjam'] = filter.borrower
      ..['barang_id'] = filter.goodsId;
    (json['hak_akses'] as Map<String, dynamic>)['dapat_mengembalikan'] =
        canReturn;
    return GoodsLoanRecapPage.fromJson(json);
  }

  @override
  Future<GoodsLoanRecapPage> document(GoodsLoanRecapFilter filter) async {
    documentCalls++;
    return GoodsLoanRecapPage.fromJson(_response(document: true));
  }
}

class _FakeDocumentService implements GoodsLoanRecapDocumentService {
  int printCalls = 0;
  int shareCalls = 0;

  @override
  Future<bool> printReport(GoodsLoanRecapPage page) async {
    printCalls++;
    return true;
  }

  @override
  Future<bool> shareReport(GoodsLoanRecapPage page) async {
    shareCalls++;
    return true;
  }
}

Map<String, dynamic> _response({bool document = false}) => {
  'ringkasan': {
    'aktif': 2,
    'terlambat': 1,
    'jatuh_tempo': 1,
    'tanpa_rencana': 0,
  },
  'filter': {
    'kata_kunci': '',
    'status_pemantauan': 'terlambat',
    'jenis_peminjam': 'semua',
    'peminjam': '',
    'barang_id': null,
    'tanggal_mulai': null,
    'tanggal_selesai': null,
  },
  'pilihan': {
    'status_pemantauan': [
      {'nilai': 'aktif', 'label': 'Masih dipinjam'},
      {'nilai': 'terlambat', 'label': 'Terlambat dikembalikan'},
      {'nilai': 'jatuh_tempo', 'label': 'Jatuh tempo 7 hari'},
      {'nilai': 'tanpa_rencana', 'label': 'Belum ada rencana kembali'},
      {'nilai': 'selesai', 'label': 'Sudah selesai'},
      {'nilai': 'semua', 'label': 'Semua transaksi'},
    ],
    'jenis_peminjam': [
      {'nilai': 'semua', 'label': 'Semua'},
      {'nilai': 'siswa', 'label': 'Siswa'},
      {'nilai': 'pegawai', 'label': 'Pegawai'},
    ],
    'peminjam': [
      {
        'nilai': 'siswa:1',
        'label': 'Andi Saputra · NISN 0011223344',
        'jenis': 'siswa',
      },
    ],
    'barang': [
      {
        'id': 5,
        'kode': 'AST-2026-0001',
        'nama': 'Laptop Chromebook',
        'label': 'Laptop Chromebook · AST-2026-0001',
      },
    ],
  },
  'hak_akses': {'dapat_mengembalikan': true},
  'items': [_loan()],
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'daftar_terlambat': {
    'jumlah': 1,
    'teks': 'DAFTAR BARANG TERLAMBAT DIKEMBALIKAN\nAndi Saputra\nLaptop Chromebook (2 Unit)',
  },
  'dicetak_pada': document ? '07 September 2026 10:30' : null,
};

Map<String, dynamic> _loan() => {
  'id': 1,
  'nomor': 'PJM-20260901-0001',
  'jenis_peminjam': 'siswa',
  'jenis_peminjam_label': 'Siswa',
  'nama_peminjam': 'Andi Saputra',
  'identitas_peminjam': 'NISN 0011223344',
  'tanggal': '2026-09-01',
  'tanggal_label': '01 Sep 2026',
  'rencana_kembali': '2026-09-04',
  'rencana_kembali_label': '04 Sep 2026',
  'status': 'dipinjam',
  'status_label': 'Dipinjam',
  'pemantauan_label': 'Terlambat 3 hari',
  'terlambat': true,
  'hari_terlambat': 3,
  'jumlah_item': 1,
  'items_belum_kembali': 1,
  'items': [
    {
      'id': 101,
      'barang_id': 5,
      'nama_barang': 'Laptop Chromebook',
      'kode': 'AST-2026-0001',
      'unit_barang_id': null,
      'lokasi': 'Labor Komputer',
      'tipe_pengelolaan': 'stok_dikembalikan',
      'jumlah': 3,
      'jumlah_dikembalikan': 1,
      'jumlah_belum_dikembalikan': 2,
      'wajib_dikembalikan': true,
      'satuan': 'Unit',
      'cara_input': 'manual',
      'catatan': null,
    },
  ],
  'pengembalian': [],
};
