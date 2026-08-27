import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/survey_monitoring/data/survey_monitoring_remote_data_source.dart';
import 'package:nusa/features/survey_monitoring/domain/survey_monitoring.dart';
import 'package:nusa/features/survey_monitoring/presentation/survey_monitoring_detail_view.dart';
import 'package:nusa/features/survey_monitoring/presentation/survey_monitoring_view.dart';

void main() {
  testWidgets('monitoring per guru mapel rapi dan membuka rincian anonim', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await _pumpApp(tester, _FakeSurveyMonitoringRemoteDataSource());

    expect(find.text('Monitoring Survei'), findsOneWidget);
    expect(
      find.byKey(const Key('survey-monitoring-year-filter')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('survey-monitoring-search')), findsOneWidget);
    expect(find.text('Guru Matematika Mobile'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.ensureVisible(find.byKey(const Key('survey-monitoring-11')));
    await tester.tap(find.byKey(const Key('survey-monitoring-11')));
    await tester.pumpAndSettle();

    expect(find.text('Rincian Survei'), findsOneWidget);
    expect(find.text('Anonim'), findsOneWidget);
    expect(find.text('Rincian Pernyataan'), findsOneWidget);
    expect(find.text('Identitas siswa tidak pernah tampil.'), findsNothing);
    final detailScroll = find.descendant(
      of: find.byKey(
        const PageStorageKey<String>('survey-monitoring-detail-scroll'),
      ),
      matching: find.byType(Scrollable),
    );
    await tester.scrollUntilVisible(
      find.text('Penjelasan guru sudah mudah dipahami.'),
      300,
      scrollable: detailScroll.first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Penjelasan guru sudah mudah dipahami.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('rincian di bawah lima responden tetap terkunci', (tester) async {
    final remote = _FakeSurveyMonitoringRemoteDataSource();
    await _pumpApp(
      tester,
      remote,
      initialLocation: '/monitoring-survei/12?semester=ganjil',
    );

    expect(
      find.byKey(const Key('survey-monitoring-results-locked')),
      findsOneWidget,
    );
    expect(find.text('2/5'), findsOneWidget);
    expect(find.text('Saran yang belum boleh terbuka.'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpApp(
  WidgetTester tester,
  SurveyMonitoringRemoteDataSource remote, {
  String initialLocation = '/monitoring-survei',
}) async {
  final router = GoRouter(
    initialLocation: initialLocation,
    routes: [
      GoRoute(
        path: '/monitoring-survei',
        builder: (context, state) => const SurveyMonitoringView(),
        routes: [
          GoRoute(
            path: ':id',
            builder: (context, state) => SurveyMonitoringDetailView(
              assignmentId: int.parse(state.pathParameters['id']!),
              semester: state.uri.queryParameters['semester'] ?? 'ganjil',
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
        surveyMonitoringRemoteDataSourceProvider.overrideWithValue(remote),
      ],
      child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
    ),
  );
  await tester.pumpAndSettle();
}

final class _FakeSurveyMonitoringRemoteDataSource
    implements SurveyMonitoringRemoteDataSource {
  @override
  Future<SurveyMonitoringPage> fetch({
    int? academicYearId,
    required String semester,
    required String status,
    required String query,
    required int page,
    int perPage = 15,
  }) async => SurveyMonitoringPage(
    items: [_assignment(id: 11, respondents: 5, open: true, average: 4.2)],
    summary: const SurveyMonitoringSummary(
      assignments: 1,
      responseTarget: 6,
      responses: 5,
      openResults: 1,
    ),
    academicYears: const [
      SurveyMonitoringAcademicYear(id: 7, name: '2026/2027', active: true),
    ],
    filter: SurveyMonitoringFilter(
      academicYearId: academicYearId ?? 7,
      semester: semester,
      status: status,
      query: query,
    ),
    pagination: const SurveyMonitoringPagination(
      page: 1,
      total: 1,
      hasNextPage: false,
    ),
    minimumRespondents: 5,
  );

  @override
  Future<SurveyMonitoringDetail> fetchDetail({
    required int assignmentId,
    required String semester,
  }) async {
    final open = assignmentId == 11;
    return SurveyMonitoringDetail(
      assignment: _assignment(
        id: assignmentId,
        respondents: open ? 5 : 2,
        open: open,
        average: open ? 4.2 : null,
      ),
      semester: semester,
      minimumRespondents: 5,
      scale: const [
        SurveyMonitoringScale(value: 1, label: 'Sangat tidak sesuai'),
        SurveyMonitoringScale(value: 5, label: 'Sangat sesuai'),
      ],
      questions: open
          ? const [
              SurveyMonitoringQuestion(
                code: 'kejelasan_materi',
                statement: 'Guru menjelaskan materi dengan jelas.',
                order: 1,
                answerCount: 5,
                average: 4.2,
                distribution: [
                  SurveyMonitoringDistribution(
                    value: 1,
                    count: 0,
                    percentage: 0,
                  ),
                  SurveyMonitoringDistribution(
                    value: 2,
                    count: 0,
                    percentage: 0,
                  ),
                  SurveyMonitoringDistribution(
                    value: 3,
                    count: 1,
                    percentage: 20,
                  ),
                  SurveyMonitoringDistribution(
                    value: 4,
                    count: 2,
                    percentage: 40,
                  ),
                  SurveyMonitoringDistribution(
                    value: 5,
                    count: 2,
                    percentage: 40,
                  ),
                ],
              ),
            ]
          : const [],
      suggestions: open
          ? [
              SurveyMonitoringSuggestion(
                text: 'Penjelasan guru sudah mudah dipahami.',
                filledAt: DateTime(2026, 8, 27),
              ),
            ]
          : const [],
    );
  }
}

SurveyMonitoringAssignment _assignment({
  required int id,
  required int respondents,
  required bool open,
  double? average,
}) => SurveyMonitoringAssignment(
  id: id,
  teacherName: id == 11 ? 'Guru Matematika Mobile' : 'Guru IPA Mobile',
  teacherNip: '198001012010011111',
  subjectName: id == 11 ? 'Matematika' : 'IPA',
  className: 'VIII.A',
  academicYearName: '2026/2027',
  active: true,
  studentCount: 6,
  respondentCount: respondents,
  responsePercentage: respondents / 6 * 100,
  responseStatus: respondents == 0
      ? 'belum'
      : (respondents >= 6 ? 'lengkap' : 'berjalan'),
  resultsOpen: open,
  average: average,
);
