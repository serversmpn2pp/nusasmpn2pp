import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/facility_dashboard/data/facility_dashboard_remote_data_source.dart';
import 'package:nusa/features/facility_dashboard/domain/facility_dashboard.dart';
import 'package:nusa/features/facility_dashboard/presentation/facility_dashboard_view.dart';
import 'package:nusa/features/home/presentation/home_view.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

void main() {
  test('domain dashboard sarpras membaca ringkasan dan daftar perhatian', () {
    final data = FacilityDashboard.fromJson(_response());

    expect(data.summary.goodsTypes, 24);
    expect(data.tools.single.label, 'Inventaris Barang');
    expect(data.summary.overdueLoans, 2);
    expect(data.stockAttention.single.status, 'menipis');
    expect(data.overdueLoans.single.overdueDays, 3);
    expect(data.unitAttention.single.conditionLabel, 'Rusak berat');
    expect(data.recentActivities.single.time?.isUtc, isFalse);
  });

  test('kategori Sarana Prasarana membuka dashboard jika akun berhak', () {
    final group = MenuGroup(
      code: 'sarana-prasarana',
      label: 'Sarana Prasarana',
      description: 'Inventaris sekolah',
      icon: 'inventory',
      items: const [
        MenuEntry(
          code: 'dashboard-sarpras',
          label: 'Dashboard Sarpras',
          description: '',
          initials: 'DS',
          subgroup: 'Ringkasan',
          icon: null,
          status: 'tersedia',
          route: '/dashboard-sarpras',
        ),
      ],
    );

    expect(nusaMenuGroupDestination(group), '/dashboard-sarpras');
  });

  testWidgets('dashboard sarpras rapi dan dapat digulir pada layar kecil', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          facilityDashboardRemoteDataSourceProvider.overrideWithValue(
            _FakeFacilityDashboardRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const FacilityDashboardView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Dashboard Sarpras'), findsOneWidget);
    expect(find.text('Sarana Prasarana'), findsOneWidget);
    expect(find.text('24'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.byKey(const Key('facility-tool-inventaris-barang')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('facility-tool-inventaris-barang')));
    await tester.pump();
    expect(
      find.text(
        'Inventaris Barang akan dilanjutkan sebagai modul native berikutnya.',
      ),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.text('Kertas HVS A4'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Kertas HVS A4'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Laptop Labor 1'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Laptop Labor 1'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Barang datang'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Barang datang'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _FakeFacilityDashboardRemoteDataSource
    implements FacilityDashboardRemoteDataSource {
  @override
  Future<FacilityDashboard> fetch() async =>
      FacilityDashboard.fromJson(_response());
}

Map<String, dynamic> _response() => {
  'tanggal': '2026-09-06',
  'tanggal_label': 'Minggu, 06 September 2026',
  'hak_akses': {
    'dapat_melihat_barang': true,
    'dapat_mengelola_barang': true,
    'dapat_mengelola_peminjaman': true,
  },
  'menu': [
    {
      'kode': 'inventaris-barang',
      'label': 'Inventaris Barang',
      'deskripsi': 'Kelola data inventaris sekolah.',
      'inisial': 'IB',
      'subkelompok': 'Inventaris',
      'status': 'segera_hadir',
      'rute': null,
    },
  ],
  'ringkasan': {
    'jenis_barang': 24,
    'unit_aset': 180,
    'unit_tersedia': 165,
    'peminjaman_aktif': 7,
    'peminjaman_terlambat': 2,
    'jatuh_tempo': 3,
    'stok_menipis': 1,
    'stok_habis': 0,
    'unit_perlu_perhatian': 1,
    'stok_belum_dicatat': 1,
  },
  'stok_perlu_perhatian': [
    {
      'id': 1,
      'kode': 'ATK.01',
      'nama': 'Kertas HVS A4',
      'lokasi': 'Gudang ATK',
      'jumlah': 2,
      'stok_minimum': 5,
      'satuan': 'Rim',
      'status': 'menipis',
    },
  ],
  'stok_belum_dicatat': [
    {'id': 2, 'kode': 'ATK.02', 'nama': 'Spidol', 'satuan': 'Kotak'},
  ],
  'peminjaman_terlambat': [
    {
      'id': 1,
      'nomor': 'PJM-2026-001',
      'peminjam': 'Budi Santoso',
      'identitas': 'NIP 19800101',
      'rencana_kembali': '2026-09-03',
      'rencana_kembali_label': '03 Sep 2026',
      'terlambat_hari': 3,
      'barang': ['Proyektor (1 unit)'],
    },
  ],
  'distribusi_status_unit': [
    {
      'kode': 'tersedia',
      'label': 'Tersedia',
      'jumlah': 165,
      'warna': '#15477A',
    },
    {'kode': 'dipinjam', 'label': 'Dipinjam', 'jumlah': 10, 'warna': '#F1C40F'},
    {
      'kode': 'dalam_perbaikan',
      'label': 'Dalam perbaikan',
      'jumlah': 5,
      'warna': '#F97316',
    },
  ],
  'unit_perlu_perhatian': [
    {
      'id': 2,
      'barang': 'Laptop Labor 1',
      'kode_inventaris': 'ICT.01.02',
      'lokasi': 'Labor Komputer',
      'status': 'dalam_perbaikan',
      'status_label': 'Dalam perbaikan',
      'kondisi': 'rusak_berat',
      'kondisi_label': 'Rusak berat',
      'nada': 'bahaya',
    },
  ],
  'aktivitas_terbaru': [
    {
      'jenis': 'Barang datang',
      'judul': 'TRM-2026-001',
      'keterangan': 'BOS - 2 jenis barang',
      'waktu': '2026-09-06T08:30:00+07:00',
      'nada': 'berhasil',
    },
  ],
};
