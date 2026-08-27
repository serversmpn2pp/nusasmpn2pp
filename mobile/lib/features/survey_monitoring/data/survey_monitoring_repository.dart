import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/survey_monitoring/data/survey_monitoring_remote_data_source.dart';
import 'package:nusa/features/survey_monitoring/domain/survey_monitoring.dart';

final class SurveyMonitoringRepository {
  SurveyMonitoringRepository(this._remote);

  final SurveyMonitoringRemoteDataSource _remote;

  Future<SurveyMonitoringPage> fetch({
    int? academicYearId,
    required String semester,
    required String status,
    required String query,
    required int page,
  }) => _remote.fetch(
    academicYearId: academicYearId,
    semester: semester,
    status: status,
    query: query,
    page: page,
  );

  Future<SurveyMonitoringDetail> fetchDetail({
    required int assignmentId,
    required String semester,
  }) => _remote.fetchDetail(assignmentId: assignmentId, semester: semester);
}

final surveyMonitoringRepositoryProvider = Provider<SurveyMonitoringRepository>(
  (ref) => SurveyMonitoringRepository(
    ref.watch(surveyMonitoringRemoteDataSourceProvider),
  ),
);
