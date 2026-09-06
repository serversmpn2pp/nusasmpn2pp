import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/goods_request/data/goods_request_remote_data_source.dart';
import 'package:nusa/features/goods_request/domain/goods_request.dart';
import 'package:nusa/features/goods_request/presentation/goods_request_detail_view.dart';
import 'package:nusa/features/goods_request/presentation/goods_request_view.dart';

void main() {
  test('domain pengajuan membaca ringkasan dan ketersediaan aset', () {
    final page = GoodsRequestPage.fromJson(_page());
    final detail = GoodsRequestDetail.fromJson(_detail());

    expect(page.summary.pending, 1);
    expect(page.items.single.goodsName, 'Laptop Chromebook');
    expect(detail.requiredUnits, 1);
    expect(detail.units.single.code, 'AST-2026-000010');
    expect(detail.canFulfill, isTrue);
  });

  testWidgets('daftar pengajuan rapi pada layar kecil', (tester) async {
    _smallScreen(tester);
    await tester.pumpWidget(
      _app(_FakeGoodsRequestRemoteDataSource(), const GoodsRequestView()),
    );
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Pengajuan Barang'), findsOneWidget);
    expect(find.byKey(const Key('goods-request-list')), findsOneWidget);
    expect(find.byKey(const Key('goods-request-1')), findsOneWidget);
    expect(find.text('Menunggu petugas'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('petugas memilih unit dan memenuhi pengajuan aset', (
    tester,
  ) async {
    _smallScreen(tester);
    final remote = _FakeGoodsRequestRemoteDataSource();
    await tester.pumpWidget(
      _app(remote, const GoodsRequestDetailView(requestId: 1)),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('fulfill-goods-request')));
    await tester.pumpAndSettle();
    expect(find.text('Penuhi & Serahkan Barang'), findsOneWidget);
    await tester.tap(find.byKey(const Key('goods-request-unit-10')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(
      find.byKey(const Key('save-goods-request-fulfill')),
    );
    await tester.tap(find.byKey(const Key('save-goods-request-fulfill')));
    await tester.pumpAndSettle();

    expect(remote.fulfilledId, 1);
    expect(remote.fulfilled?.unitIds, [10]);
    expect(find.text('Dipenuhi'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('alasan penolakan dikirim dan aksi ditutup', (tester) async {
    _smallScreen(tester);
    final remote = _FakeGoodsRequestRemoteDataSource();
    await tester.pumpWidget(
      _app(remote, const GoodsRequestDetailView(requestId: 1)),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('reject-goods-request')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('goods-request-reject-reason')),
      'Barang dipakai untuk kegiatan lain.',
    );
    await tester.tap(find.byKey(const Key('save-goods-request-reject')));
    await tester.pumpAndSettle();

    expect(remote.rejectedId, 1);
    expect(remote.reason, 'Barang dipakai untuk kegiatan lain.');
    expect(find.text('Ditolak'), findsWidgets);
    expect(find.byKey(const Key('fulfill-goods-request')), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

void _smallScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(320, 640);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Widget _app(GoodsRequestRemoteDataSource remote, Widget home) => ProviderScope(
  overrides: [goodsRequestRemoteDataSourceProvider.overrideWithValue(remote)],
  child: MaterialApp(theme: AppTheme.light, home: home),
);

class _FakeGoodsRequestRemoteDataSource
    implements GoodsRequestRemoteDataSource {
  int? fulfilledId;
  GoodsRequestFulfillValue? fulfilled;
  int? rejectedId;
  String? reason;

  @override
  Future<GoodsRequestPage> fetch({
    required String query,
    required String type,
    required String status,
    required int page,
    int perPage = 15,
  }) async => GoodsRequestPage.fromJson(_page());

  @override
  Future<GoodsRequestDetail> detail(int id) async =>
      GoodsRequestDetail.fromJson(_detail());

  @override
  Future<GoodsRequestDetail> fulfill(
    int id,
    GoodsRequestFulfillValue value,
  ) async {
    fulfilledId = id;
    fulfilled = value;
    return GoodsRequestDetail.fromJson(
      _detail(status: 'dipenuhi', statusLabel: 'Dipenuhi'),
    );
  }

  @override
  Future<GoodsRequestDetail> reject(int id, String value) async {
    rejectedId = id;
    reason = value;
    return GoodsRequestDetail.fromJson(
      _detail(status: 'ditolak', statusLabel: 'Ditolak'),
    );
  }
}

Map<String, dynamic> _page() => {
  'ringkasan': {'semua': 1, 'menunggu': 1, 'peminjaman': 1, 'permintaan': 0},
  'filter': {'kata_kunci': '', 'jenis': 'semua', 'status': 'menunggu'},
  'pilihan': {
    'jenis': [
      {'nilai': 'semua', 'label': 'Semua jenis'},
      {'nilai': 'peminjaman', 'label': 'Peminjaman aset'},
    ],
    'status': [
      {'nilai': 'semua', 'label': 'Semua status'},
      {'nilai': 'menunggu', 'label': 'Menunggu petugas'},
    ],
  },
  'items': [_request()],
  'paginasi': {'halaman': 1, 'total': 1, 'ada_halaman_berikutnya': false},
};

Map<String, dynamic> _detail({
  String status = 'menunggu',
  String statusLabel = 'Menunggu petugas',
}) => {
  'pengajuan': {
    ..._request(),
    'status': status,
    'status_label': statusLabel,
    'tujuan': 'Pembelajaran di ruang kelas.',
    'catatan_petugas': status == 'menunggu' ? null : 'Sudah diproses.',
    'diproses_oleh': status == 'menunggu' ? null : 'Administrator NUSA',
    'diproses_pada_label': status == 'menunggu' ? null : '07 Sep 2026, 08:00',
    'peminjaman_barang_id': status == 'dipenuhi' ? 9 : null,
    'nomor_peminjaman': status == 'dipenuhi' ? 'PJM-20260907-000009' : null,
    'tipe_pengelolaan': 'aset_individual',
    'kategori_barang': 'Peralatan TIK',
  },
  'ketersediaan': {
    'unit_dibutuhkan': status == 'menunggu' ? 1 : 0,
    'unit': status == 'menunggu'
        ? [
            {
              'id': 10,
              'kode': 'AST-2026-000010',
              'nomor_aset_resmi': '02.06.10',
              'lokasi': 'Labor Komputer',
              'kondisi': 'Baik',
            },
          ]
        : [],
    'saldo': [],
  },
  'hak_akses': {
    'dapat_memenuhi': status == 'menunggu',
    'dapat_menolak': status == 'menunggu',
  },
};

Map<String, dynamic> _request() => {
  'id': 1,
  'nomor': 'PGJ-20260907-000001',
  'nama_pegawai': 'Dina Kurnia, S.Pd.',
  'nip': '198505052010012001',
  'jenis_pegawai': 'Guru',
  'barang_id': 5,
  'kode_barang': '02.06.01.05.40',
  'nama_barang': 'Laptop Chromebook',
  'jenis': 'peminjaman',
  'jenis_label': 'Peminjaman aset',
  'jumlah': 1,
  'satuan': 'Unit',
  'tanggal_pengajuan_label': '07 Sep 2026',
  'tanggal_dibutuhkan_label': '08 Sep 2026',
  'rencana_kembali_label': '14 Sep 2026',
  'status': 'menunggu',
  'status_label': 'Menunggu petugas',
};
