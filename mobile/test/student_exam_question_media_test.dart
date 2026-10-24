import 'dart:io';
import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_bank/application/question_bank_controller.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_preview_view.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/features/student_exam/presentation/exam_question_image.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_question_view.dart';

import 'support/question_image_http.dart';

Future<void> _finishImages(WidgetTester tester) async {
  for (var i = 0; i < 3; i++) {
    await tester.pump();
    await tester.runAsync(
      () => Future<void>.delayed(const Duration(milliseconds: 30)),
    );
  }
  await tester.pumpAndSettle();
}

Map<String, dynamic> _image(String name) => {
  'gambar': {'url': 'https://question.test/$name.png', 'alt': 'Diagram $name'},
};

StudentExamQuestion _question(String type) => StudentExamQuestion.fromJson({
  'id': 7,
  'nomor': 1,
  'jenis': type,
  'label_jenis': 'Pilihan Ganda',
  'stimulus': 'Perhatikan gambar gelombang berikut.',
  'stimulus_media': _image('stimulus'),
  'pertanyaan':
      'Jarak P ke Q menunjukkan satu panjang gelombang. Berapakah nilainya?',
  'media': {
    'tabel': {
      'judul': 'Data pengamatan',
      'baris': [
        ['Jarak P–Q', '20 cm'],
        ['Waktu', '2 s'],
      ],
    },
  },
  'pilihan': [
    {'kode': 'A', 'teks': '10 cm', 'media': _image('pilihan_A')},
    {'kode': 'B', 'teks': '20 cm'},
  ],
  'pernyataan': [
    {
      'nomor': '1',
      'teks': 'Panjang gelombang 20 cm.',
      'media': _image('pernyataan'),
    },
  ],
  'pasangan': [
    {'nomor': '1', 'kiri': 'Gelombang', 'media': _image('kiri')},
  ],
  'jawaban': {},
  'ragu': false,
});

