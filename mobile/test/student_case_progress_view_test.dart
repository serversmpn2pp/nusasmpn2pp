import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_case_progress/data/student_case_progress_remote_data_source.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';
import 'package:nusa/features/student_case_progress/presentation/student_case_detail_view.dart';
import 'package:nusa/features/student_case_progress/presentation/student_case_progress_view.dart';

void main() {
  test('model membaca ringkasan dan keputusan resmi dari respons API', () {
    final page = StudentCaseProgressPage.fromJson(_pageJson);
    final detail = StudentCaseDetail.fromJson(_detailJson);

    expect(page.student?.name, 'Siswa Progress Mobile');
    expect(page.summary.officialPoints, 15);
    expect(page.items.single.status.finalized, isTrue);
    expect(detail.decision.officialPoints, 15);
    expect(
      detail.decision.violations.single.name,
      'Datang terlambat tanpa alasan',
    );
    expect(detail.timeline.single.title, 'Poin disahkan Wakil Kesiswaan');
  });

  testWidgets(
    'siswa membuka kasus sendiri dan melihat detail publik tanpa overflow',
    (tester) async {
      tester.view.physicalSize = const Size(320, 700);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final remote = _FakeStudentCaseProgressRemoteDataSource();
      final router = GoRouter(
        initialLocation: '/progress-kasus-saya',
        routes: [
          GoRoute(
            path: '/progress-kasus-saya',
            builder: (context, state) => const StudentCaseProgressView(),
            routes: [
              GoRoute(
                path: ':id',
                builder: (context, state) => StudentCaseDetailView(
                  reportId: int.parse(state.pathParameters['id']!),
                ),
              ),
            ],
          ),
        ],
      );
      addTearDown(router.dispose);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            studentCaseProgressRemoteDataSourceProvider.overrideWithValue(
              remote,
            ),
          ],
          child: MaterialApp.router(
            theme: AppTheme.light,
            routerConfig: router,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Progress Kasus Saya'), findsOneWidget);
      expect(find.text('Siswa Progress Mobile'), findsOneWidget);
      expect(find.text('Pelanggaran berpoin disahkan'), findsOneWidget);
      expect(remote.listRequests, 1);
      expect(tester.takeException(), isNull);

      final card = find.byKey(const Key('student-case-41'));
      await tester.ensureVisible(card);
      await tester.tap(card);
      await tester.pumpAndSettle();

      expect(find.text('Detail Progress Kasus'), findsOneWidget);
      expect(remote.detailRequest, 41);
      expect(find.text('Catatan BK yang sangat rahasia'), findsNothing);
      expect(tester.takeException(), isNull);

      final scroll = find.descendant(
        of: find.byKey(const Key('student-case-detail-scroll')),
        matching: find.byType(Scrollable),
      );
      await tester.scrollUntilVisible(
        find.text('Datang terlambat tanpa alasan'),
        250,
        scrollable: scroll,
      );
      expect(find.text('15 poin'), findsOneWidget);
      expect(tester.takeException(), isNull);

      await tester.scrollUntilVisible(
        find.text('Poin disahkan Wakil Kesiswaan'),
        250,
        scrollable: scroll,
      );
      expect(find.text('Konseling Siswa'), findsOneWidget);

      await tester.scrollUntilVisible(
        find.textContaining('Rincian pemeriksaan internal'),
        250,
        scrollable: scroll,
      );
      expect(tester.takeException(), isNull);
    },
  );
}

final class _FakeStudentCaseProgressRemoteDataSource
    implements StudentCaseProgressRemoteDataSource {
  int listRequests = 0;
  int? detailRequest;

  @override
  Future<StudentCaseProgressPage> fetch({required int page}) async {
    listRequests++;
    return StudentCaseProgressPage.fromJson(_pageJson);
  }

  @override
  Future<StudentCaseDetail> fetchDetail(int id) async {
    detailRequest = id;
    return StudentCaseDetail.fromJson(_detailJson);
  }
}

final Map<String, dynamic> _pageJson = {
  'siswa': {
    'id': 9,
    'nama': 'Siswa Progress Mobile',
    'nis': '26001234',
    'nisn': '0011223344',
  },
  'tahun_pelajaran_aktif': {'id': 7, 'nama': '2026/2027'},
  'ringkasan': {'semua': 1, 'diproses': 0, 'pembinaan': 0, 'poin_resmi': 15},
  'items': [_reportJson],
  'paginasi': {
    'halaman': 1,
    'per_halaman': 10,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
};

final Map<String, dynamic> _detailJson = {
  'siswa': _pageJson['siswa'],
  'laporan': {
    ..._reportJson,
    'jenis_laporan': 'pelanggaran',
    'label_jenis_laporan': 'Laporan Pelanggaran',
    'waktu_kejadian': '07:15',
    'sumber': 'Laporan pegawai',
    'kronologi': 'Siswa datang setelah gerbang sekolah ditutup.',
  },
  'tahapan': [
    {'nomor': 1, 'label': 'Laporan diterima', 'selesai': true},
    {'nomor': 2, 'label': 'Pemeriksaan BK', 'selesai': true},
    {'nomor': 3, 'label': 'Keputusan sekolah', 'selesai': true},
    {'nomor': 4, 'label': 'Selesai', 'selesai': true},
  ],
  'keputusan': {
    'status': 'Pelanggaran berpoin disahkan',
    'deskripsi': 'Keputusan dan poin telah disahkan oleh sekolah.',
    'final': true,
    'total_poin_resmi': 15,
    'butir_pelanggaran': [
      {'nama': 'Datang terlambat tanpa alasan', 'poin': 15},
    ],
    'rekomendasi_belum_resmi': false,
  },
  'linimasa': [
    {
      'judul': 'Poin disahkan Wakil Kesiswaan',
      'deskripsi': 'Keputusan sekolah sudah ditetapkan.',
      'tanggal': '2026-09-09T08:30:00.000Z',
    },
  ],
  'tindak_lanjut': [
    {'jenis': 'Konseling Siswa', 'tanggal': '2026-09-10', 'status': 'Selesai'},
  ],
  'privasi': 'Rincian pemeriksaan internal dikelola oleh sekolah dan tidak ditampilkan pada akun siswa.',
};

final Map<String, dynamic> _reportJson = {
  'id': 41,
  'nomor_laporan': 'LP-2026-0041',
  'judul': 'Laporan kejadian siswa',
  'tanggal_kejadian': '2026-09-08',
  'tempat_kejadian': 'Gerbang sekolah',
  'kelas': {'id': 12, 'nama': 'VIII.A'},
  'tahun_pelajaran': {'id': 7, 'nama': '2026/2027'},
  'status': {
    'label': 'Pelanggaran berpoin disahkan',
    'deskripsi': 'Keputusan sekolah telah ditetapkan.',
    'warna': 'danger',
    'langkah': 4,
    'final': true,
    'status_penanganan': 'Selesai',
  },
  // Nilai ini sengaja tidak pernah masuk respons API sebenarnya.
  // UI juga tidak memiliki bidang untuk merender catatan internal.
};
