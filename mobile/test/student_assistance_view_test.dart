import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_assistance/data/student_assistance_remote_data_source.dart';
import 'package:nusa/features/student_assistance/domain/student_assistance.dart';
import 'package:nusa/features/student_assistance/presentation/student_assistance_create_view.dart';
import 'package:nusa/features/student_assistance/presentation/student_assistance_detail_view.dart';
import 'package:nusa/features/student_assistance/presentation/student_assistance_list_view.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

void main() {
  test('domain membaca ringkasan, siswa, peringatan, dan hak akses', () {
    final page = StudentAssistancePage.fromJson(_pageJson());
    final detail = StudentAssistanceDetail.fromJson(_detailJson());

    expect(page.summary.inProgress, 1);
    expect(page.items.single.student.name, 'Siswa Pendampingan Native');
    expect(page.items.single.warning?.typeLabel, 'Sering Terlambat');
    expect(detail.access.canManage, isTrue);
    expect(detail.officers.single.name, 'Guru BK Native');
  });

  testWidgets('daftar pendampingan rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentAssistanceRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentAssistanceRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentAssistanceListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Pendampingan Siswa'), findsOneWidget);
    expect(find.text('Siswa Pendampingan Native'), findsOneWidget);
    expect(
      find.byKey(const Key('student-assistance-status-filter')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('student-assistance-add')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('petugas dapat memulai pendampingan dari formulir native', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentAssistanceRemoteDataSource();
    final router = GoRouter(
      initialLocation: '/tambah',
      routes: [
        GoRoute(
          path: '/tambah',
          builder: (context, state) =>
              const StudentAssistanceCreateView(academicYearId: 1),
        ),
        GoRoute(
          path: '/pendampingan-siswa/:id',
          builder: (context, state) => const Scaffold(body: Text('Tersimpan')),
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentAssistanceRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('student-assistance-student')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Siswa Pendampingan Native · VIII.A').last);
    await tester.ensureVisible(
      find.byKey(const Key('student-assistance-officer')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-assistance-officer')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Guru BK Native · Guru BK').last);
    await tester.enterText(
      find.byKey(const Key('student-assistance-note')),
      'Siswa diajak menyusun target perubahan bersama guru BK.',
    );
    await tester.ensureVisible(
      find.byKey(const Key('student-assistance-submit')),
    );
    tester
        .widget<NusaPrimaryButton>(
          find.byKey(const Key('student-assistance-submit')),
        )
        .onPressed
        ?.call();
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(remote.lastPayload?.studentId, 1);
    expect(find.text('Tersimpan'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('hasil wajib dan penyelesaian memakai konfirmasi pengaman', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeStudentAssistanceRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentAssistanceRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const StudentAssistanceDetailView(assistanceId: 7),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('student-assistance-status')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('student-assistance-status')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Selesai').last);
    await tester.scrollUntilVisible(
      find.byKey(const Key('student-assistance-submit')),
      450,
      scrollable: find.byType(Scrollable).first,
    );
    tester
        .widget<NusaPrimaryButton>(
          find.byKey(const Key('student-assistance-submit')),
        )
        .onPressed
        ?.call();
    await tester.pumpAndSettle();

    expect(
      find.text(
        'Hasil penanganan wajib diisi sebelum pendampingan diselesaikan.',
      ),
      findsOneWidget,
    );
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const Key('student-assistance-result')),
      'Siswa dan orang tua menyepakati langkah perbaikan.',
    );
    await tester.scrollUntilVisible(
      find.byKey(const Key('student-assistance-submit')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('student-assistance-submit')));
    await tester.pumpAndSettle();
    expect(find.text('Selesaikan pendampingan?'), findsOneWidget);
    await tester.tap(
      find.byKey(const Key('student-assistance-confirm-submit')),
    );
    await tester.pumpAndSettle();

    expect(remote.updateCalls, 1);
    expect(remote.lastPayload?.status, 'selesai');
    expect(tester.takeException(), isNull);
  });
}

class _FakeStudentAssistanceRemoteDataSource
    implements StudentAssistanceRemoteDataSource {
  int createCalls = 0;
  int updateCalls = 0;
  StudentAssistancePayload? lastPayload;

  @override
  Future<StudentAssistancePage> fetch({
    required String query,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async => StudentAssistancePage.fromJson(_pageJson());

  @override
  Future<StudentAssistanceReference> fetchReference({
    required String query,
    required int? academicYearId,
    required int? classId,
  }) async => StudentAssistanceReference.fromJson({
    'siswa': [
      {
        'id': 1,
        'nama': 'Siswa Pendampingan Native',
        'nis': 'NUSA-01',
        'nisn': '0088111001',
        'kelas': {'id': 1, 'nama': 'VIII.A'},
        'memiliki_pendampingan_aktif': false,
      },
    ],
    'pegawai': _officers,
    'jenis_tindakan': _types,
    'tahun_pelajaran_id': academicYearId ?? 1,
    'kelas_id': classId,
    'kata_kunci': query,
  });

  @override
  Future<StudentAssistanceDetail> fetchDetail(int id) async =>
      StudentAssistanceDetail.fromJson(_detailJson());

  @override
  Future<StudentAssistanceDetail> create(
    StudentAssistancePayload payload,
  ) async {
    createCalls++;
    lastPayload = payload;
    return StudentAssistanceDetail.fromJson(_detailJson());
  }

  @override
  Future<StudentAssistanceDetail> update(
    int id,
    StudentAssistancePayload payload,
  ) async {
    updateCalls++;
    lastPayload = payload;
    return StudentAssistanceDetail.fromJson(
      _detailJson(completed: payload.status == 'selesai'),
    );
  }
}

const _types = [
  {'kode': 'konseling', 'label': 'Konseling Siswa'},
  {'kode': 'mediasi', 'label': 'Mediasi'},
];
const _statuses = [
  {'kode': 'dalam_proses', 'label': 'Dalam Proses'},
  {'kode': 'selesai', 'label': 'Selesai'},
];
const _officers = [
  {
    'id': 2,
    'nama': 'Guru BK Native',
    'nip': '198101012026081001',
    'jabatan': 'Guru BK',
  },
];

Map<String, dynamic> _pageJson() => {
  'items': [_itemJson()],
  'ringkasan': {'total': 3, 'dalam_proses': 1, 'selesai': 2},
  'pilihan': {
    'status': _statuses,
    'jenis_tindakan': _types,
    'tahun_pelajaran': [
      {'id': 1, 'nama': '2026/2027', 'aktif': true},
    ],
    'kelas': [
      {'id': 1, 'tahun_pelajaran_id': 1, 'nama': 'VIII.A', 'tingkat': 8},
    ],
  },
  'filter': {
    'kata_kunci': '',
    'status': 'dalam_proses',
    'tahun_pelajaran_id': 1,
    'kelas_id': null,
  },
  'paginasi': {
    'halaman': 1,
    'per_halaman': 15,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'dapat_kelola': true, 'cakupan_luas': true},
};

Map<String, dynamic> _detailJson({bool completed = false}) => {
  'pendampingan': _itemJson(completed: completed),
  'pilihan': {
    'jenis_tindakan': _types,
    'status': _statuses,
    'pegawai': _officers,
  },
  'hak_akses': {'dapat_kelola': true, 'cakupan_luas': true},
};

Map<String, dynamic> _itemJson({bool completed = false}) => {
  'id': 7,
  'siswa': {
    'id': 1,
    'nama': 'Siswa Pendampingan Native',
    'nis': 'NUSA-01',
    'nisn': '0088111001',
  },
  'kelas': {'id': 1, 'nama': 'VIII.A'},
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'peringatan': {
    'id': 4,
    'jenis': 'sering_terlambat',
    'label_jenis': 'Sering Terlambat',
    'judul': 'Keterlambatan berulang',
  },
  'petugas': _officers.first,
  'jenis_tindakan': 'konseling',
  'label_jenis_tindakan': 'Konseling Siswa',
  'tanggal_tindak_lanjut': '2026-08-31',
  'catatan': 'Siswa diajak menyusun target perubahan.',
  'status': completed ? 'selesai' : 'dalam_proses',
  'label_status': completed ? 'Selesai' : 'Dalam Proses',
  'hasil': completed ? 'Siswa menyepakati langkah perbaikan.' : null,
  'selesai_pada': completed ? '2026-08-31T10:00:00+07:00' : null,
  'diperbarui_pada': '2026-08-31T09:00:00+07:00',
};