void main() {
  late QuestionImageHttp http;
  void imageTestWidgets(String description, WidgetTesterCallback test) {
    testWidgets(description, (tester) async {
      http = QuestionImageHttp()..install();
      try {
        await test(tester);
      } finally {
        http.uninstall();
      }
    });
  }

  imageTestWidgets(
    'gambar utuh, zoom di dalam aplikasi, dan tidak mengubah jawaban',
    (tester) async {
      tester.view.physicalSize = const Size(360, 780);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var answers = 0;
      var doubts = 0;
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light,
          home: Scaffold(
            body: StudentExamQuestionView(
              question: _question('pilihan_ganda'),
              statusIndicator: const Text('Pratinjau'),
              controllerFor: (_, _) =>
                  throw StateError('Pilihan ganda tidak memakai controller'),
              onAnswerChanged: (_) => answers++,
              onDoubtChanged: (_) => doubts++,
              uploadingFile: false,
              onUploadFile: () =>
                  throw StateError('Tidak boleh membuka berkas'),
            ),
          ),
        ),
      );
      await _finishImages(tester);
      expect(find.text('Muat Ulang Gambar'), findsNothing);
      final inline = tester.widgetList<Image>(find.byType(Image));
      expect(inline.every((image) => image.fit == BoxFit.contain), isTrue);
      expect(inline.every((image) => image.image is NetworkImage), isTrue);
      final optionZoom = find.descendant(
        of: find.byWidgetPredicate(
          (widget) =>
              widget is ExamQuestionImage &&
              widget.image.url.endsWith('pilihan_A.png'),
        ),
        matching: find.text('Perbesar Gambar'),
      );
      await tester.ensureVisible(optionZoom);
      await tester.tap(optionZoom);
      await _finishImages(tester);
      expect(
        find.byKey(const Key('exam-question-image-viewer')),
        findsOneWidget,
      );
      final viewer = tester.widget<InteractiveViewer>(
        find.byType(InteractiveViewer),
      );
      expect(viewer.maxScale, 6);
      expect(viewer.panEnabled, isTrue);
      await tester.tap(find.byTooltip('Tutup gambar'));
      await tester.pumpAndSettle();
      expect(find.byType(InteractiveViewer), findsNothing);
      expect(answers, 0);
      expect(doubts, 0);
      expect(tester.takeException(), isNull);
    },
  );

  imageTestWidgets(
    'gambar gagal menampilkan status dan dapat dimuat ulang dari zoom',
    (tester) async {
      http.status = HttpStatus.notFound;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: ExamQuestionImage(
                image: StudentExamMedia.fromJson(_image('hilang')).image!,
              ),
            ),
          ),
        ),
      );
      await _finishImages(tester);
      expect(find.textContaining('HTTP 404'), findsOneWidget);
      await tester.tap(find.text('Perbesar Gambar'));
      await _finishImages(tester);
      expect(find.textContaining('HTTP 404').hitTestable(), findsOneWidget);
      http.status = HttpStatus.ok;
      await tester.tap(find.text('Muat Ulang Gambar').hitTestable());
      await _finishImages(tester);
      expect(find.textContaining('HTTP 404'), findsNothing);
      expect(find.byType(InteractiveViewer), findsOneWidget);
      expect(http.requests.length, greaterThan(1));
      expect(tester.takeException(), isNull);
    },
  );

  imageTestWidgets(
    'tabel lama dengan panjang baris berbeda tetap dapat dibaca',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: StudentExamQuestionMedia(
              media: StudentExamMedia.fromJson({
                'tabel': {
                  'baris': [
                    ['Besaran', 'Nilai'],
                    ['Frekuensi'],
                  ],
                },
              }),
            ),
          ),
        ),
      );
      expect(find.text('Frekuensi'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  imageTestWidgets(
    'pembesar ditutup ketika soal dilepas karena ditahan atau waktu habis',
    (tester) async {
      final visible = ValueNotifier(true);
      addTearDown(visible.dispose);
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: ValueListenableBuilder(
                valueListenable: visible,
                builder: (_, value, _) => value
                    ? ExamQuestionImage(
                        image: StudentExamMedia.fromJson(_image('dilepas'))
                            .image!,
                      )
                    : const Text('Sesi ditahan'),
              ),
            ),
          ),
        ),
      );
      await _finishImages(tester);
      await tester.ensureVisible(find.text('Perbesar Gambar'));
      await tester.tap(find.text('Perbesar Gambar'));
      await _finishImages(tester);
      expect(find.byType(InteractiveViewer), findsOneWidget);
      visible.value = false;
      await tester.pumpAndSettle();
      expect(find.byType(InteractiveViewer), findsNothing);
      expect(find.text('Sesi ditahan'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  for (final type in [
    'pilihan_ganda',
    'pilihan_ganda_kompleks',
    'benar_salah',
    'menjodohkan',
    'isian_singkat',
    'numerik',
    'uraian',
    'upload_file',
  ]) {
    imageTestWidgets(
      'pratinjau $type mendukung layar 320 px dan masukan lokal',
      (tester) async {
        tester.view.physicalSize = const Size(320, 740);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          ProviderScope(
            overrides: [
              questionBankPreviewProvider(7)
                  .overrideWith((ref) async => _question(type)),
            ],
            child: MaterialApp(
              theme: AppTheme.light,
              home: const QuestionBankPreviewView(questionId: 7),
            ),
          ),
        );
        await _finishImages(tester);
        await tester.drag(
          find.byKey(const Key('student-exam-question-7')),
          const Offset(0, -600),
        );
        await _finishImages(tester);
        expect(find.byType(StudentExamQuestionView), findsOneWidget);
        if (['isian_singkat', 'numerik', 'uraian'].contains(type)) {
          await tester.ensureVisible(find.byType(TextField));
          await tester.enterText(find.byType(TextField), '20');
          await tester.pump();
          expect(
            tester
                .widget<StudentExamQuestionView>(
                  find.byType(StudentExamQuestionView),
                )
                .question
                .answer
                .values,
            contains('20'),
          );
          await tester.tap(find.byTooltip('Ulangi Pratinjau'));
          await tester.pump();
          expect(
            tester.widget<TextField>(find.byType(TextField)).controller!.text,
            isEmpty,
          );
        }
        if (type == 'upload_file') {
          await tester.ensureVisible(find.text('Pilih dan Unggah Berkas'));
          await tester.tap(find.text('Pilih dan Unggah Berkas'));
          await tester.pump();
          expect(
            find.textContaining('Tidak membuka atau mengunggah'),
            findsOneWidget,
          );
        }
        expect(tester.takeException(), isNull);
      },
    );
  }

  imageTestWidgets('contoh pratinjau bergambar untuk pemeriksaan visual', (
    tester,
  ) async {
    await (FontLoader('Roboto')
          ..addFont(rootBundle.load('assets/fonts/roboto-regular.ttf'))
          ..addFont(rootBundle.load('assets/fonts/roboto-bold.ttf')))
        .load();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    http.bytes = await tester.runAsync(_diagramPng) ?? http.bytes;
    final boundaryKey = GlobalKey();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          questionBankPreviewProvider(7)
              .overrideWith((ref) async => _question('pilihan_ganda')),
        ],
        child: RepaintBoundary(
          key: boundaryKey,
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: AppTheme.light,
            home: const QuestionBankPreviewView(questionId: 7),
          ),
        ),
      ),
    );
    await _finishImages(tester);
    expect(find.text('Muat Ulang Gambar'), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.runAsync(() async {
      final boundary =
          boundaryKey.currentContext!.findRenderObject()!
              as RenderRepaintBoundary;
      final image = await boundary.toImage(pixelRatio: 2);
      final png = await image.toByteData(format: ui.ImageByteFormat.png);
      await File('build/question-preview-phone.png')
          .writeAsBytes(png!.buffer.asUint8List());
      image.dispose();
    });
  });
}

Future<Uint8List> _diagramPng() async {
  final recorder = ui.PictureRecorder();
  final canvas = Canvas(recorder)..drawColor(Colors.white, BlendMode.src);
  final axis = Paint()
    ..color = const Color(0xFF23496B)
    ..strokeWidth = 2;
  canvas.drawLine(const Offset(32, 138), const Offset(610, 138), axis);
  final path = Path()..moveTo(44, 138);
  for (var x = 44; x <= 580; x++) {
    path.lineTo(
      x.toDouble(),
      138 - 64 * math.sin((x - 44) * math.pi * 2 / 268),
    );
  }
  canvas.drawPath(
    path,
    Paint()
      ..color = const Color(0xFF1261A0)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4,
  );
  for (final (text, offset) in [
    ('P', const Offset(40, 154)),
    ('Q', const Offset(308, 154)),
    ('20 cm', const Offset(158, 210)),
  ]) {
    final painter = TextPainter(
      text: TextSpan(
        text: text,
        style: const TextStyle(
          fontFamily: 'Roboto',
          color: Color(0xFF123954),
          fontSize: 24,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    painter.paint(canvas, offset);
    painter.dispose();
  }
  canvas.drawLine(const Offset(44, 204), const Offset(312, 204), axis);
  final picture = recorder.endRecording();
  final image = await picture.toImage(640, 270);
  final data = await image.toByteData(format: ui.ImageByteFormat.png);
  image.dispose();
  picture.dispose();
  return data!.buffer.asUint8List();
}
