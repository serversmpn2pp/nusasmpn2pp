import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_loan/data/goods_loan_remote_data_source.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';
import 'package:nusa/features/goods_loan/presentation/goods_loan_detail_view.dart';
import 'package:nusa/features/goods_loan/presentation/goods_loan_view.dart';
import 'package:nusa/features/goods_loan/presentation/goods_return_view.dart';

void main() {
  test('domain peminjaman membaca barang dan ringkasan pengembalian', () {
    final page = GoodsLoanPage.fromJson(_loanPage());
    final returns = GoodsReturnPage.fromJson(_returnPage());

    expect(page.summary.active, 1);
    expect(page.items.single.items.single.remaining, 1);
    expect(page.availableItems.single.mustReturn, isTrue);
    expect(returns.summary.overdue, 1);
    expect(returns.items.single.overdue, isTrue);
  });

  testWidgets('peminjaman rapi di layar kecil dan transaksi dapat disimpan', (
    tester,
  ) async {
    _smallScreen(tester);
    final remote = _FakeGoodsLoanRemoteDataSource();
    await tester.pumpWidget(_app(remote, const GoodsLoanView()));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Peminjaman Barang'), findsOneWidget);
    expect(find.byKey(const Key('goods-loan-1')), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.byKey(const Key('add-goods-loan')));
    await tester.pumpAndSettle();
    expect(find.text('Catat Peminjaman'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('add-goods-loan-line')));
    await tester.tap(find.byKey(const Key('add-goods-loan-line')));
    await tester.pumpAndSettle();
    await tester.drag(
      find.byKey(const Key('goods-loan-form-scroll')),
      const Offset(0, -900),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('save-goods-loan')));
    await tester.tap(find.byKey(const Key('save-goods-loan')));
    await tester.pumpAndSettle();

    expect(remote.created?.borrowerType, 'siswa');
    expect(remote.created?.borrowerId, 1);
    expect(remote.created?.lines.single.item.assetUnitId, 10);
    expect(tester.takeException(), isNull);
  });

  testWidgets('detail mendukung pengembalian unit aset', (tester) async {
    _smallScreen(tester);
    final remote = _FakeGoodsLoanRemoteDataSource();
    await tester.pumpWidget(_app(remote, const GoodsLoanDetailView(loanId: 1)));
    await tester.pumpAndSettle();

    expect(find.text('PJM-20260907-0001'), findsOneWidget);
    await tester.tap(find.byKey(const Key('return-goods-loan')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('goods-return-select-101')));
    await tester.pumpAndSettle();
    await tester.drag(
      find.byKey(const Key('goods-return-form-scroll')),
      const Offset(0, -700),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('save-goods-return')));
    await tester.tap(find.byKey(const Key('save-goods-return')));
    await tester.pumpAndSettle();

    expect(remote.returnedLoanId, 1);
    expect(remote.returned?.lines.single.detailId, 101);
    expect(remote.returned?.lines.single.condition, 'baik');
    expect(tester.takeException(), isNull);
  });

  testWidgets('pengembalian menampilkan antrean dan ringkasan di layar kecil', (
    tester,
  ) async {
    _smallScreen(tester);
    await tester.pumpWidget(
      _app(_FakeGoodsLoanRemoteDataSource(), const GoodsReturnView()),
    );
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Pengembalian Barang'), findsOneWidget);
    expect(find.byKey(const Key('goods-return-list')), findsOneWidget);
    expect(find.text('Terlambat'), findsWidgets);
    expect(
      find.text('Barang habis pakai tidak melalui proses pengembalian.'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('akun baca tidak melihat aksi pencatatan', (tester) async {
    await tester.pumpWidget(
      _app(
        _FakeGoodsLoanRemoteDataSource(canManage: false),
        const GoodsLoanView(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('add-goods-loan')), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

void _smallScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(320, 640);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Widget _app(GoodsLoanRemoteDataSource remote, Widget home) => ProviderScope(
  overrides: [goodsLoanRemoteDataSourceProvider.overrideWithValue(remote)],
  child: MaterialApp(theme: AppTheme.light, home: home),
);

class _FakeGoodsLoanRemoteDataSource implements GoodsLoanRemoteDataSource {
  _FakeGoodsLoanRemoteDataSource({this.canManage = true});
  final bool canManage;
  GoodsLoanFormValue? created;
  GoodsReturnFormValue? returned;
  int? returnedLoanId;

  @override
  Future<GoodsLoanPage> fetchLoans({
    required String query,
    required String borrowerType,
    required String status,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  }) async {
    final json = _loanPage();
    (json['hak_akses'] as Map<String, dynamic>)['dapat_kelola'] = canManage;
    return GoodsLoanPage.fromJson(json);
  }

  @override
  Future<GoodsReturnPage> fetchReturns({
    required String query,
    required int page,
    int perPage = 15,
  }) async => GoodsReturnPage.fromJson(_returnPage());

  @override
  Future<GoodsLoanDetailResponse> detail(int id) async =>
      GoodsLoanDetailResponse.fromJson(_detail());

  @override
  Future<GoodsLoanDetailResponse> create(GoodsLoanFormValue value) async {
    created = value;
    return GoodsLoanDetailResponse.fromJson(_detail());
  }

  @override
  Future<IdentifiedBorrower> identifyBorrower({
    required String code,
    String type = 'otomatis',
  }) async => const IdentifiedBorrower(
    type: 'siswa',
    id: 1,
    name: 'Andi Saputra',
    identity: '0011223344',
    information: 'VIII A',
  );

  @override
  Future<GoodsLoanAvailableItem> identifyItem({
    required String code,
    int? locationId,
  }) async => GoodsLoanAvailableItem.fromJson(
    (_loanPage()['pilihan'] as Map<String, dynamic>)['barang'][0]
        as Map<String, dynamic>,
  );

  @override
  Future<IdentifiedReturn> identifyReturn(String code) async =>
      const IdentifiedReturn(
        loanId: 1,
        detailId: 101,
        code: 'AST-2026-0001',
        goodsName: 'Laptop Chromebook',
        borrowerName: 'Andi Saputra',
        borrowerIdentity: '0011223344',
        loanNumber: 'PJM-20260907-0001',
        location: 'Labor Komputer',
        condition: 'Baik',
        loanDate: '07 Sep 2026',
        plannedReturn: '08 Sep 2026',
      );

  @override
  Future<GoodsLoanDetailResponse> returnGoods({
    required int loanId,
    required GoodsReturnFormValue value,
  }) async {
    returnedLoanId = loanId;
    returned = value;
    return GoodsLoanDetailResponse.fromJson(_detail(returned: true));
  }
}

Map<String, dynamic> _loanPage() => {
  'ringkasan': {'total': 1, 'aktif': 1, 'selesai': 0, 'hari_ini': 1},
  'filter': {
    'cari': '',
    'jenis_peminjam': 'semua',
    'status': 'semua',
    'tanggal_mulai': null,
    'tanggal_selesai': null,
  },
  'hak_akses': {'dapat_kelola': true, 'dapat_mengembalikan': true},
  'pilihan': {
    'jenis_peminjam': [
      {'nilai': 'siswa', 'label': 'Siswa'},
      {'nilai': 'pegawai', 'label': 'Pegawai'},
    ],
    'status': [
      {'nilai': 'semua', 'label': 'Semua status'},
      {'nilai': 'dipinjam', 'label': 'Dipinjam'},
    ],
    'siswa': [
      {'id': 1, 'label': 'Andi Saputra · 0011223344 · VIII A'},
    ],
    'pegawai': [
      {'id': 2, 'label': 'Budi Santoso · 19800101'},
    ],
    'barang': [
      {
        'kunci': 'unit:10',
        'tipe_item': 'unit',
        'unit_barang_id': 10,
        'barang_id': 5,
        'lokasi_barang_id': 3,
        'kode': 'AST-2026-0001',
        'label': 'Laptop Chromebook',
        'keterangan': 'Labor Komputer · Kondisi baik',
        'jenis_tampilan': 'Aset individual',
        'wajib_dikembalikan': true,
        'satuan': 'unit',
        'saldo': 1,
      },
    ],
  },
  'items': [_loan()],
  'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
};

Map<String, dynamic> _returnPage() => {
  'ringkasan': {'aktif': 1, 'terlambat': 1, 'sebagian': 0, 'jatuh_tempo': 1},
  'filter': {'cari': ''},
  'items': [_loan()],
  'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
};

Map<String, dynamic> _detail({bool returned = false}) => {
  'peminjaman': _loan(returned: returned),
  'hak_akses': {'dapat_kelola': true, 'dapat_mengembalikan': !returned},
  'pilihan': {
    'kondisi': [
      {'nilai': 'baik', 'label': 'Baik'},
      {'nilai': 'rusak_ringan', 'label': 'Rusak ringan'},
    ],
  },
};

Map<String, dynamic> _loan({bool returned = false}) => {
  'id': 1,
  'nomor': 'PJM-20260907-0001',
  'jenis_peminjam': 'siswa',
  'jenis_peminjam_label': 'Siswa',
  'nama_peminjam': 'Andi Saputra',
  'identitas_peminjam': '0011223344',
  'tanggal': '2026-09-07',
  'tanggal_label': '07 Sep 2026',
  'rencana_kembali': '2026-09-08',
  'rencana_kembali_label': '08 Sep 2026',
  'status': returned ? 'selesai' : 'dipinjam',
  'status_label': returned ? 'Selesai' : 'Dipinjam',
  'pemantauan_label': returned ? 'Sudah kembali' : 'Terlambat 1 hari',
  'terlambat': !returned,
  'hari_terlambat': returned ? 0 : 1,
  'jumlah_item': 1,
  'items_belum_kembali': returned ? 0 : 1,
  'catatan': 'Untuk praktik kelas.',
  'dibuat_oleh': 'Administrator NUSA',
  'items': [
    {
      'id': 101,
      'barang_id': 5,
      'nama_barang': 'Laptop Chromebook',
      'kode': 'AST-2026-0001',
      'unit_barang_id': 10,
      'lokasi': 'Labor Komputer',
      'tipe_pengelolaan': 'individual',
      'jumlah': 1,
      'jumlah_dikembalikan': returned ? 1 : 0,
      'jumlah_belum_dikembalikan': returned ? 0 : 1,
      'wajib_dikembalikan': true,
      'satuan': 'unit',
      'cara_input': 'manual',
      'catatan': null,
    },
  ],
  'pengembalian': returned
      ? [
          {
            'id': 201,
            'nomor': 'PNG-20260907-0001',
            'tanggal': '2026-09-07',
            'tanggal_label': '07 Sep 2026',
            'catatan': null,
            'dibuat_oleh': 'Administrator NUSA',
            'items': [
              {
                'id': 301,
                'nama_barang': 'Laptop Chromebook',
                'jumlah': 1,
                'satuan': 'unit',
                'kondisi': 'baik',
                'kondisi_label': 'Baik',
                'cara_input': 'manual',
                'catatan': null,
              },
            ],
          },
        ]
      : [],
};
