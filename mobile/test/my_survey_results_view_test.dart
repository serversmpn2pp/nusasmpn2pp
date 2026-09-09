import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_survey_results/data/my_survey_results_remote_data_source.dart';
import 'package:nusa/features/survey_monitoring/domain/survey_monitoring.dart';
import 'package:nusa/features/survey_monitoring/presentation/survey_monitoring_detail_view.dart';
import 'package:nusa/features/survey_monitoring/presentation/survey_monitoring_view.dart';

void main() {
  testWidgets('hasil survei guru menampilkan penugasan sendiri dan rincian', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final remote = _FakeMySurveyResultsRemoteDataSource();
    final router = GoRouter(
      initialLocation: '/hasil-survei-saya',
      routes: [
        GoRoute(
          path: '/hasil-survei-saya',
          builder: (context, state) =>
              const SurveyMonitoringView(personal: true),
          routes: [
            GoRoute(
              path: ':id',
              builder: (context, state) => SurveyMonitoringDetailView(
                assignmentId: int.parse(state.pathParameters['id']!),
                semester: state.uri.queryParameters['semester'] ?? 'ganjil',
                personal: true,
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
          mySurveyResultsRemoteDataSourceProvider.overrideWithValue(remote),
        ],
        child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Hasil Survei Saya'), findsOneWidget);
    expect(find.text('Matematika · VIII.A'), findsOneWidget);
    expect(find.text('Guru Lain'), findsNothing);
    expect(remote.fetchCount, 1);
    expect(tester.takeException(), isNull);

    await tester.ensureVisible(find.byKey(const Key('survey-monitoring-11')));
    await tester.tap(find.byKey(const Key('survey-monitoring-11')));
    await tester.pumpAndSettle();

    expect(find.text('Rincian Hasil Survei'), findsOneWidget);
    expect(
      find.byKey(const Key('survey-monitoring-results-locked')),
      findsOneWidget,
    );
    expect(remote.detailAssignmentId, 11);
    expect(tester.takeException(), isNull);
  });
}

final class _FakeMySurveyResultsRemoteDataSource
    implements MySurveyResultsRemoteDataSource {
  int fetchCount = 0;
  int? detailAssignmentId;

  @override
  Future<SurveyMonitoringPage> fetch({
    int? academicYearId,
    required String semester,
    required String status,
    required String query,
    required int page,
    int perPage = 15,
  }) async {
    fetchCount++;
    return SurveyMonitoringPage(
      items: const [_assignment],
      summary: const SurveyMonitoringSummary(
        assignments: 1,
        responseTarget: 6,
        responses: 2,
        openResults: 0,
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
  }

  @override
  Future<SurveyMonitoringDetail> fetchDetail({
    required int assignmentId,
    required String semester,
  }) async {
    detailAssignmentId = assignmentId;
    return SurveyMonitoringDetail(
      assignment: _assignment,
      semester: semester,
      minimumRespondents: 5,
      scale: const [],
      questions: const [],
      suggestions: const [],
    );
  }
}

const _assignment = SurveyMonitoringAssignment(
  id: 11,
  teacherName: 'Guru Matematika Mobile',
  teacherNip: '198001012010011111',
  subjectName: 'Matematika',
  className: 'VIII.A',
  academicYearName: '2026/2027',
  active: true,
  studentCount: 6,
  respondentCount: 2,
  responsePercentage: 33.3,
  responseStatus: 'berjalan',
  resultsOpen: false,
);
