import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_early_warning/data/student_early_warning_remote_data_source.dart';
import 'package:nusa/features/student_early_warning/domain/student_early_warning.dart';
import 'package:nusa/features/student_early_warning/presentation/student_early_warning_detail_view.dart';
import 'package:nusa/features/student_early_warning/presentation/student_early_warning_list_view.dart';

void main() {
  test('domain membaca ringkasan, data pendukung, dan hak akses', () {
    final page = StudentEarlyWarningPage.fromJson(_pageJson());
    final detail = StudentEarlyWarningDetail.fromJson(_detailJson());

    expect(page.summary.active, 5);
    expect(page.summary.important, 2);
    expect(page.items.single.student.name, 'Siswa Peringatan Native');
    expect(page.items.single.supportingData.first.value, '4 kali');
    expect(detail.access.canProcess, isTrue);
    expect(detail.access.canManageAssistance, isTrue);
  });

  testWidgets('daftar peringatan rapi dan deteksi dapat dijalankan admin', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentEarlyWarningRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentEarlyWarningRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentEarlyWarningListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Peringatan Dini Siswa'), findsOneWidget);
    expect(find.text('Siswa Peringatan Native'), findsOneWidget);
    expect(
      find.byKey(const Key('student-warning-type-filter')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);

    await tester.tap(find.byKey(const Key('student-warning-process')));
    await tester.pumpAndSettle();
    expect(find.text('Jalankan deteksi peringatan?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('student-warning-confirm-process')));
    await tester.pumpAndSettle();

    expect(remote.processCalls, 1);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'detail membuka pendampingan dengan konteks peringatan dan siswa',
    (tester) async {
      tester.view.physicalSize = const Size(360, 760);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final remote = _FakeStudentEarlyWarningRemoteDataSource();
      final router = GoRouter(
        initialLocation: '/peringatan-dini-siswa/9',
        routes: [
          GoRoute(
            path: '/peringatan-dini-siswa/:id',
            builder: (context, state) => StudentEarlyWarningDetailView(
              warningId: int.parse(state.pathParameters['id']!),
            ),
          ),
          GoRoute(
            path: '/pendampingan-siswa/tambah',
            builder: (context, state) => Scaffold(
              body: Text(
                'Pendampingan ${state.uri.queryParameters['peringatan']} '
                'Siswa ${state.uri.queryParameters['siswa']}',
              ),
            ),
          ),
        ],
      );
      addTearDown(router.dispose);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            studentEarlyWarningRemoteDataSourceProvider.overrideWithValue(
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

      expect(find.text('Detail Peringatan Dini'), findsOneWidget);
      expect(find.text('Keterlambatan siswa berulang'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.byKey(const Key('student-warning-start-assistance')),
        350,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(
        find.byKey(const Key('student-warning-start-assistance')),
      );
      await tester.pumpAndSettle();

      expect(find.text('Pendampingan 9 Siswa 1'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}

class _FakeStudentEarlyWarningRemoteDataSource
    implements StudentEarlyWarningRemoteDataSource {
  int processCalls = 0;

  @override
  Future<StudentEarlyWarningPage> fetch({
    required String query,
    required String type,
    required String level,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async => StudentEarlyWarningPage.fromJson(_pageJson());

  @override
  Future<StudentEarlyWarningDetail> fetchDetail(int id) async =>
      StudentEarlyWarningDetail.fromJson(_detailJson());

  @override
  Future<StudentWarningProcessResult> process(int? academicYearId) async {
    processCalls++;
    return const StudentWarningProcessResult(
      message: 'Deteksi selesai: 1 baru, 2 diperbarui, dan 0 diselesaikan.',
      created: 1,
      updated: 2,
      resolved: 0,
    );
  }
}

Map<String, dynamic> _pageJson() => {
  'items': [_itemJson()],
  'ringkasan': {
    'total_aktif': 5,
    'penting': 2,
    'mendekati_sanksi': 1,
    'pola_berulang': 3,
    'sanksi_aktif': 1,
  },
  'pilihan': {
    'jenis': const [
      {'kode': 'sering_terlambat', 'label': 'Sering Terlambat'},
    ],
    'tingkat': const [
      {'kode': 'peringatan', 'label': 'Peringatan'},
      {'kode': 'penting', 'label': 'Penting'},
    ],
    'status': const [
      {'kode': 'aktif', 'label': 'Aktif'},
      {'kode': 'selesai', 'label': 'Selesai'},
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
    'jenis': 'semua',
    'tingkat': 'semua',
    'status': 'aktif',
    'tahun_pelajaran_id': 1,
    'kelas_id': null,
  },
  'paginasi': {
    'halaman': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {
    'cakupan_luas': true,
    'dapat_proses': true,
    'dapat_kelola_pendampingan': true,
  },
};

Map<String, dynamic> _detailJson() => {
  'peringatan': _itemJson(),
  'hak_akses': {
    'cakupan_luas': true,
    'dapat_proses': true,
    'dapat_kelola_pendampingan': true,
  },
};

Map<String, dynamic> _itemJson() => {
  'id': 9,
  'siswa': {
    'id': 1,
    'nama': 'Siswa Peringatan Native',
    'nis': 'NUSA-01',
    'nisn': '0088221001',
  },
  'kelas': {'id': 3, 'nama': 'VIII.A'},
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'guru_wali': {'id': 4, 'nama': 'Guru Wali Native', 'nip': '19800101'},
  'jenis': 'sering_terlambat',
  'label_jenis': 'Sering Terlambat',
  'tingkat': 'penting',
  'label_tingkat': 'Penting',
  'status': 'aktif',
  'label_status': 'Aktif',
  'judul': 'Keterlambatan siswa berulang',
  'pesan': 'Siswa terlambat 4 kali dengan total 45 menit.',
  'data_pendukung_ringkas': const [
    {'label': 'Jumlah terlambat', 'nilai': '4 kali'},
    {'label': 'Total keterlambatan', 'nilai': '45 menit'},
  ],
  'siklus': 1,
  'terdeteksi_pada': '2026-08-31T08:00:00+07:00',
  'terakhir_terdeteksi_pada': '2026-09-01T08:00:00+07:00',
  'diselesaikan_pada': null,
  'pendampingan_aktif': null,
  'sanksi': null,
};
