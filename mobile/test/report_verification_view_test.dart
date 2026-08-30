import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/report_verification/data/report_verification_remote_data_source.dart';
import 'package:nusa/features/report_verification/domain/report_verification.dart';
import 'package:nusa/features/report_verification/presentation/report_verification_detail_view.dart';
import 'package:nusa/features/report_verification/presentation/report_verification_list_view.dart';

void main() {
  test('domain membaca antrean, fakta, tenggat, dan hak aksi', () {
    final page = ReportVerificationPage.fromJson(_pageJson());
    final detail = ReportVerificationDetail.fromJson(_detailJson());

    expect(page.summary.active, 3);
    expect(page.summary.counseling, 2);
    expect(page.items.single.report.number, 'PV-MOB-001');
    expect(page.items.single.facts.completedCount, 3);
    expect(page.items.single.activeStage, 1);
    expect(detail.canReview, isTrue);
    expect(detail.violationOptions.single.points, 15);
    expect(detail.process.userTask, 'Menunggu keputusan BK');
  });

  testWidgets('antrean pemeriksaan rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeReportVerificationRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          reportVerificationRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const ReportVerificationListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Pemeriksaan & Pengesahan'), findsOneWidget);
    expect(find.text('Siswa Pemeriksaan Native'), findsOneWidget);
    expect(
      find.byKey(const Key('report-verification-queue-filter')),
      findsOneWidget,
    );
    expect(find.text('Menunggu keputusan BK'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('BK memilih butir dan menyimpan keputusan dengan konfirmasi', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeReportVerificationRemoteDataSource();
    final router = _detailRouter();
    addTearDown(router.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          reportVerificationRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Detail Pemeriksaan'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.byKey(const Key('report-verification-review-form')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.ensureVisible(
      find.byKey(const Key('report-verification-pick-violations')),
    );
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const Key('report-verification-pick-violations')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('violation-option-11')));
    await tester.tap(
      find.byKey(const Key('report-verification-save-violations')),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('report-verification-submit-review')),
      260,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(
      find.byKey(const Key('report-verification-submit-review')),
    );
    await tester.pumpAndSettle();

    expect(find.text('Simpan keputusan BK?'), findsOneWidget);
    await tester.tap(
      find.byKey(const Key('report-verification-confirm-submit')),
    );
    await tester.pumpAndSettle();

    expect(remote.reviewCalls, 1);
    expect(remote.lastReviewResult, 'sanksi_poin');
    expect(remote.lastViolationIds, [11]);
    expect(find.text('Keputusan BK berhasil disimpan.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Wakil mengesahkan rekomendasi poin melalui dialog pengaman', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeReportVerificationRemoteDataSource(wakilMode: true);
    final router = _detailRouter();
    addTearDown(router.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          reportVerificationRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('report-verification-submit-approval')),
      600,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(
      find.byKey(const Key('report-verification-submit-approval')),
    );
    await tester.pumpAndSettle();

    expect(find.text('Sahkan rekomendasi poin?'), findsOneWidget);
    await tester.tap(
      find.byKey(const Key('report-verification-confirm-submit')),
    );
    await tester.pumpAndSettle();

    expect(remote.approveCalls, 1);
    expect(remote.lastApprovalDecision, 'sahkan');
    expect(find.text('Poin siswa berhasil disahkan.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

GoRouter _detailRouter() => GoRouter(
  initialLocation: '/detail/1',
  routes: [
    GoRoute(
      path: '/',
      builder: (context, state) => const Scaffold(body: Text('Antrean')),
    ),
    GoRoute(
      path: '/detail/:id',
      builder: (context, state) => ReportVerificationDetailView(
        reportId: int.parse(state.pathParameters['id']!),
      ),
    ),
  ],
);

Map<String, dynamic> _pageJson({bool wakil = false}) => {
  'items': [_taskJson(wakil: wakil)],
  'ringkasan': {
    'aktif': 3,
    'bk': wakil ? 0 : 2,
    'wakil': wakil ? 1 : 1,
    'terlambat': 1,
    'selesai': 4,
  },
  'pilihan_antrean': [
    {'kode': 'semua', 'label': 'Semua tugas aktif'},
    {'kode': 'bk', 'label': 'Pemeriksaan BK'},
    {'kode': 'wakil', 'label': 'Pengesahan Wakil Kesiswaan'},
    {'kode': 'terlambat', 'label': 'Terlambat diproses'},
    {'kode': 'selesai', 'label': 'Riwayat selesai'},
  ],
  'filter': {'kata_kunci': '', 'antrean': wakil ? 'wakil' : 'semua'},
  'paginasi': {
    'halaman': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {
    'dapat_verifikasi_bk': !wakil,
    'dapat_sahkan_wakil': wakil,
    'dapat_memantau_semua': false,
  },
};

Map<String, dynamic> _taskJson({bool wakil = false}) => {
  ..._reportJson(wakil: wakil),
  'tugas_pengguna': wakil
      ? 'Menunggu pengesahan Wakil Kesiswaan'
      : 'Menunggu keputusan BK',
  'tahap_aktif': wakil ? 2 : 1,
  'batas_hari': 2,
  'hari_menunggu': 1,
  'sisa_hari': 1,
  'terlambat_diproses': false,
  'kelengkapan_fakta': {
    'kronologi': true,
    'lokasi': true,
    'butir': wakil,
    'bukti': true,
    'saksi': false,
    'klarifikasi': false,
  },
  'keputusan_bk_terakhir': wakil
      ? {
          'hasil': 'sanksi_poin',
          'label_hasil': 'Tetapkan Sanksi Poin',
          'catatan': 'Fakta mendukung rekomendasi.',
          'petugas': 'BK Native',
          'diproses_pada': '2026-08-31T09:00:00+07:00',
        }
      : null,
};

Map<String, dynamic> _reportJson({bool wakil = false}) => {
  'id': 1,
  'nomor_laporan': 'PV-MOB-001',
  'jenis_laporan': wakil ? 'pelanggaran' : 'kejadian',
  'label_jenis_laporan': wakil ? 'Pelanggaran Berpoin' : 'Laporan Kejadian',
  'sumber_laporan': 'manual',
  'tanggal_kejadian': '2026-08-30',
  'waktu_kejadian': '09:15',
  'tempat_kejadian': 'Halaman sekolah',
  'siswa': {
    'id': 1,
    'nama': 'Siswa Pemeriksaan Native',
    'nis': 'VER-001',
    'nisn': '0099007788',
  },
  'kelas': {'id': 1, 'nama': 'VIII.A', 'tingkat': 8},
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'kategori': wakil ? {'id': 1, 'nama': 'Kedisiplinan'} : null,
  'pelapor': {'id': 2, 'nama': 'Guru Pelapor', 'nip': '197001012000011001'},
  'tingkat': 'ringan',
  'label_tingkat': 'Ringan',
  'status': 'baru',
  'label_status': 'Baru',
  'status_verifikasi': wakil ? 'menunggu_pengesahan_wakil' : 'diajukan',
  'label_status_verifikasi': wakil
      ? 'Menunggu Pengesahan Wakil Kesiswaan'
      : 'Diajukan',
  'total_poin': wakil ? 15 : 0,
  'tenggat': {
    'tahap': wakil ? 'pengesahan_wakil' : 'pemeriksaan_bk',
    'label_tahap': wakil ? 'Pengesahan Wakil Kesiswaan' : 'Pemeriksaan BK',
    'pada': '2026-09-02T09:15:00+07:00',
    'terlambat': false,
  },
  'jumlah_butir': wakil ? 1 : 0,
  'jumlah_bukti': 1,
  'jumlah_saksi': 0,
  'jumlah_klarifikasi': 0,
  'jumlah_tindak_lanjut': 0,
  'dibuat_pada': '2026-08-30T09:20:00+07:00',
};

Map<String, dynamic> _detailJson({bool wakil = false, bool finished = false}) {
  final effectiveWakil = wakil || finished;
  return {
    'laporan': {
      ..._reportJson(wakil: effectiveWakil),
      if (finished) ...{
        'status_verifikasi': 'disahkan',
        'label_status_verifikasi': 'Disahkan',
      },
      'kronologi': 'Kronologi faktual yang sudah diperiksa.',
      'tindakan_awal': 'Siswa diarahkan memberikan klarifikasi.',
      'wali_kelas': {'id': 3, 'nama': 'Wali Kelas', 'nip': null},
      'guru_wali': null,
    },
    'butir_pelanggaran': effectiveWakil
        ? [
            {
              'id': 21,
              'jenis_pelanggaran_id': 11,
              'kode': 'A-01',
              'nama': 'Pelanggaran kedisiplinan',
              'tingkat': 'ringan',
              'poin': 15,
              'catatan': null,
            },
          ]
        : [],
    'pemeriksaan_bk': effectiveWakil
        ? [
            {
              'id': 31,
              'hasil': 'sanksi_poin',
              'label_hasil': 'Tetapkan Sanksi Poin',
              'catatan': 'Fakta mendukung rekomendasi.',
              'petugas': 'BK Native',
              'diproses_pada': '2026-08-31T09:00:00+07:00',
            },
          ]
        : [],
    'persetujuan': finished
        ? [
            {
              'id': 41,
              'jenis': 'wakil_kesiswaan',
              'label_jenis': 'Wakil Kesiswaan',
              'keputusan': 'setuju',
              'label_keputusan': 'Disahkan',
              'catatan': null,
              'petugas': 'Wakil Native',
              'diproses_pada': '2026-08-31T10:00:00+07:00',
            },
          ]
        : [],
    'bukti': [
      {
        'id': 51,
        'jenis': 'dokumen',
        'nama_file': 'bukti.pdf',
        'tipe_file': 'application/pdf',
        'ukuran_file': 2048,
        'ukuran_ringkas': '2 KB',
        'keterangan': 'Bukti kejadian.',
        'diunggah_pada': '2026-08-30T09:21:00+07:00',
      },
    ],
    'saksi': [],
    'klarifikasi': [],
    'tindak_lanjut': [],
    'linimasa': [
      {
        'id': 61,
        'kode': 'laporan_dibuat',
        'judul': 'Laporan dibuat',
        'keterangan': 'Siap diperiksa.',
        'status_sebelum': null,
        'status_sesudah': 'diajukan',
        'pengguna': 'Guru Pelapor',
        'terjadi_pada': '2026-08-30T09:20:00+07:00',
      },
    ],
    'hak_akses': {
      'dapat_kelola_fakta': !effectiveWakil,
      'dapat_mencatat_klarifikasi': !effectiveWakil,
    },
    'proses': {
      'tugas_pengguna': finished
          ? 'Proses keputusan selesai'
          : effectiveWakil
          ? 'Menunggu pengesahan Wakil Kesiswaan'
          : 'Menunggu keputusan BK',
      'tahap_aktif': finished ? 3 : (effectiveWakil ? 2 : 1),
      'batas_hari': finished ? 0 : 2,
      'hari_menunggu': 1,
      'sisa_hari': 1,
      'terlambat_diproses': false,
      'kelengkapan_fakta': {
        'kronologi': true,
        'lokasi': true,
        'butir': effectiveWakil,
        'bukti': true,
        'saksi': false,
        'klarifikasi': false,
      },
    },
    'pilihan_hasil_bk': [
      {'kode': 'sanksi_poin', 'label': 'Tetapkan Sanksi Poin'},
      {'kode': 'pembinaan', 'label': 'Tetapkan Pembinaan Tanpa Poin'},
      {'kode': 'perlu_klarifikasi', 'label': 'Perlu Klarifikasi'},
      {'kode': 'tidak_terbukti', 'label': 'Tidak Terbukti'},
    ],
    'pilihan_keputusan_wakil': [
      {'kode': 'sahkan', 'label': 'Sahkan Rekomendasi Poin'},
      {'kode': 'kembalikan', 'label': 'Kembalikan kepada BK'},
    ],
    'jenis_pelanggaran': [
      {
        'id': 11,
        'kode': 'A-01',
        'nama': 'Pelanggaran kedisiplinan',
        'tingkat': 'ringan',
        'poin': 15,
        'kategori': 'Kedisiplinan',
      },
    ],
    'hak_aksi': {
      'dapat_verifikasi_bk': !effectiveWakil,
      'dapat_sahkan_wakil': wakil && !finished,
    },
  };
}

final class _FakeReportVerificationRemoteDataSource
    implements ReportVerificationRemoteDataSource {
  _FakeReportVerificationRemoteDataSource({this.wakilMode = false});

  final bool wakilMode;
  int reviewCalls = 0;
  int approveCalls = 0;
  String? lastReviewResult;
  String? lastApprovalDecision;
  List<int> lastViolationIds = [];
  bool finished = false;

  @override
  Future<ReportVerificationPage> fetch({
    required String query,
    required String queue,
    required int page,
  }) async => ReportVerificationPage.fromJson(_pageJson(wakil: wakilMode));

  @override
  Future<ReportVerificationDetail> fetchDetail(int reportId) async =>
      ReportVerificationDetail.fromJson(
        _detailJson(wakil: wakilMode, finished: finished),
      );

  @override
  Future<ReportVerificationMutation> review({
    required int reportId,
    required String result,
    required List<int> violationIds,
    required String? note,
  }) async {
    reviewCalls++;
    lastReviewResult = result;
    lastViolationIds = violationIds;
    finished = true;
    return const ReportVerificationMutation(
      message: 'Keputusan BK berhasil disimpan.',
      status: 'menunggu_pengesahan_wakil',
      statusLabel: 'Menunggu Pengesahan Wakil Kesiswaan',
      totalPoints: 15,
    );
  }

  @override
  Future<ReportVerificationMutation> approve({
    required int reportId,
    required String decision,
    required String? note,
  }) async {
    approveCalls++;
    lastApprovalDecision = decision;
    finished = true;
    return const ReportVerificationMutation(
      message: 'Poin siswa berhasil disahkan.',
      status: 'disahkan',
      statusLabel: 'Disahkan',
      totalPoints: 15,
    );
  }
}
