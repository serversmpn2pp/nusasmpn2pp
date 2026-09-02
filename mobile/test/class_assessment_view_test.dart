import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_assessment/data/class_assessment_remote_data_source.dart';
import 'package:nusa/features/class_assessment/domain/class_assessment.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_form_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_list_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_questions_view.dart';

void main() {
  test('domain membaca ringkasan, kelas tujuan, dan soal asesmen', () {
    final page = ClassAssessmentPage.fromJson(_pageJson());
    final detail = ClassAssessmentDetail.fromJson(_detailJson());
    final questions = AssessmentQuestions.fromJson(_questionsJson());

    expect(page.summary.scheduled, 1);
    expect(page.items.single.classes, ['VIII.A']);
    expect(detail.classes.single.component, 'Sumatif Bab Persamaan');
    expect(questions.questions.single.weight, 2.5);
  });

  testWidgets('daftar asesmen rapi pada layar Android sempit', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          classAssessmentRemoteDataSourceProvider.overrideWithValue(
            _FakeClassAssessmentRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const ClassAssessmentListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Asesmen Kelas'), findsOneWidget);
    expect(find.text('Sumatif Bab Persamaan'), findsOneWidget);
    expect(
      find.byKey(const Key('class-assessment-status-filter')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('guru membuat asesmen dengan kelas peserta', (tester) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeClassAssessmentRemoteDataSource();
    final router = GoRouter(
      initialLocation: '/form',
      routes: [
        GoRoute(
          path: '/form',
          builder: (context, state) => const ClassAssessmentFormView(),
        ),
        GoRoute(
          path: '/asesmen-kelas/:id',
          builder: (context, state) =>
              const Scaffold(body: Center(child: Text('Asesmen tersimpan'))),
        ),
      ],
    );
    addTearDown(router.dispose);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          classAssessmentRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const Key('class-assessment-name')),
      'Sumatif Mobile Baru',
    );
    tester
        .widget<CheckboxListTile>(
          find.byKey(const Key('class-assessment-class-3')),
        )
        .onChanged
        ?.call(true);
    await tester.pumpAndSettle();
    tester
        .widget<FilledButton>(find.byKey(const Key('class-assessment-save')))
        .onPressed
        ?.call();
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(remote.lastPayload?.classes.single.classId, 3);
    expect(remote.lastPayload?.classes.single.componentId, 'baru');
    expect(find.text('Asesmen tersimpan'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('guru menyimpan urutan dan bobot soal asesmen', (tester) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeClassAssessmentRemoteDataSource();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          classAssessmentRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const ClassAssessmentQuestionsView(assessmentId: 9),
        ),
      ),
    );
    await tester.pumpAndSettle();

    tester
        .widget<FilledButton>(
          find.byKey(const Key('class-assessment-save-questions')),
        )
        .onPressed
        ?.call();
    await tester.pumpAndSettle();

    expect(remote.saveQuestionCalls, 1);
    expect(remote.lastQuestions.single.id, 21);
    expect(remote.lastQuestions.single.weight, 2.5);
    expect(tester.takeException(), isNull);
  });
}

class _FakeClassAssessmentRemoteDataSource
    implements ClassAssessmentRemoteDataSource {
  int createCalls = 0;
  int saveQuestionCalls = 0;
  ClassAssessmentPayload? lastPayload;
  List<AssessmentQuestionPayload> lastQuestions = [];

  @override
  Future<ClassAssessmentPage> fetch({
    required String query,
    required String status,
    required int page,
  }) async => ClassAssessmentPage.fromJson(_pageJson());

  @override
  Future<ClassAssessmentDetail> detail(int id) async =>
      ClassAssessmentDetail.fromJson(_detailJson());

  @override
  Future<ClassAssessmentDetail> create(ClassAssessmentPayload payload) async {
    createCalls++;
    lastPayload = payload;
    return ClassAssessmentDetail.fromJson(_detailJson());
  }

  @override
  Future<ClassAssessmentDetail> update(
    int id,
    ClassAssessmentPayload payload,
  ) async {
    lastPayload = payload;
    return ClassAssessmentDetail.fromJson(_detailJson());
  }

  @override
  Future<void> deactivate(int id) async {}

  @override
  Future<AssessmentQuestions> questions(int id) async =>
      AssessmentQuestions.fromJson(_questionsJson());

  @override
  Future<AssessmentQuestions> saveQuestions(
    int id,
    List<AssessmentQuestionPayload> questions,
  ) async {
    saveQuestionCalls++;
    lastQuestions = questions;
    return AssessmentQuestions.fromJson(_questionsJson());
  }
}

Map<String, dynamic> _assessmentJson() => {
  'id': 9,
  'nama': 'Sumatif Bab Persamaan',
  'mata_pelajaran': 'Matematika',
  'tahun_pelajaran': '2026/2027',
  'semester': 'ganjil',
  'tingkat': 8,
  'tanggal_mulai': '2026-09-10T08:00:00+07:00',
  'tanggal_selesai': '2026-09-10T09:00:00+07:00',
  'durasi_menit': 40,
  'target_soal': 10,
  'jumlah_soal': 1,
  'jumlah_peserta': 30,
  'kelas': ['VIII.A'],
  'status': 'terjadwal',
  'label_status': 'Terjadwal',
  'siap_soal': false,
};

Map<String, dynamic> _referencesJson() => {
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'kelompok_pengajaran': [
    {
      'key': '2-4-8',
      'mata_pelajaran_id': 4,
      'mata_pelajaran': 'Matematika',
      'pegawai': 'Guru Matematika',
      'tingkat': 8,
      'kkm': 78,
      'label': 'Matematika - Kelas 8',
      'kelas': [
        {
          'kelas_id': 3,
          'nama': 'VIII.A',
          'guru_mata_pelajaran_id': 5,
          'komponen': [
            {'id': 7, 'nama': 'Sumatif Lama', 'semester': 'ganjil'},
          ],
        },
      ],
    },
  ],
  'status': [
    {'kode': 'draft', 'label': 'Draft'},
    {'kode': 'terjadwal', 'label': 'Terjadwal'},
    {'kode': 'berlangsung', 'label': 'Berlangsung'},
    {'kode': 'selesai', 'label': 'Selesai'},
  ],
};

Map<String, dynamic> _pageJson() => {
  'ringkasan': {'total': 1, 'draft': 0, 'terjadwal': 1, 'berlangsung': 0},
  'items': [_assessmentJson()],
  'referensi': _referencesJson(),
  'filter': {'kata_kunci': '', 'status': 'semua'},
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
};

Map<String, dynamic> _detailJson() => {
  ..._assessmentJson(),
  'kode': 'AK-20260910-01',
  'kkm': 78,
  'acak_soal': true,
  'acak_jawaban': true,
  'batasi_satu_perangkat': false,
  'deteksi_pindah_tab': true,
  'tampilkan_hasil': false,
  'petunjuk': 'Kerjakan dengan teliti.',
  'dibuat_oleh': 'Guru Matematika',
  'kelompok_pengajaran': '2-4-8',
  'kelas_tujuan': [
    {
      'kelas_id': 3,
      'nama': 'VIII.A',
      'komponen_nilai_id': 8,
      'komponen_nilai': 'Sumatif Bab Persamaan',
      'jumlah_peserta': 30,
    },
  ],
  'referensi': _referencesJson(),
  'hak_akses': {'dapat_kelola': true, 'dapat_nonaktifkan': true},
};

Map<String, dynamic> _questionsJson() => {
  'asesmen': _assessmentJson(),
  'soal': [
    {
      'id': 21,
      'kode': 'SOAL-21',
      'jenis_soal': 'pilihan_ganda',
      'label_jenis_soal': 'Pilihan Ganda',
      'tingkat_kesulitan': 'sedang',
      'label_tingkat_kesulitan': 'Sedang',
      'topik': 'Persamaan',
      'materi': 'Aljabar',
      'pertanyaan': 'Tentukan nilai x.',
      'skor_maksimal': 2.5,
      'dipilih': true,
      'bobot': 2.5,
      'nomor_urut': 1,
      'dapat_dipilih': true,
      'gambar_url': null,
    },
  ],
  'referensi': {
    'jenis_soal': [
      {'kode': 'pilihan_ganda', 'label': 'Pilihan Ganda'},
    ],
    'tingkat_kesulitan': [
      {'kode': 'sedang', 'label': 'Sedang'},
    ],
  },
  'hak_akses': {'dapat_ubah': true},
};
