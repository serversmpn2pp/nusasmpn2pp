import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/learning_survey/data/learning_survey_remote_data_source.dart';
import 'package:nusa/features/learning_survey/domain/learning_survey.dart';
import 'package:nusa/features/learning_survey/presentation/learning_survey_view.dart';

void main() {
  testWidgets('survei native memvalidasi, mengirim, dan membuka nilai', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 700));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final remote = _FakeLearningSurveyRemoteDataSource();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          learningSurveyRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp(theme: AppTheme.light, home: const _SurveyHost()),
      ),
    );
    await tester.tap(find.text('Buka Survei'));
    await tester.pumpAndSettle();

    expect(find.text('Survei Pembelajaran'), findsOneWidget);
    expect(find.text('Matematika Mobile'), findsOneWidget);
    expect(find.text('0/2 terjawab'), findsOneWidget);

    await tester.tap(find.byKey(const Key('submit-learning-survey')));
    await tester.pumpAndSettle();
    expect(find.text('Semua pernyataan wajib dijawab.'), findsWidgets);
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.byKey(const Key('survey-answer-kejelasan_materi-4')),
      160,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(find.byKey(const Key('survey-answer-kejelasan_materi-4')));
    await tester.scrollUntilVisible(
      find.byKey(const Key('survey-answer-media_pembelajaran-5')),
      260,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.tap(
      find.byKey(const Key('survey-answer-media_pembelajaran-5')),
    );
    await tester.scrollUntilVisible(
      find.byKey(const Key('survey-suggestion')),
      260,
      scrollable: find.byType(Scrollable).last,
    );
    await tester.enterText(
      find.byKey(const Key('survey-suggestion')),
      'Pembelajaran sudah baik.',
    );

    await tester.tap(find.byKey(const Key('submit-learning-survey')));
    await tester.pumpAndSettle();
    expect(find.text('Kirim survei?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-submit-survey')));
    await tester.pumpAndSettle();

    expect(remote.submission?.answers, {
      'kejelasan_materi': 4,
      'media_pembelajaran': 5,
    });
    expect(remote.submission?.suggestion, 'Pembelajaran sudah baik.');
    expect(find.text('Nilai terbuka: true'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _SurveyHost extends StatefulWidget {
  const _SurveyHost();

  @override
  State<_SurveyHost> createState() => _SurveyHostState();
}

class _SurveyHostState extends State<_SurveyHost> {
  bool? _completed;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: Center(
      child: _completed == null
          ? FilledButton(
              onPressed: () async {
                final result = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(
                    builder: (context) => const LearningSurveyView(
                      assignmentId: 91,
                      semester: 'ganjil',
                    ),
                  ),
                );
                if (mounted) setState(() => _completed = result);
              },
              child: const Text('Buka Survei'),
            )
          : Text('Nilai terbuka: $_completed'),
    ),
  );
}

final class _FakeLearningSurveyRemoteDataSource
    implements LearningSurveyRemoteDataSource {
  LearningSurveySubmission? submission;

  @override
  Future<LearningSurveyPage> fetch(
    LearningSurveyArgs args,
  ) async => LearningSurveyPage(
    assignmentId: args.assignmentId,
    semester: args.semester,
    alreadyCompleted: false,
    context: const LearningSurveyContext(
      subjectName: 'Matematika Mobile',
      teacherName: 'Guru Mobile Uji',
      className: 'VIII.A',
      academicYearName: '2026/2027',
    ),
    questions: const [
      LearningSurveyQuestion(
        id: 1,
        code: 'kejelasan_materi',
        statement: 'Guru menjelaskan materi dengan jelas.',
        order: 1,
      ),
      LearningSurveyQuestion(
        id: 2,
        code: 'media_pembelajaran',
        statement: 'Media pembelajaran membantu pemahaman saya.',
        order: 2,
      ),
    ],
    options: const [
      LearningSurveyOption(value: 1, label: 'Sangat tidak sesuai'),
      LearningSurveyOption(value: 2, label: 'Tidak sesuai'),
      LearningSurveyOption(value: 3, label: 'Cukup sesuai'),
      LearningSurveyOption(value: 4, label: 'Sesuai'),
      LearningSurveyOption(value: 5, label: 'Sangat sesuai'),
    ],
    note: 'Jawaban digunakan sebagai umpan balik dan tidak memengaruhi nilai.',
  );

  @override
  Future<LearningSurveySubmitResult> submit(
    LearningSurveyArgs args,
    LearningSurveySubmission value,
  ) async {
    submission = value;
    return const LearningSurveySubmitResult(
      message: 'Survei berhasil dikirim.',
      alreadyCompleted: true,
    );
  }
}
