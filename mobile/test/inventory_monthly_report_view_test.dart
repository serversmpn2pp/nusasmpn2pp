import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/inventory_monthly_report/application/inventory_monthly_report_document_service.dart';
import 'package:nusa/features/inventory_monthly_report/data/inventory_monthly_report_remote_data_source.dart';
import 'package:nusa/features/inventory_monthly_report/domain/inventory_monthly_report.dart';
import 'package:nusa/features/inventory_monthly_report/presentation/inventory_monthly_report_view.dart';

void main() {
  test('domain laporan membaca seluruh bagian laporan desktop', () {
    final report = InventoryMonthlyReport.fromJson(_report());

    expect(report.summary.stockNeedsAttention, 1);
    expect(report.stockRows.single.closing, 10);
    expect(report.employeeServices.single.employee.name, 'Dina Kurnia, S.Pd.');
    expect(report.employeeServices.single.items.single.mustReturn, isTrue);
    expect(report.unitsNeedingAttention.single.condition, 'rusak_ringan');
    expect(report.movements.length, 2);
    expect(report.signatories.length, 3);
  });

  test('PDF laporan memuat data operasional lengkap', () async {
    final report = InventoryMonthlyReport.fromJson(_report());
    final bytes = await InventoryMonthlyReportPdfBuilder().build(report);

    expect(bytes.length, greaterThan(1000));
    expect(String.fromCharCodes(bytes.take(4)), '%PDF');
  });

  testWidgets('laporan dan filter rapi pada layar Android kecil', (
    tester,
  ) async {
    _smallScreen(tester);
    final remote = _FakeInventoryMonthlyReportRemoteDataSource();
    await tester.pumpWidget(_app(remote));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Laporan Inventaris'), findsOneWidget);
    expect(find.byKey(const Key('inventory-report-summary')), findsOneWidget);
    expect(find.text('September 2026'), findsWidgets);
    expect(find.byKey(const Key('inventory-report-services')), findsOneWidget);
    expect(find.text('Dina Kurnia, S.Pd.'), findsOneWidget);

    await tester.tap(find.byKey(const Key('inventory-report-filter')));
    await tester.pumpAndSettle();
    expect(find.text('Filter Laporan'), findsOneWidget);
    expect(find.byKey(const Key('inventory-report-month')), findsOneWidget);
    expect(find.byKey(const Key('inventory-report-year')), findsOneWidget);
    expect(find.byKey(const Key('inventory-report-location')), findsOneWidget);

    await tester.tap(find.byKey(const Key('inventory-report-location')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Labor Komputer').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('apply-inventory-report-filter')));
    await tester.pumpAndSettle();

    expect(remote.lastFilter?.period, '2026-09');
    expect(remote.lastFilter?.locationId, 2);
    expect(tester.takeException(), isNull);
  });

  testWidgets('bagian stok, aset, dan mutasi dapat dibuka tanpa overflow', (
    tester,
  ) async {
    _smallScreen(tester);
    await tester.pumpWidget(
      _app(_FakeInventoryMonthlyReportRemoteDataSource()),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('inventory-report-assets')),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Snapshot Unit Aset'));
    await tester.pumpAndSettle();
    expect(find.text('AST-2026-000002 · Labor Komputer'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('inventory-report-movements')),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Rincian Mutasi Periode'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Stok awal'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

class _FakeInventoryMonthlyReportRemoteDataSource
    implements InventoryMonthlyReportRemoteDataSource {
  InventoryReportFilter? lastFilter;

  @override
  Future<InventoryMonthlyReport> fetch(InventoryReportFilter filter) async {
    lastFilter = filter;
    return InventoryMonthlyReport.fromJson(_report());
  }
}

Widget _app(InventoryMonthlyReportRemoteDataSource remote) => ProviderScope(
  overrides: [
    inventoryMonthlyReportRemoteDataSourceProvider.overrideWithValue(remote),
  ],
  child: MaterialApp(
    theme: AppTheme.light,
    home: const InventoryMonthlyReportView(),
  ),
);

void _smallScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(360, 640);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Map<String, dynamic> _report() => {
  'periode': '2026-09',
  'label_periode': 'September 2026',
  'awal_periode': '2026-09-01',
  'akhir_periode': '2026-09-30',
  'lokasi_barang_id': null,
  'lokasi_label': 'Semua lokasi',
  'dicetak_pada': '07 September 2026 08:00',
  'pilihan': {
    'lokasi': [
      {'id': 2, 'kode': 'LAB', 'nama': 'Labor Komputer', 'aktif': true},
    ],
  },
  'ringkasan': {
    'baris_stok': 1,
    'jumlah_mutasi': 2,
    'stok_menipis': 1,
    'stok_habis': 0,
    'stok_belum_dicatat': 1,
    'unit_aset': 2,
    'unit_diperoleh': 1,
    'unit_perlu_perhatian': 1,
  },
  'ringkasan_layanan_pegawai': {
    'jumlah_layanan': 1,
    'pegawai_dilayani': 1,
    'peminjaman_aset': 1,
    'penyerahan_habis_pakai': 0,
    'pinjaman_aktif': 1,
  },
  'penandatangan': [
    {
      'kode': 'wakil_sarpras',
      'jabatan': 'Wakil Kepala Sekolah Bidang Sarpras',
      'nama': 'Budi Santoso, S.Pd.',
      'nip': '198002022006041001',
    },
    {
      'kode': 'petugas_inventaris',
      'jabatan': 'Petugas Inventaris',
      'nama': 'Rina Kurnia, S.Pd.',
      'nip': '198503032010012002',
    },
    {
      'kode': 'kepala_sekolah',
      'jabatan': 'Kepala Sekolah',
      'nama': 'Dra. Rahmawati',
      'nip': '197001011995012001',
    },
  ],
  'barang_stok_belum_dicatat': [
    {'id': 9, 'kode': 'BHP-009', 'nama': 'Tinta Printer', 'satuan': 'Buah'},
  ],
  'rekap_stok': [
    {
      'id': 5,
      'barang': {
        'id': 1,
        'kode': 'BHP-001',
        'nama': 'Spidol Papan Tulis',
        'kategori': 'ATK',
        'satuan': 'Buah',
      },
      'lokasi': {'id': 2, 'nama': 'Labor Komputer'},
      'saldo_awal': 0,
      'stok_masuk': 12,
      'stok_keluar': 2,
      'penyesuaian': 0,
      'saldo_akhir': 10,
      'jumlah_mutasi': 2,
      'status': 'menipis',
      'status_label': 'Menipis',
    },
  ],
  'distribusi_status_unit': [
    {'kode': 'tersedia', 'label': 'Tersedia', 'jumlah': 0},
    {'kode': 'dipinjam', 'label': 'Dipinjam', 'jumlah': 1},
    {'kode': 'dalam_perbaikan', 'label': 'Dalam perbaikan', 'jumlah': 1},
  ],
  'unit_perlu_perhatian': [
    {
      'id': 8,
      'kode_inventaris': 'AST-2026-000002',
      'barang': 'Laptop Chromebook',
      'lokasi': 'Labor Komputer',
      'kondisi': 'rusak_ringan',
      'kondisi_label': 'Rusak ringan',
      'status': 'dalam_perbaikan',
      'status_label': 'Dalam perbaikan',
    },
  ],
  'layanan_barang_pegawai': [
    {
      'id': 4,
      'nomor': 'PMJ-20260907-0001',
      'pegawai': {
        'id': 3,
        'nama': 'Dina Kurnia, S.Pd.',
        'nip': '198505052010012001',
        'jenis': 'Guru',
      },
      'tanggal': '2026-09-07',
      'tanggal_label': '07 Sep 2026',
      'rencana_kembali': '2026-09-12',
      'rencana_kembali_label': '12 Sep 2026',
      'status': 'dipinjam',
      'status_label': 'Dipinjam',
      'sumber': 'Dicatat petugas',
      'items': [
        {
          'barang': 'Laptop Chromebook',
          'jumlah': 1,
          'satuan': 'unit',
          'kode_inventaris': 'AST-2026-000001',
          'lokasi': 'Labor Komputer',
          'wajib_dikembalikan': true,
        },
      ],
    },
  ],
  'mutasi_periode': [
    {
      'id': 2,
      'tanggal': '2026-09-01',
      'tanggal_label': '01 Sep 2026',
      'barang': 'Spidol Papan Tulis',
      'lokasi': 'Labor Komputer',
      'jenis': 'keluar',
      'jenis_label': 'Keluar',
      'kategori': 'pengeluaran_pemakaian',
      'kategori_label': 'Pengeluaran pemakaian',
      'jumlah_perubahan': -2,
      'saldo_akhir': 10,
      'satuan': 'Buah',
      'referensi': null,
    },
    {
      'id': 1,
      'tanggal': '2026-09-01',
      'tanggal_label': '01 Sep 2026',
      'barang': 'Spidol Papan Tulis',
      'lokasi': 'Labor Komputer',
      'jenis': 'masuk',
      'jenis_label': 'Masuk',
      'kategori': 'stok_awal',
      'kategori_label': 'Stok awal',
      'jumlah_perubahan': 12,
      'saldo_akhir': 12,
      'satuan': 'Buah',
      'referensi': 'SALDO-AWAL',
    },
  ],
};
