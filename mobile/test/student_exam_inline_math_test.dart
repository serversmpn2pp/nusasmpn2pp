import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_math_fork/flutter_math.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_bank/application/question_bank_controller.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_preview_view.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/features/student_exam/presentation/exam_math_text.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_question_view.dart';

const _cubicCm = 'cm\u00B3';
const _mixedFraction =
    r'\(1.437\frac{1}{3}\pi\) '
    '$_cubicCm';
const _capturePreview = bool.fromEnvironment('NUSA_CAPTURE_MATH_PREVIEW');

void main() {
  for (final type in ['pilihan_ganda', 'pilihan_ganda_kompleks']) {
    testWidgets('$type merender rumus tanpa mengubah kode jawaban', (
      tester,
    ) async {
      _phone(tester);
      Map<String, String>? answer;
      await _showQuestion(
        tester,
        StudentExamQuestion.fromJson({
          'id': 7,
          'nomor': 1,
          'jenis': type,
          'stimulus': r'Gunakan \(r = 7\) cm.',
          'pertanyaan': r'Hitung \(\sqrt{49}\) dan \(r^2\).',
          'pilihan': [
            {'kode': 'D', 'label': 'A', 'teks': _mixedFraction},
            {'kode': 'B', 'teks': '0'},
          ],
        }),
        onAnswerChanged: (value) => answer = value,
      );

      _expectMath(tester, 4);
      expect(find.textContaining(_cubicCm), findsOneWidget);
      expect(find.text('0'), findsOneWidget);
      final option = find.ancestor(
        of: find.textContaining(_cubicCm),
        matching: find.byType(InkWell),
      );
      await tester.ensureVisible(option);
      await tester.tap(option);
      expect(answer, {'0': 'D'});
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('pernyataan merender rumus blok dan mempertahankan teks', (
    tester,
  ) async {
    _phone(tester);
    Map<String, String>? answer;
    await _showQuestion(
      tester,
      StudentExamQuestion.fromJson({
        'id': 7,
        'jenis': 'benar_salah',
        'pertanyaan': 'Tentukan kebenaran pernyataan berikut.',
        'pernyataan': [
          {
            'nomor': '1',
            'teks': r'Berlaku \[\frac{1}{2}\times 8\div 2 = 2\] untuk x.',
          },
        ],
      }),
      onAnswerChanged: (value) => answer = value,
    );

    _expectMath(tester, 1);
    expect(tester.widget<Math>(find.byType(Math)).mathStyle, MathStyle.display);
    expect(find.textContaining('Berlaku \n'), findsOneWidget);
    expect(find.textContaining('\n untuk x.'), findsOneWidget);
    await tester.tap(find.text('Benar'));
    expect(answer, {'1': 'benar'});
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'pratinjau menjodohkan merender pecahan dan menyimpan nilai asli',
    (tester) async {
      _phone(tester);
      final previewKey = GlobalKey();
      if (_capturePreview) {
        tester.view.physicalSize = const Size(390, 844);
        await _loadPreviewFonts();
      }
      final question = StudentExamQuestion.fromJson({
        'id': 7,
        'nomor': 1,
        'jenis': 'menjodohkan',
        'label_jenis': 'Menjodohkan',
        'stimulus': r'Tabung dan setengah bola berjari-jari \(r=7\) cm.',
        'pertanyaan': 'Pasangkan volume dengan hasil yang sesuai.',
        'pilihan': [
          {'kode': 'B', 'label': 'A', 'teks': _mixedFraction},
          {
            'kode': 'C',
            'label': 'B',
            'teks':
                r'\(1.540\pi\) '
                '$_cubicCm',
          },
          {
            'kode': 'D',
            'label': 'C',
            'teks':
                r'\(686\pi\) '
                '$_cubicCm',
          },
          {
            'kode': 'A',
            'label': 'D',
            'teks':
                r'\(308\pi\) '
                '$_cubicCm',
          },
        ],
        'pasangan': [
          {
            'nomor': '1',
            'kiri':
                r'Volume tabung dengan \(r^2 = 49\) '
                'cm\u00B2',
          },
          {'nomor': '2', 'kiri': r'Volume bola dengan \(r = 7\) cm'},
          {'nomor': '3', 'kiri': 'Volume setengah bola'},
        ],
      });
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            questionBankPreviewProvider(7).overrideWith((_) async => question),
          ],
          child: RepaintBoundary(
            key: previewKey,
            child: MaterialApp(
              debugShowCheckedModeBanner: false,
              theme: AppTheme.light,
              home: const QuestionBankPreviewView(questionId: 7),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      _expectMath(tester, 7);
      if (_capturePreview) {
        await tester.runAsync(() async {
          final boundary =
              previewKey.currentContext!.findRenderObject()!
                  as RenderRepaintBoundary;
          final image = await boundary.toImage(pixelRatio: 2);
          final png = await image.toByteData(format: ui.ImageByteFormat.png);
          await File('build/pratinjau-rumus-android.png')
              .writeAsBytes(png!.buffer.asUint8List());
          image.dispose();
        });
      }
      final dropdown = find.byKey(const Key('student-exam-match-7-1'));
      await tester.ensureVisible(dropdown);
      await tester.tap(dropdown);
      await tester.pumpAndSettle();
      final item = find.byWidgetPredicate(
        (widget) =>
            widget is DropdownMenuItem<String> &&
            widget.value == _mixedFraction,
      );
      expect(
        find.descendant(of: item, matching: find.byType(Math)),
        findsOneWidget,
      );
      await tester.tap(item);
      await tester.pumpAndSettle();

      final preview = tester.widget<StudentExamQuestionView>(
        find.byType(StudentExamQuestionView),
      );
      expect(preview.question.answerPayload, {'1': _mixedFraction});
      expect(find.text('Pilihan A'), findsOneWidget);
      expect(question.answer, isEmpty);
      await tester.tap(find.byTooltip('Ulangi Pratinjau'));
      await tester.pumpAndSettle();
      expect(tester.state<FormFieldState<String>>(dropdown).value, isNull);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'rumus panjang dapat digeser dan rumus tidak valid tetap terbaca',
    (tester) async {
      _phone(tester);
      final longFormula = List.filled(20, r'\frac{x^2}{\sqrt{49}}').join('+');
      const invalidFormula = r'\(\unknowncommand{3}\)';
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.light,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: const TextScaler.linear(1.6)),
            child: child!,
          ),
          home: Scaffold(
            body: Column(
              children: [
                ExamMathText('Rumus: \\[$longFormula\\]'),
                const ExamMathText('Rumus lama: $invalidFormula'),
                const ExamMathText(r'Penanda belum lengkap: \(x + 9'),
                StudentExamQuestionMedia(
                  media: StudentExamMedia.fromJson({
                    'tabel': {
                      'baris': [
                        ['Jari-jari', r'\(\sqrt{49}\) cm'],
                      ],
                    },
                  }),
                ),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byType(Math), findsNWidgets(3));
      expect(find.text(invalidFormula), findsOneWidget);
      expect(find.text(r'Penanda belum lengkap: \(x + 9'), findsOneWidget);
      final viewport = find.byType(Scrollable).first;
      final position = tester.state<ScrollableState>(viewport).position;
      expect(position.maxScrollExtent, greaterThan(0));
      await tester.drag(viewport, const Offset(-90, 0));
      await tester.pumpAndSettle();
      expect(position.pixels, greaterThan(0));
      expect(tester.takeException(), isNull);
    },
  );
}

void _phone(WidgetTester tester) {
  tester.view.physicalSize = const Size(320, 740);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Future<void> _showQuestion(
  WidgetTester tester,
  StudentExamQuestion question, {
  required ValueChanged<Map<String, String>> onAnswerChanged,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      theme: AppTheme.light,
      home: Scaffold(
        body: StudentExamQuestionView(
          question: question,
          statusIndicator: const Text('Tersimpan'),
          controllerFor: (_, _) => throw StateError('Tidak memakai controller'),
          onAnswerChanged: onAnswerChanged,
          onDoubtChanged: (_) {},
          uploadingFile: false,
          onUploadFile: () {},
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void _expectMath(WidgetTester tester, int count) {
  final formulas = tester.widgetList<Math>(find.byType(Math));
  expect(formulas, hasLength(count));
  expect(formulas.map((formula) => formula.parseError), everyElement(isNull));
  expect(find.textContaining(r'\('), findsNothing);
  expect(find.textContaining(r'\['), findsNothing);
}

Future<void> _loadPreviewFonts() async {
  await (FontLoader('Roboto')
        ..addFont(rootBundle.load('assets/fonts/roboto-regular.ttf'))
        ..addFont(rootBundle.load('assets/fonts/roboto-bold.ttf')))
      .load();
  final manifest = jsonDecode(
    await rootBundle.loadString('FontManifest.json'),
  ) as List<dynamic>;
  for (final entry in manifest) {
    final family = entry['family'] as String;
    if (family != 'MaterialIcons' &&
        !family.startsWith('packages/flutter_math_fork/')) {
      continue;
    }
    final loader = FontLoader(family);
    for (final font in entry['fonts'] as List<dynamic>) {
      loader.addFont(rootBundle.load(font['asset'] as String));
    }
    await loader.load();
  }
}
