import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_bank/data/question_bank_remote_data_source.dart';
import 'package:nusa/features/question_bank/domain/question_bank.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_form_view.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_list_view.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_preview_view.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_detail_view.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_question_view.dart';
import 'package:nusa/features/student_exam/presentation/exam_question_image.dart';

import 'support/question_image_http.dart';

void main() {
  test('domain membaca soal, kunci, media, dan hak akses', () {
    final page = QuestionBankPage.fromJson(_pageJson());
    final detail = BankQuestionDetail.fromJson(_detailJson());

    expect(page.summary.ready, 1);
    expect(page.references.types, hasLength(8));
    expect(page.items.single.subject?.name, 'Matematika');
    expect(detail.answer.options[1].correct, isTrue);
    expect(detail.media.table?.rows, hasLength(2));
    expect(detail.media.formula?.latex, r'f = \frac{n}{t}');
    expect(detail.access.canArchive, isTrue);
    final mediaDetail = BankQuestionDetail.fromJson(_detailWithImagesJson());
    expect(mediaDetail.stimulusMedia.image?.url, contains('/stimulus.png'));
    expect(
      mediaDetail.answer.options.first.media.image?.url,
      contains('/pilihan_A.png'),
    );
    expect(
      StudentExamMedia.fromJson(mediaDetail.stimulusMedia.toJson()).image?.url,
      mediaDetail.stimulusMedia.image?.url,
    );
  });

  testWidgets('daftar Bank Soal rapi pada layar Android sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          questionBankRemoteDataSourceProvider.overrideWithValue(
            _FakeQuestionBankRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const QuestionBankListView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Bank Soal'), findsOneWidget);
    expect(find.text('SOAL-CBT-001'), findsOneWidget);
    expect(
      find.byKey(const Key('question-bank-context-filter')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('question-bank-add')), findsOneWidget);
    expect(find.byKey(const Key('question-bank-preview-7')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  for (final fromDetail in [false, true]) {
    testWidgets(
      'tombol pratinjau dari ${fromDetail ? 'detail' : 'daftar'} memakai tampilan siswa tanpa menyimpan',
      (tester) async {
        tester.view.physicalSize = const Size(360, 780);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final remote = _FakeQuestionBankRemoteDataSource();
        final router = GoRouter(
          initialLocation: fromDetail ? '/bank-soal/7' : '/bank-soal',
          routes: [
            GoRoute(
              path: '/bank-soal',
              builder: (_, _) => const QuestionBankListView(),
            ),
            GoRoute(
              path: '/bank-soal/:id',
              builder: (_, _) => const QuestionBankDetailView(questionId: 7),
            ),
            GoRoute(
              path: '/bank-soal/:id/pratinjau',
              builder: (_, _) => const QuestionBankPreviewView(questionId: 7),
            ),
          ],
        );
        addTearDown(router.dispose);
        await tester.pumpWidget(
          ProviderScope(
            overrides: [
              questionBankRemoteDataSourceProvider.overrideWithValue(remote),
            ],
            child: MaterialApp.router(
              theme: AppTheme.light,
              routerConfig: router,
            ),
          ),
        );
        await tester.pumpAndSettle();
        final button = find.byKey(
          Key(fromDetail ? 'question-bank-preview' : 'question-bank-preview-7'),
        );
        await tester.ensureVisible(button);
        await tester.tap(button);
        await tester.pumpAndSettle();
        expect(find.text('Pratinjau Siswa'), findsOneWidget);
        expect(find.byType(StudentExamQuestionView), findsOneWidget);
        expect(remote.previewCalls, 1);
        await tester.tap(find.text('Hertz'));
        await tester.pumpAndSettle();
        expect(
          tester
              .widget<StudentExamQuestionView>(
                find.byType(StudentExamQuestionView),
              )
              .question
              .answer,
          {'0': 'B'},
        );
        await tester.ensureVisible(find.byType(SwitchListTile));
        await tester.tap(find.byType(SwitchListTile));
        await tester.pumpAndSettle();
        expect(
          tester
              .widget<StudentExamQuestionView>(
                find.byType(StudentExamQuestionView),
              )
              .question
              .doubtful,
          isTrue,
        );
        await tester.tap(find.byTooltip('Ulangi Pratinjau'));
        await tester.pumpAndSettle();
        final reset = tester
            .widget<StudentExamQuestionView>(
              find.byType(StudentExamQuestionView),
            )
            .question;
        expect(reset.answer, isEmpty);
        expect(reset.doubtful, isFalse);
        expect(remote.createCalls, 0);
        expect(remote.updateCalls, 0);
        expect(remote.previewCalls, 1);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'detail memuat gambar stimulus, soal dan pilihan pada layar sempit',
    (tester) async {
      tester.view.physicalSize = const Size(320, 740);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final http = QuestionImageHttp()..install();
      addTearDown(http.uninstall);
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            questionBankRemoteDataSourceProvider.overrideWithValue(
              _FakeQuestionBankRemoteDataSource(images: true),
            ),
          ],
          child: MaterialApp(
            theme: AppTheme.light,
            home: const QuestionBankDetailView(questionId: 7),
          ),
        ),
      );
      await _finishImages(tester);
      await tester.drag(find.byType(ListView), const Offset(0, -400));
      await _finishImages(tester);
      expect(find.byType(ExamQuestionImage), findsWidgets);
      await tester.drag(find.byType(ListView), const Offset(0, -450));
      await _finishImages(tester);
      expect(
        http.requests.map((url) => url.path),
        containsAll(['/stimulus.png', '/utama.png', '/pilihan_A.png']),
      );
      expect(find.text('Muat Ulang Gambar'), findsNothing);
      http.uninstall();
    },
  );

  testWidgets('editor menyesuaikan bentuk jawaban tanpa overflow', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          questionBankRemoteDataSourceProvider.overrideWithValue(
            _FakeQuestionBankRemoteDataSource(),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const QuestionBankFormView(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('question-form-type')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Benar / Salah').last);
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView), const Offset(0, -650));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('question-statement-0')), findsOneWidget);

    await tester.drag(find.byType(ListView), const Offset(0, 650));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('question-form-type')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Menjodohkan').last);
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView), const Offset(0, -650));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('question-pair-left-0')), findsOneWidget);
    expect(find.byKey(const Key('question-pair-right-0')), findsOneWidget);
    await tester.dragUntilVisible(
      find.text('Tambah pengecoh'),
      find.byType(ListView),
      const Offset(0, -300),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tambah pengecoh'));
    await tester.pumpAndSettle();
    expect(
      find.byKey(const Key('question-matching-distractor-0')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('guru dapat menyimpan soal siap dari editor native', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final remote = _FakeQuestionBankRemoteDataSource();
    final router = GoRouter(
      initialLocation: '/bank-soal/tambah',
      routes: [
        GoRoute(
          path: '/bank-soal/tambah',
          builder: (context, state) => const QuestionBankFormView(),
        ),
        GoRoute(
          path: '/bank-soal/:id',
          builder: (context, state) =>
              const Scaffold(body: Center(child: Text('Soal Tersimpan'))),
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          questionBankRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('question-form-context')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Matematika · Kelas 8').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('question-form-difficulty')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sedang').last);
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView), const Offset(0, -650));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('question-form-question')),
      'Berapakah hasil 2 + 2?',
    );
    tester.testTextInput.hide();
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView), const Offset(0, -550));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('question-option-0')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('question-option-0')), 'Tiga');
    await tester.enterText(find.byKey(const Key('question-option-1')), 'Empat');
    await tester.ensureVisible(
      find.byKey(const Key('question-option-choice-1')),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('question-option-choice-1')));
    tester.testTextInput.hide();
    await tester.pump();
    tester
        .widget<FilledButton>(find.byKey(const Key('question-form-save-ready')))
        .onPressed
        ?.call();
    await tester.pumpAndSettle();

    expect(remote.createCalls, 1);
    expect(remote.lastValue?.payload['jenis_soal'], 'pilihan_ganda');
    expect(remote.lastValue?.payload['mata_pelajaran_id'], 3);
    expect(remote.lastValue?.payload['tingkat'], 8);
    expect(remote.lastValue?.payload['tingkat_kesulitan'], 'sedang');
    expect(remote.lastValue?.payload.containsKey('skor_maksimal'), isFalse);
    expect(remote.lastValue?.payload['kunci_pg'], 'B');
    expect(remote.lastValue?.payload['aksi'], 'simpan_siap');
    expect(find.text('Soal Tersimpan'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _finishImages(WidgetTester tester) async {
  for (var i = 0; i < 3; i++) {
    await tester.pump();
    await tester.runAsync(
      () => Future<void>.delayed(const Duration(milliseconds: 30)),
    );
  }
  await tester.pumpAndSettle();
}

class _FakeQuestionBankRemoteDataSource
    implements QuestionBankRemoteDataSource {
  _FakeQuestionBankRemoteDataSource({this.images = false});
  final bool images;
  int createCalls = 0;
  int updateCalls = 0;
  QuestionFormValue? lastValue;

  @override
  Future<QuestionBankPage> fetch({
    required String query,
    required int? subjectId,
    required String grade,
    required String type,
    required String status,
    required int page,
  }) async => QuestionBankPage.fromJson(_pageJson());

  @override
  Future<BankQuestionDetail> detail(int id) async =>
      BankQuestionDetail.fromJson(
        images ? _detailWithImagesJson() : _detailJson(),
      );

  int previewCalls = 0;
  @override
  Future<StudentExamQuestion> preview(int id) async {
    previewCalls++;
    return StudentExamQuestion.fromJson({
      'id': id,
      'nomor': 1,
      'jenis': 'pilihan_ganda',
      'label_jenis': 'Pilihan Ganda',
      'pertanyaan': 'Satuan frekuensi adalah ....',
      'pilihan': [
        {'kode': 'A', 'teks': 'Meter'},
        {'kode': 'B', 'teks': 'Hertz'},
      ],
      'jawaban': {},
      'ragu': false,
    });
  }

  @override
  Future<BankQuestionDetail> create(QuestionFormValue value) async {
    createCalls++;
    lastValue = value;
    return BankQuestionDetail.fromJson(_detailJson());
  }

  @override
  Future<BankQuestionDetail> update(int id, QuestionFormValue value) async {
    updateCalls++;
    lastValue = value;
    return BankQuestionDetail.fromJson(_detailJson());
  }

  @override
  Future<void> archive(int id) async {}
}

Map<String, dynamic> _pageJson() => {
  'ringkasan': {'total': 2, 'siap': 1, 'draft': 1, 'arsip': 0},
  'items': [_questionJson()],
  'referensi': {
    'konteks': [
      {
        'kunci': '3-8',
        'mata_pelajaran_id': 3,
        'tingkat': 8,
        'nama_mata_pelajaran': 'Matematika',
        'label': 'Matematika · Kelas 8',
      },
    ],
    'jenis_soal': const [
      {'kode': 'pilihan_ganda', 'label': 'Pilihan Ganda'},
      {'kode': 'pilihan_ganda_kompleks', 'label': 'Pilihan Ganda Kompleks'},
      {'kode': 'benar_salah', 'label': 'Benar / Salah'},
      {'kode': 'menjodohkan', 'label': 'Menjodohkan'},
      {'kode': 'isian_singkat', 'label': 'Isian Singkat'},
      {'kode': 'uraian', 'label': 'Uraian'},
      {'kode': 'numerik', 'label': 'Numerik'},
      {'kode': 'upload_file', 'label': 'Unggah Berkas'},
    ],
    'tingkat_kesulitan': const [
      {'kode': 'mudah', 'label': 'Mudah'},
      {'kode': 'sedang', 'label': 'Sedang'},
      {'kode': 'sulit', 'label': 'Sulit'},
      {'kode': 'sangat_sulit', 'label': 'Sangat Sulit'},
    ],
    'kategori': const [
      {'kode': 'lots', 'label': 'LOTS'},
      {'kode': 'mots', 'label': 'MOTS'},
      {'kode': 'hots', 'label': 'HOTS'},
    ],
    'status': const [
      {'kode': 'draft', 'label': 'Draf'},
      {'kode': 'siap', 'label': 'Siap'},
      {'kode': 'arsip', 'label': 'Arsip'},
    ],
  },
  'filter': {
    'kata_kunci': '',
    'mata_pelajaran_id': null,
    'tingkat': 'semua',
    'jenis_soal': 'semua',
    'status': 'semua',
  },
  'paginasi': {
    'halaman': 1,
    'halaman_terakhir': 1,
    'total': 1,
    'ada_halaman_berikutnya': false,
  },
  'hak_akses': {'dapat_kelola': true},
};

Map<String, dynamic> _questionJson() => {
  'id': 7,
  'kode': 'SOAL-CBT-001',
  'mata_pelajaran': {'id': 3, 'kode': 'MTK', 'nama': 'Matematika'},
  'tingkat': 8,
  'jenis_soal': 'pilihan_ganda',
  'label_jenis_soal': 'Pilihan Ganda',
  'tingkat_kesulitan': 'sedang',
  'label_tingkat_kesulitan': 'Sedang',
  'kategori': 'mots',
  'label_kategori': 'MOTS',
  'topik': 'Getaran',
  'materi': 'Frekuensi',
  'pertanyaan': 'Satuan frekuensi adalah ....',
  'skor_maksimal': 1,
  'status': 'siap',
  'label_status': 'Siap',
  'aktif': true,
  'jumlah_pemakaian': 2,
  'diperbarui_pada': '2026-09-03T08:00:00+07:00',
};

Map<String, dynamic> _detailJson() => {
  ..._questionJson(),
  'tahun_pelajaran': {'id': 1, 'nama': '2026/2027'},
  'tujuan_pembelajaran': 'Memahami frekuensi.',
  'stimulus': 'Perhatikan hasil pengamatan berikut.',
  'pembahasan': 'Frekuensi dinyatakan dalam Hertz.',
  'jawaban': {
    'opsi': [
      {'kode': 'A', 'teks': 'Meter', 'benar': false},
      {'kode': 'B', 'teks': 'Hertz', 'benar': true},
      {'kode': 'C', 'teks': 'Sekon', 'benar': false},
      {'kode': 'D', 'teks': 'Newton', 'benar': false},
    ],
    'pernyataan': [],
    'pasangan': [],
    'pengecoh_menjodohkan': [],
    'kunci_teks': null,
    'rubrik': null,
  },
  'media': {
    'gambar': null,
    'tabel': {
      'judul': 'Hasil pengamatan',
      'baris': [
        ['Besaran', 'Nilai'],
        ['Frekuensi', '2 Hz'],
      ],
    },
    'rumus': {'latex': r'f = \frac{n}{t}', 'keterangan': 'Rumus frekuensi'},
  },
  'dibuat_oleh': 'Administrator',
  'hak_akses': {'dapat_kelola': true, 'dapat_arsipkan': true},
};

Map<String, dynamic> _detailWithImagesJson() {
  final json = _detailJson();
  Map<String, dynamic> image(String name) => {
    'gambar': {
      'url': 'https://question.test/$name.png',
      'alt': 'Diagram $name',
    },
  };
  json['stimulus_media'] = image('stimulus');
  json['media'] = image('utama');
  ((json['jawaban'] as Map)['opsi'] as List).first['media'] = image(
    'pilihan_A',
  );
  return json;
}
