import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_point_recap/data/student_point_recap_remote_data_source.dart';
import 'package:nusa/features/student_point_recap/domain/student_point_recap.dart';
import 'package:nusa/features/student_point_recap/presentation/student_point_recap_detail_view.dart';
import 'package:nusa/features/student_point_recap/presentation/student_point_recap_list_view.dart';

void main() {
  test('domain membaca saldo, indikator, transaksi, dan profil disiplin', () {
    final page = StudentPointRecapPage.fromJson(_pageJson());
    final detail = StudentPointRecapDetail.fromJson(_detailJson());

    expect(page.summary.withPoints, 3);
    expect(page.items.single.totalPoints, 15);
    expect(page.items.single.indicator.code, 'mendekati_sanksi');
    expect(page.classSummaries.single.totalPoints, 45);
    expect(detail.summary.pendingPoints, 10);
    expect(detail.transactions.length, 2);
    expect(detail.warnings.single.typeLabel, 'Mendekati Ambang Sanksi');
    expect(detail.sanctions.single.name, 'Teguran Lisan');
  });

  testWidgets('daftar rekap poin rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentPointRecapRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentPointRecapRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentPointRecapListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Rekap Poin Siswa'), findsOneWidget);
    expect(find.text('Siswa Rekap Native'), findsOneWidget);
    expect(find.text('Mendekati ambang sanksi'), findsOneWidget);
    expect(find.byKey(const Key('point-recap-status-filter')), findsOneWidget);
    expect(find.byKey(const Key('point-recap-class-summary')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('profil disiplin membuka pendampingan dengan siswa terpilih', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentPointRecapRemoteDataSource();
    final router = GoRouter(
      initialLocation: '/rekap-poin-siswa/1?tahun=1',
      routes: [
        GoRoute(
          path: '/rekap-poin-siswa/:id',
          builder: (context, state) => StudentPointRecapDetailView(
            studentId: int.parse(state.pathParameters['id']!),
            academicYearId: int.tryParse(
              state.uri.queryParameters['tahun'] ?? '',
            ),
          ),
        ),
        GoRoute(
          path: '/pendampingan-siswa/tambah',
          builder: (context, state) => Scaffold(
            body: Text(
              'Pendampingan Siswa ${state.uri.queryParameters['siswa']} '
              'Tahun ${state.uri.queryParameters['tahun']}',
            ),
          ),
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentPointRecapRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Profil Disiplin Siswa'), findsOneWidget);
    expect(find.text('15'), findsWidgets);
    await tester.scrollUntilVisible(
      find.byKey(const Key('point-recap-start-assistance')),
      350,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('point-recap-start-assistance')));
    await tester.pumpAndSettle();

    expect(find.text('Pendampingan Siswa 1 Tahun 1'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _FakeStudentPointRecapRemoteDataSource
    implements StudentPointRecapRemoteDataSource {
  @override
  Future<StudentPointRecapPage> fetch({
    required String query,
    required String attentionStatus,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async => StudentPointRecapPage.fromJson(_pageJson());

  @override
  Future<StudentPointRecapDetail> fetchDetail(
    int studentId,
    int? academicYearId,
  ) async => StudentPointRecapDetail.fromJson(_detailJson());
}

Map<String, dynamic> _pageJson() => {
  'items': [_studentItemJson()],
  'ringkasan': {
    'total_siswa': 5,
    'siswa_berpoin': 3,
    'mendekati_sanksi': 1,
    'laporan_menunggu': 1,
    'sanksi_aktif': 1,
  },
  'ringkasan_kelas': [
    {
      'kelas': {'id': 3, 'nama': 'VIII.A'},
      'jumlah_siswa': 5,
      'siswa_berpoin': 3,
      'total_poin': 45,
      'menunggu': 1,
      'sanksi_aktif': 1,
    },
  ],
  'pilihan': {
    'status_perhatian': const [
      {'kode': 'berpoin', 'label': 'Memiliki Poin'},
      {'kode': 'mendekati_sanksi', 'label': 'Mendekati Sanksi'},
    ],
    'tahun_pelajaran': const [
      {'id': 1, 'nama': '2026/2027', 'aktif': true},
    ],
    'kelas': const [
      {'id': 3, 'tahun_pelajaran_id': 1, 'nama': 'VIII.A', 'tingkat': 8},
    ],
  },
  'filter': {
    'kata_kunci': '',
    'status_perhatian': 'semua',
    'tahun_pelajaran_id': 1,
    'kelas_id': null,
  },
  'paginasi': {
    'halaman': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'cakupan_luas': true, 'dapat_kelola_pendampingan': true},
};

Map<String, dynamic> _detailJson() => {
  'siswa': _studentIdentityJson(),
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'ringkasan': {
    'total_poin': 15,
    'peringatan_aktif': 1,
    'peringatan_penting': 0,
    'laporan_menunggu': 1,
    'poin_dalam_proses': 10,
    'sanksi_aktif': 1,
    'keterlambatan': {'jumlah': 2, 'total_menit': 22},
    'indikator': _indicatorJson(),
  },
  'perkembangan_bulanan': const [
    {'kunci': '2026-07', 'label': 'Jul 2026', 'perubahan': 20, 'saldo': 20},
    {'kunci': '2026-08', 'label': 'Agu 2026', 'perubahan': -5, 'saldo': 15},
  ],
  'transaksi': const [
    {
      'id': 2,
      'jenis': 'pengurangan',
      'label_jenis': 'Pengurangan',
      'poin': -5,
      'keterangan': 'Pengurangan poin yang disetujui.',
      'tercatat_pada': '2026-08-20T09:00:00+07:00',
      'sumber': null,
    },
    {
      'id': 1,
      'jenis': 'pelanggaran',
      'label_jenis': 'Pelanggaran',
      'poin': 20,
      'keterangan': 'Pelanggaran yang sudah disahkan.',
      'tercatat_pada': '2026-07-20T09:00:00+07:00',
      'sumber': {'jenis': 'laporan', 'id': 7, 'label': 'PB-001'},
    },
  ],
  'laporan': const [
    {
      'id': 7,
      'nomor': 'PB-001',
      'tanggal': '2026-08-20',
      'jenis': 'pelanggaran',
      'label_jenis': 'Pelanggaran Berpoin',
      'kategori': 'Kedisiplinan',
      'kode_pelanggaran': ['P-01'],
      'status': 'pemeriksaan_bk',
      'label_status': 'Pemeriksaan BK',
      'poin': 10,
    },
  ],
  'peringatan': const [
    {
      'id': 9,
      'jenis': 'mendekati_sanksi',
      'label_jenis': 'Mendekati Ambang Sanksi',
      'tingkat': 'peringatan',
      'label_tingkat': 'Peringatan',
      'pesan': 'Tersisa 10 poin menuju Teguran Lisan.',
      'siklus': 1,
      'terakhir_terdeteksi_pada': '2026-09-01T08:00:00+07:00',
    },
  ],
  'pendampingan': const [
    {
      'id': 4,
      'tanggal': '2026-08-01',
      'jenis': 'konseling',
      'label_jenis': 'Konseling Siswa',
      'status': 'selesai',
      'label_status': 'Selesai',
      'petugas': 'Guru BK Native',
      'ringkasan': 'Pendampingan sebelumnya selesai.',
      'peringatan_id': 9,
    },
  ],
  'sanksi': const [
    {
      'id': 3,
      'nama': 'Teguran Lisan',
      'ambang_poin': 25,
      'poin_saat_terpicu': 25,
      'status': 'menunggu',
      'label_status': 'Menunggu Penugasan',
      'terpicu_pada': '2026-08-25T08:00:00+07:00',
      'batas_pelaksanaan': null,
      'petugas': null,
      'terlambat': false,
    },
  ],
  'pengurangan': const [
    {
      'id': 5,
      'tanggal': '2026-08-20',
      'jenis_kegiatan': 'Bakti sosial',
      'deskripsi': 'Mengikuti kegiatan sekolah.',
      'poin': 5,
      'status': 'disetujui',
      'label_status': 'Disetujui',
      'disetujui_oleh': 'Wakil Kesiswaan',
    },
  ],
  'keterlambatan': const [
    {
      'id': 8,
      'tanggal': '2026-08-19',
      'kelas': 'VIII.A',
      'menit': 12,
      'poin': 0,
      'status_poin': 'menunggu',
    },
  ],
  'pilihan_tahun': const [
    {'id': 1, 'nama': '2026/2027', 'aktif': true},
  ],
  'hak_akses': {'cakupan_luas': true, 'dapat_kelola_pendampingan': true},
};

Map<String, dynamic> _studentItemJson() => {
  ..._studentIdentityJson(),
  'total_poin': 15,
  'laporan_menunggu': 1,
  'sanksi_aktif': 0,
  'indikator': _indicatorJson(),
};

Map<String, dynamic> _studentIdentityJson() => {
  'siswa': {
    'id': 1,
    'nama': 'Siswa Rekap Native',
    'nis': 'NUSA-01',
    'nisn': '0088331001',
  },
  'kelas': {'id': 3, 'nama': 'VIII.A'},
  'guru_wali': {'id': 6, 'nama': 'Guru Wali Native', 'nip': '19800101'},
};

Map<String, dynamic> _indicatorJson() => {
  'kode': 'mendekati_sanksi',
  'label': 'Mendekati ambang sanksi',
  'jarak': 10,
  'persentase': 60,
  'ambang_berikutnya': {'id': 1, 'nama': 'Teguran Lisan', 'batas_poin': 25},
};
